<?php

namespace App\Support\Operations\Playbooks;

use App\Models\SupportAuditLog;
use App\Services\StellarConnectionService;
use App\Support\Operations\Contracts\TechnicalOperationInterface;
use PDO;
use RuntimeException;

class KardexAuditReconciliationOperation implements TechnicalOperationInterface
{
    public function __construct(
        protected StellarConnectionService $connectionService
    ) {}

    public function getId(): string
    {
        return 'kardex_reconciliation_audit';
    }

    public function getTitle(): string
    {
        return 'Diagnóstico y Cuadre: TR_INVENTARIO vs MA_DEPOPROD';
    }

    public function getDescription(): string
    {
        return 'Audita la coherencia entre el historial acumulado en TR_INVENTARIO (Kardex transaccional) y el saldo consolidado en MA_DEPOPROD. Detecta descuadres y determina si se deben a ejecuciones pendientes del Agente DepoProd.';
    }

    public function getCategory(): string
    {
        return 'Inventario';
    }

    public function getRiskLevel(): string
    {
        return 'low';
    }

    public function getIcon(): string
    {
        return 'heroicon-o-calculator';
    }

    public function getParametersSchema(): array
    {
        return [
            'codigo_articulo' => [
                'type' => 'text',
                'label' => 'Código de Artículo a Diagnosticar',
                'placeholder' => 'Ej. 75010001 o ART-01',
                'required' => true,
            ],
            'deposito' => [
                'type' => 'text',
                'label' => 'Depósito (Opcional, vacío para todos)',
                'placeholder' => 'Ej. 01',
                'default' => '',
                'required' => false,
            ],
        ];
    }

    public function preview(string $clientKey, string $branchCode, array $params): array
    {
        $cod = trim($params['codigo_articulo'] ?? '');
        $dep = trim($params['deposito'] ?? '');

        if (empty($cod)) {
            throw new RuntimeException("Debe ingresar el código de artículo.");
        }

        $pdo = $this->connectionService->getConnection($clientKey, $branchCode, "vad10");

        // 1. Maestro de Producto
        $stmtProd = $pdo->prepare("SELECT TOP 1 c_Codigo, c_Descri, c_departamento, c_grupo FROM MA_PRODUCTOS WHERE c_Codigo = ?");
        $stmtProd->execute([$cod]);
        $producto = $stmtProd->fetch();

        if (!$producto) {
            return [
                'found' => false,
                'message' => "El código de artículo '{$cod}' no existe en el ERP.",
            ];
        }

        // 2. Saldos consolidados en MA_DEPOPROD por depósito
        $sqlDepo = "SELECT c_coddeposito, ISNULL(n_cant_real, 0) as n_cant_real, ISNULL(n_cant_comp, 0) as n_cant_comp, ISNULL(n_cant_disp, 0) as n_cant_disp FROM MA_DEPOPROD WHERE c_codarticulo = ?";
        $paramsDepo = [$cod];
        if (!empty($dep)) {
            $sqlDepo .= " AND c_coddeposito = ?";
            $paramsDepo[] = $dep;
        }
        $stmtDepo = $pdo->prepare($sqlDepo);
        $stmtDepo->execute($paramsDepo);
        $depoRows = $stmtDepo->fetchAll();

        // 3. Suma acumulada de TR_INVENTARIO
        $sqlInv = "SELECT c_Deposito, 
                          SUM(CASE WHEN LOWER(c_TipoMov) IN ('cargo', 'entrada', 'in') THEN n_Cantidad ELSE -n_Cantidad END) as balance_kardex,
                          SUM(CASE WHEN LOWER(c_TipoMov) IN ('cargo', 'entrada', 'in') THEN n_Cantidad ELSE 0 END) as total_entradas,
                          SUM(CASE WHEN LOWER(c_TipoMov) NOT IN ('cargo', 'entrada', 'in') THEN n_Cantidad ELSE 0 END) as total_salidas,
                          COUNT(1) as total_movimientos
                   FROM TR_INVENTARIO
                   WHERE c_CodArticulo = ?";
        $paramsInv = [$cod];
        if (!empty($dep)) {
            $sqlInv .= " AND c_Deposito = ?";
            $paramsInv[] = $dep;
        }
        $sqlInv .= " GROUP BY c_Deposito";

        $stmtInv = $pdo->prepare($sqlInv);
        $stmtInv->execute($paramsInv);
        $invRows = $stmtInv->fetchAll();
        $invByDep = [];
        foreach ($invRows as $r) {
            $invByDep[trim($r['c_Deposito'])] = $r;
        }

        // 4. Comparativa detallada por depósito
        $comparison = [];
        $hasDiscrepancy = false;
        $allDeposits = array_unique(array_merge(
            array_map(fn($d) => trim($d['c_coddeposito']), $depoRows),
            array_keys($invByDep)
        ));

        foreach ($allDeposits as $dKey) {
            $saldoDepo = 0.0;
            foreach ($depoRows as $d) {
                if (trim($d['c_coddeposito']) === $dKey) {
                    $saldoDepo = floatval($d['n_cant_real']);
                    break;
                }
            }

            $kardexRow = $invByDep[$dKey] ?? null;
            $saldoKardex = floatval($kardexRow['balance_kardex'] ?? 0.0);
            $diff = round($saldoKardex - $saldoDepo, 4);

            if (abs($diff) > 0.0001) {
                $hasDiscrepancy = true;
            }

            $comparison[] = [
                'deposito' => $dKey,
                'saldo_consolidado_depoprod' => $saldoDepo,
                'saldo_calculado_kardex' => $saldoKardex,
                'descuadre' => $diff,
                'estado' => abs($diff) <= 0.0001 ? 'CUADRADO' : 'DESCUADRADO',
                'movimientos_kardex' => intval($kardexRow['total_movimientos'] ?? 0),
            ];
        }

        // 5. Últimos movimientos
        $stmtLast = $pdo->prepare("SELECT TOP 8 c_Documento, c_Concepto, n_Cantidad, c_TipoMov, f_Fecha, c_Deposito FROM TR_INVENTARIO WHERE c_CodArticulo = ? ORDER BY f_Fecha DESC, ID DESC");
        $stmtLast->execute([$cod]);
        $lastMovs = $stmtLast->fetchAll();

        $warnings = [];
        if ($hasDiscrepancy) {
            $warnings[] = "⚠️ DESCUADRE DETECTADO: El Kardex en TR_INVENTARIO no coincide con el saldo de MA_DEPOPROD. Ejecute el Agente DepoProd para consolidar los movimientos o aplique un ajuste correctivo.";
        } else {
            $warnings[] = "✅ ESTADO ÓPTIMO: Todos los movimientos de TR_INVENTARIO están 100% reflejados y consolidados en MA_DEPOPROD.";
        }

        return [
            'found' => true,
            'producto' => [
                'codigo' => $producto['c_Codigo'],
                'descripcion' => $producto['c_Descri'],
            ],
            'has_discrepancy' => $hasDiscrepancy,
            'comparison' => $comparison,
            'recent_movements' => $lastMovs,
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

        // Registrar auditoría del diagnóstico realizado
        SupportAuditLog::create([
            'user_name' => $operator,
            'client_name' => $clientKey,
            'branch_code' => $branchCode,
            'action_type' => 'KARDEX_AUDIT_DIAGNOSTIC',
            'target_id' => $previewData['producto']['codigo'],
            'ticket_number' => $ticket,
            'status' => 'SUCCESS',
            'notes' => $notes ?: "Diagnóstico de cuadre TR_INVENTARIO vs MA_DEPOPROD para {$previewData['producto']['codigo']} ({$previewData['producto']['descripcion']}). Resultado: " . ($previewData['has_discrepancy'] ? 'DESCUADRADO' : 'CUADRADO'),
            'before_state' => $previewData['comparison'],
            'after_state' => ['audited_at' => date('Y-m-d H:i:s')],
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        return [
            'success' => true,
            'codigo' => $previewData['producto']['codigo'],
            'has_discrepancy' => $previewData['has_discrepancy'],
            'comparison' => $previewData['comparison'],
            'message' => "Diagnóstico finalizado y asentado en bitácora. " . ($previewData['has_discrepancy'] ? 'Se registraron discrepancias en los depósitos auditados.' : 'El inventario está 100% cuadrado.'),
        ];
    }
}
