<?php

namespace App\Support\Operations\Playbooks;

use App\Models\SupportAuditLog;
use App\Services\StellarConnectionService;
use App\Support\Operations\Contracts\TechnicalOperationInterface;
use Exception;
use PDO;
use RuntimeException;

class ManualKardexAdjustmentOperation implements TechnicalOperationInterface
{
    public function __construct(
        protected StellarConnectionService $connectionService
    ) {}

    public function getId(): string
    {
        return 'manual_kardex_adjustment';
    }

    public function getTitle(): string
    {
        return 'Ajuste Técnico en TR_INVENTARIO (para DepoProd)';
    }

    public function getDescription(): string
    {
        return 'Asienta un movimiento correctivo directo en TR_INVENTARIO (Cargo o Descargo). No altera MA_DEPOPROD directamente para evitar inconsistencias; en la siguiente corrida del Agente DepoProd el saldo quedará perfectamente consolidado con trazabilidad de auditoría.';
    }

    public function getCategory(): string
    {
        return 'Inventario';
    }

    public function getRiskLevel(): string
    {
        return 'medium';
    }

    public function getIcon(): string
    {
        return 'heroicon-o-scale';
    }

    public function getParametersSchema(): array
    {
        return [
            'codigo_articulo' => [
                'type' => 'text',
                'label' => 'Código de Artículo',
                'placeholder' => 'Ej. 75010001 o ART-01',
                'required' => true,
            ],
            'deposito' => [
                'type' => 'text',
                'label' => 'Código de Depósito',
                'placeholder' => 'Ej. 01',
                'default' => '01',
                'required' => true,
            ],
            'tipo_movimiento' => [
                'type' => 'select',
                'label' => 'Tipo de Movimiento',
                'options' => [
                    'Cargo' => 'Cargo (Entrada / Incrementa existencia)',
                    'Descargo' => 'Descargo (Salida / Reduce existencia)',
                ],
                'default' => 'Cargo',
                'required' => true,
            ],
            'cantidad' => [
                'type' => 'number',
                'label' => 'Cantidad a Ajustar',
                'placeholder' => 'Ej. 5.00',
                'default' => 1,
                'required' => true,
            ],
            'motivo' => [
                'type' => 'text',
                'label' => 'Motivo Técnico del Ajuste',
                'placeholder' => 'Ej. Corrección por descuadre físico según inventario cíclico',
                'required' => true,
            ],
            'costo_unitario' => [
                'type' => 'number',
                'label' => 'Costo Unitario (Opcional, dejar vacío para tomar del ERP)',
                'placeholder' => '0.00',
                'default' => 0,
                'required' => false,
            ],
        ];
    }

    public function preview(string $clientKey, string $branchCode, array $params): array
    {
        $cod = trim($params['codigo_articulo'] ?? '');
        $dep = trim($params['deposito'] ?? '01');
        $tipo = trim($params['tipo_movimiento'] ?? 'Cargo');
        $cant = floatval($params['cantidad'] ?? 0);
        $costoParam = floatval($params['costo_unitario'] ?? 0);

        if (empty($cod)) {
            throw new RuntimeException("Debe ingresar el código de artículo.");
        }
        if ($cant <= 0) {
            throw new RuntimeException("La cantidad debe ser mayor a cero.");
        }

        $pdo = $this->connectionService->getConnection($clientKey, $branchCode, "vad10");

        // 1. Validar existencia del producto en MA_PRODUCTOS
        $stmtProd = $pdo->prepare("SELECT TOP 1 c_Codigo, c_Descri, n_costo_prom, n_costo_rep, c_departamento, c_grupo FROM MA_PRODUCTOS WHERE c_Codigo = ?");
        $stmtProd->execute([$cod]);
        $producto = $stmtProd->fetch();

        if (!$producto) {
            return [
                'found' => false,
                'message' => "El código de artículo '{$cod}' no existe en el maestro de productos (MA_PRODUCTOS).",
            ];
        }

        $costoReal = $costoParam > 0 ? $costoParam : floatval($producto['n_costo_prom'] ?? $producto['n_costo_rep'] ?? 0.0);

        // 2. Consultar saldo actual consolidado en MA_DEPOPROD
        $stmtDepo = $pdo->prepare("SELECT TOP 1 c_codarticulo, c_coddeposito, n_cant_real, n_cant_comp, n_cant_disp FROM MA_DEPOPROD WHERE c_codarticulo = ? AND c_coddeposito = ?");
        $stmtDepo->execute([$cod, $dep]);
        $depo = $stmtDepo->fetch();

        $saldoActual = floatval($depo['n_cant_real'] ?? 0.0);
        $saldoProyectado = ($tipo === 'Cargo') ? ($saldoActual + $cant) : ($saldoActual - $cant);

        // 3. Consultar últimos 5 movimientos en TR_INVENTARIO
        $stmtRecent = $pdo->prepare("SELECT TOP 5 c_Documento, c_Concepto, n_Cantidad, c_TipoMov, f_Fecha, c_Documento_Origen FROM TR_INVENTARIO WHERE c_CodArticulo = ? AND c_Deposito = ? ORDER BY f_Fecha DESC, ID DESC");
        $stmtRecent->execute([$cod, $dep]);
        $recentMovements = $stmtRecent->fetchAll();

        $warnings = [];
        if ($tipo === 'Descargo' && $saldoProyectado < 0) {
            $warnings[] = "⚠️ ATENCIÓN: El descargo dejará la existencia teórica en negativo ({$saldoProyectado} unidades) una vez consolidado por el Agente DepoProd.";
        }
        $warnings[] = "ℹ️ CONSOLIDACIÓN: Al asentarse en TR_INVENTARIO, el saldo no cambiará en MA_DEPOPROD de inmediato. Se reflejará automáticamente en cuanto se ejecute el Agente DepoProd.";

        return [
            'found' => true,
            'producto' => [
                'codigo' => $producto['c_Codigo'],
                'descripcion' => $producto['c_Descri'],
                'deposito' => $dep,
                'costo' => $costoReal,
                'saldo_actual_depoprod' => $saldoActual,
                'ajuste_propuesto' => ($tipo === 'Cargo' ? "+{$cant}" : "-{$cant}"),
                'saldo_proyectado_post_agente' => $saldoProyectado,
                'tipo_movimiento' => $tipo,
                'cantidad' => $cant,
            ],
            'recent_kardex' => $recentMovements,
            'warnings' => $warnings,
            'can_execute' => true,
        ];
    }

    public function execute(
        string $clientKey,
        string $branchCode,
        array $params,
        string $operator,
        ?string $ticket = null,
        ?string $notes = null
    ): array {
        $previewData = $this->preview($clientKey, $branchCode, $params);
        if (!$previewData['found']) {
            throw new RuntimeException($previewData['message']);
        }

        $prod = $previewData['producto'];
        $cod = $prod['codigo'];
        $desc = $prod['descripcion'];
        $dep = $prod['deposito'];
        $tipo = $prod['tipo_movimiento'];
        $cant = floatval($prod['cantidad']);
        $costo = floatval($prod['costo']);
        $totalCosto = $cant * $costo;
        $motivo = trim($params['motivo'] ?? 'Ajuste de inventario vía Stellar Ops Hub');

        $docId = 'AJU-TECH-' . date('YmdHis');

        return $this->connectionService->transaction($clientKey, $branchCode, "vad10", function (PDO $pdo) use (
            $clientKey, $branchCode, $cod, $desc, $dep, $tipo, $cant, $costo, $totalCosto, $motivo, $docId, $operator, $ticket, $notes, $previewData
        ) {
            // Asentar movimiento en TR_INVENTARIO
            $sqlInsert = "INSERT INTO TR_INVENTARIO (
                c_Documento, c_Concepto, c_CodArticulo, c_Descripcion,
                n_Cantidad, n_Costo, n_Total, c_Deposito,
                c_TipoMov, f_Fecha, c_Documento_Origen, c_CodLocalidad
            ) VALUES (
                :doc, 'AJU', :cod, :desc,
                :cant, :costo, :total, :dep,
                :tipo, GETDATE(), :ticket, :sucursal
            )";

            $stmt = $pdo->prepare($sqlInsert);
            $stmt->execute([
                ':doc' => $docId,
                ':cod' => $cod,
                ':desc' => mb_substr($desc, 0, 80),
                ':cant' => $cant,
                ':costo' => $costo,
                ':total' => $totalCosto,
                ':dep' => $dep,
                ':tipo' => $tipo,
                ':ticket' => $ticket ?: 'TICKET-SOPORTE',
                ':sucursal' => $branchCode,
            ]);

            // Auditoría Inmutable
            SupportAuditLog::create([
                'user_name' => $operator,
                'client_name' => $clientKey,
                'branch_code' => $branchCode,
                'action_type' => 'MANUAL_KARDEX_ADJUSTMENT',
                'target_id' => $docId,
                'ticket_number' => $ticket,
                'status' => 'SUCCESS',
                'notes' => $notes ?: "Ajuste técnico en TR_INVENTARIO doc {$docId} para {$cod} ({$desc}): {$tipo} de {$cant} unid. en depósito {$dep}. Motivo: {$motivo}. Consolidación pendiente por Agente DepoProd.",
                'before_state' => [
                    'saldo_depoprod_actual' => $previewData['producto']['saldo_actual_depoprod'],
                    'tipo' => $tipo,
                    'cantidad' => $cant,
                ],
                'after_state' => [
                    'documento_ajuste' => $docId,
                    'saldo_proyectado' => $previewData['producto']['saldo_proyectado_post_agente'],
                    'fecha_asentado' => date('Y-m-d H:i:s'),
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);

            return [
                'success' => true,
                'documento' => $docId,
                'codigo' => $cod,
                'tipo' => $tipo,
                'cantidad' => $cant,
                'deposito' => $dep,
                'message' => "Movimiento asentado con éxito en TR_INVENTARIO bajo documento '{$docId}'. En la próxima ejecución del Agente DepoProd, el saldo de MA_DEPOPROD se actualizará automáticamente a {$previewData['producto']['saldo_proyectado_post_agente']} unidades.",
            ];
        });
    }
}
