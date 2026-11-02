<?php

namespace App\Support\Operations\Playbooks;

use App\Models\SupportAuditLog;
use App\Services\StellarConnectionService;
use App\Support\Operations\Contracts\TechnicalOperationInterface;
use Exception;
use PDO;
use RuntimeException;

class DeletePurchaseOperation implements TechnicalOperationInterface
{
    public function __construct(
        protected StellarConnectionService $connectionService
    ) {}

    public function getId(): string
    {
        return 'delete_purchase';
    }

    public function getTitle(): string
    {
        return 'Reversión y Borrado Seguro de Compras';
    }

    public function getDescription(): string
    {
        return 'Elimina una compra errónea reversando sus movimientos en TR_INVENTARIO, renglones en TR_COMPRAS, cabecera en MA_COMPRAS y cuentas por pagar en MA_CXP, garantizando que el Agente DepoProd consolide el stock sin inconsistencias.';
    }

    public function getCategory(): string
    {
        return 'Compras';
    }

    public function getRiskLevel(): string
    {
        return 'high';
    }

    public function getIcon(): string
    {
        return 'heroicon-o-trash';
    }

    public function getParametersSchema(): array
    {
        return [
            'documento' => [
                'type' => 'text',
                'label' => 'Número de Documento de Compra',
                'placeholder' => 'Ej. 00012345 o COM-001',
                'required' => true,
            ],
            'concepto' => [
                'type' => 'text',
                'label' => 'Concepto del Documento (Opcional)',
                'placeholder' => 'Ej. COM, FCC o REC',
                'default' => '',
                'required' => false,
            ],
            'force_cxp' => [
                'type' => 'boolean',
                'label' => 'Forzar borrado aunque tenga abonos o registros en CxP',
                'default' => false,
            ]
        ];
    }

    /**
     * Fase 1: Simulación / Análisis previo (Dry-Run de solo lectura).
     */
    public function preview(string $clientKey, string $branchCode, array $params): array
    {
        $doc = trim($params['documento'] ?? '');
        $concepto = trim($params['concepto'] ?? '');

        if (empty($doc)) {
            throw new RuntimeException("Debe ingresar el número de documento de la compra a inspeccionar.");
        }

        $pdo = $this->connectionService->getConnection($clientKey, $branchCode, "vad10");

        // 1. Buscar Cabecera en MA_COMPRAS
        $sqlHeader = "SELECT TOP 1 * FROM MA_COMPRAS WHERE (c_DOCUMENTO = ? OR CAST(id AS VARCHAR) = ?)";
        $paramsHeader = [$doc, $doc];
        if (!empty($concepto)) {
            $sqlHeader .= " AND C_CONCEPTO = ?";
            $paramsHeader[] = $concepto;
        }

        $stmtHeader = $pdo->prepare($sqlHeader);
        $stmtHeader->execute($paramsHeader);
        $header = $stmtHeader->fetch();

        if (!$header) {
            return [
                'found' => false,
                'message' => "No se encontró ningún documento de compra con el número '{$doc}' en la sucursal seleccionada.",
            ];
        }

        $docReal = trim($header['c_DOCUMENTO'] ?? '');
        $conceptoReal = trim($header['C_CONCEPTO'] ?? 'COM');
        $relacionReal = trim($header['c_RELACION'] ?? '');

        // 2. Buscar Renglones en TR_COMPRAS
        $stmtRen = $pdo->prepare("SELECT c_CODARTICULO, c_descripcion, n_CANTIDAD, n_COSTO, n_total, C_DEPOSITO FROM TR_COMPRAS WHERE c_DOCUMENTO = ?");
        $stmtRen->execute([$docReal]);
        $renglones = $stmtRen->fetchAll();

        // 3. Buscar Movimientos en TR_INVENTARIO (clave para Agente DepoProd)
        $sqlInv = "SELECT c_Documento, c_Concepto, c_CodArticulo, n_Cantidad, c_Deposito, c_TipoMov, f_Fecha, c_Documento_Origen 
                   FROM TR_INVENTARIO 
                   WHERE c_Documento = :doc1 
                      OR (:rel <> '' AND c_Documento = :rel) 
                      OR c_Documento_Origen = :doc2";
        $stmtInv = $pdo->prepare($sqlInv);
        $stmtInv->execute([
            ':doc1' => $docReal,
            ':rel' => $relacionReal,
            ':doc2' => $docReal,
        ]);
        $movimientosInv = $stmtInv->fetchAll();

        // 4. Buscar Cuentas por Pagar asociadas en MA_CXP
        $stmtCxp = $pdo->prepare("SELECT C_Documento, C_Codigo, n_total, n_Pagado, f_fechae FROM MA_CXP WHERE C_Documento = ?");
        $stmtCxp->execute([$docReal]);
        $cxpList = $stmtCxp->fetchAll();

        $hasPayments = false;
        $totalPaid = 0.0;
        foreach ($cxpList as $cxp) {
            $pagado = floatval($cxp['n_Pagado'] ?? 0.0);
            if ($pagado > 0) {
                $hasPayments = true;
                $totalPaid += $pagado;
            }
        }

        // 5. Análisis de riesgos y advertencias
        $warnings = [];
        if ($hasPayments) {
            $warnings[] = "⚠️ ALERTA CRÍTICA: La compra posee pagos/abonos registrados en MA_CXP por un monto de " . number_format($totalPaid, 2) . ". Debe reversar los pagos antes de borrar, o marcar la casilla de forzar con justificación.";
        }

        if (empty($movimientosInv)) {
            $warnings[] = "ℹ️ NOTA: No se encontraron registros de movimiento en TR_INVENTARIO para este documento (quizá ya fue consolidado o eliminado previamente).";
        } else {
            $warnings[] = "📦 CONSOLIDACIÓN DEPOPROD: Se encontraron " . count($movimientosInv) . " movimiento(s) en TR_INVENTARIO. Al eliminarlos de TR_INVENTARIO, la próxima corrida del Agente DepoProd reajustará el stock de MA_DEPOPROD automáticamente.";
        }

        return [
            'found' => true,
            'header' => [
                'documento' => $header['c_DOCUMENTO'],
                'concepto' => $header['C_CONCEPTO'],
                'relacion' => $relacionReal,
                'proveedor' => $header['c_CODPROVEEDOR'],
                'fecha' => $header['d_FECHA'] ?? 'N/D',
                'subtotal' => floatval($header['N_SUBTOTAL'] ?? 0.0),
                'impuesto' => floatval($header['N_IMPUESTO'] ?? 0.0),
                'total' => floatval($header['n_total'] ?? ($header['N_SUBTOTAL'] + $header['N_IMPUESTO'])),
                'status' => $header['c_status'] ?? '',
                'usuario' => $header['c_usuarioadd'] ?? 'N/D',
            ],
            'items_count' => count($renglones),
            'items' => array_slice($renglones, 0, 50),
            'inventory_movements_count' => count($movimientosInv),
            'inventory_movements' => array_slice($movimientosInv, 0, 20),
            'cxp_count' => count($cxpList),
            'has_payments' => $hasPayments,
            'total_paid' => $totalPaid,
            'warnings' => $warnings,
            'can_execute' => (!$hasPayments || !empty($params['force_cxp'])),
        ];
    }

    /**
     * Fase 2: Ejecución atómica en transacción SQL Server.
     */
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

        if ($previewData['has_payments'] && empty($params['force_cxp'])) {
            throw new RuntimeException("Operación abortada: La compra tiene pagos aplicados en CxP (" . number_format($previewData['total_paid'], 2) . "). Desaplique los pagos primero o active la casilla de forzar.");
        }

        $doc = $previewData['header']['documento'];
        $rel = $previewData['header']['relacion'] ?? '';

        return $this->connectionService->transaction($clientKey, $branchCode, "vad10", function (PDO $pdo) use (
            $clientKey, $branchCode, $doc, $rel, $previewData, $operator, $ticket, $notes
        ) {
            // 1. Eliminar movimientos en TR_INVENTARIO (fundamental para que el Agente DepoProd consolide el saldo real)
            $sqlDelInv = "DELETE FROM TR_INVENTARIO 
                          WHERE c_Documento = :doc1 
                             OR (:rel <> '' AND c_Documento = :rel) 
                             OR c_Documento_Origen = :doc2";
            $stmtDelInv = $pdo->prepare($sqlDelInv);
            $stmtDelInv->execute([
                ':doc1' => $doc,
                ':rel' => $rel,
                ':doc2' => $doc,
            ]);
            $deletedInvCount = $stmtDelInv->rowCount();

            // 2. Eliminar renglones en TR_COMPRAS
            $stmtDelRen = $pdo->prepare("DELETE FROM TR_COMPRAS WHERE c_DOCUMENTO = ?");
            $stmtDelRen->execute([$doc]);
            $deletedRenCount = $stmtDelRen->rowCount();

            // 3. Eliminar descuentos asociados en TR_COMPRAS_DESCUENTOS (si existen)
            try {
                $stmtDelDesc = $pdo->prepare("DELETE FROM TR_COMPRAS_DESCUENTOS WHERE c_DOCUMENTO = ?");
                $stmtDelDesc->execute([$doc]);
            } catch (Exception $e) {
                // Tabla opcional según versión VAD
            }

            // 4. Eliminar cuentas por pagar asociadas en MA_CXP
            $deletedCxpCount = 0;
            if (!$previewData['has_payments'] || !empty($params['force_cxp'])) {
                $stmtDelCxp = $pdo->prepare("DELETE FROM MA_CXP WHERE C_Documento = ?");
                $stmtDelCxp->execute([$doc]);
                $deletedCxpCount = $stmtDelCxp->rowCount();
            }

            // 5. Eliminar cabecera en MA_COMPRAS
            $stmtDelHeader = $pdo->prepare("DELETE FROM MA_COMPRAS WHERE c_DOCUMENTO = ?");
            $stmtDelHeader->execute([$doc]);
            $deletedHeaderCount = $stmtDelHeader->rowCount();

            // 6. Registrar Bitácora de Auditoría Inmutable
            SupportAuditLog::create([
                'user_name' => $operator,
                'client_name' => $clientKey,
                'branch_code' => $branchCode,
                'action_type' => 'DELETE_PURCHASE',
                'target_id' => $doc,
                'ticket_number' => $ticket,
                'status' => 'SUCCESS',
                'notes' => $notes ?: "Reversión completa de compra {$doc}: {$deletedRenCount} renglones eliminados de TR_COMPRAS, {$deletedInvCount} movimientos purgados en TR_INVENTARIO (preparado para Agente DepoProd), {$deletedCxpCount} registros de CxP y cabecera eliminada.",
                'before_state' => $previewData['header'],
                'after_state' => [
                    'deleted_at' => date('Y-m-d H:i:s'),
                    'deleted_inv_rows' => $deletedInvCount,
                    'deleted_ren_rows' => $deletedRenCount,
                    'deleted_cxp_rows' => $deletedCxpCount,
                    'deleted_header' => $deletedHeaderCount,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);

            return [
                'success' => true,
                'documento' => $doc,
                'deleted_inventory_movements' => $deletedInvCount,
                'deleted_items' => $deletedRenCount,
                'deleted_cxp' => $deletedCxpCount,
                'message' => "La compra {$doc} fue eliminada exitosamente. Se eliminaron {$deletedRenCount} renglones de TR_COMPRAS y {$deletedInvCount} movimientos de TR_INVENTARIO. El Agente DepoProd consolidará el stock en MA_DEPOPROD automáticamente.",
            ];
        });
    }
}
