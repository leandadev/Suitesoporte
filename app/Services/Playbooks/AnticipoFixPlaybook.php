<?php

namespace App\Services\Playbooks;

use App\Models\SupportAuditLog;
use App\Services\StellarConnectionService;
use Exception;
use PDO;
use RuntimeException;

class AnticipoFixPlaybook
{
    public function __construct(
        protected StellarConnectionService $connectionService
    ) {}

    /**
     * Busca anticipos en MA_CXC por Nro de Documento o Código/Cédula de Cliente.
     */
    public function searchAnticipos(string $clientKey, string $branchCode, string $query): array
    {
        $pdo = $this->connectionService->getConnection($clientKey, $branchCode, "vad10");
        $queryClean = trim($query);

        $sql = "SELECT TOP 50 
                    c_Documento, c_Relacion, c_Codigo, c_concepto, c_detalle,
                    f_fechae, n_total, n_Pagado, n_subtotal, c_codmoneda,
                    cs_RelacionAnticipo
                FROM MA_CXC 
                WHERE (c_Documento LIKE ? OR c_Codigo LIKE ? OR c_Relacion LIKE ?)
                ORDER BY f_fechae DESC";

        $stmt = $pdo->prepare($sql);
        $wildcard = "%{$queryClean}%";
        $stmt->execute([$wildcard, $wildcard, $wildcard]);
        $rows = $stmt->fetchAll();

        // Para cada anticipo, verificamos si tiene pagos o cruces aplicados en MA_CXC_DETPAG
        foreach ($rows as &$item) {
            $doc = $item["c_Documento"];
            $stmtDet = $pdo->prepare("SELECT COUNT(*) FROM MA_CXC_DETPAG WHERE c_Documento = ? OR c_Documento_Pago = ?");
            $stmtDet->execute([$doc, $doc]);
            $item["_cruces_count"] = (int)$stmtDet->fetchColumn();
            $item["_es_anticipo"] = stripos($item["c_concepto"], "ANT") !== false || stripos($item["c_Documento"], "ANT") !== false;
            $item["_esta_cruzado"] = $item["_cruces_count"] > 0 || ($item["n_Pagado"] > 0);
        }

        return $rows;
    }

    /**
     * Cancela o elimina un anticipo de forma segura con verificación previa de dependencias.
     */
    public function cancelAnticipo(
        string $clientKey,
        string $branchCode,
        string $documentNumber,
        string $operatorName,
        ?string $ticketNumber = null,
        ?string $reason = null,
        bool $force = false
    ): array {
        return $this->connectionService->transaction($clientKey, $branchCode, "vad10", function (PDO $pdo) use (
            $clientKey, $branchCode, $documentNumber, $operatorName, $ticketNumber, $reason, $force
        ) {
            // 1. Obtener anticipo
            $stmt = $pdo->prepare("SELECT * FROM MA_CXC WHERE c_Documento = ?");
            $stmt->execute([$documentNumber]);
            $anticipo = $stmt->fetch();

            if (!$anticipo) {
                throw new RuntimeException("El documento '{$documentNumber}' no existe en MA_CXC.");
            }

            // 2. Validar si tiene cruces activos
            $stmtDet = $pdo->prepare("SELECT COUNT(*) FROM MA_CXC_DETPAG WHERE c_Documento = ? OR c_Documento_Pago = ?");
            $stmtDet->execute([$documentNumber, $documentNumber]);
            $cruces = (int)$stmtDet->fetchColumn();

            if ($cruces > 0 && !$force) {
                throw new RuntimeException("El anticipo '{$documentNumber}' tiene {$cruces} cruces de pago aplicados en MA_CXC_DETPAG. Debe descruzar primero las facturas asociadas o solicitar autorización de Nivel 2.");
            }

            if (($anticipo["n_Pagado"] ?? 0) > 0 && !$force) {
                throw new RuntimeException("El anticipo tiene saldo amortizado (n_Pagado: {$anticipo['n_Pagado']}). No se puede eliminar sin forzar.");
            }

            // 3. Reversar saldo de cliente en MA_CLIENTES si existe
            $clienteCodigo = $anticipo["c_Codigo"];
            $monto = (float)($anticipo["n_total"] ?? 0);

            // 4. Eliminar o anular en MA_CXC
            $deleteStmt = $pdo->prepare("DELETE FROM MA_CXC WHERE c_Documento = ?");
            $deleteStmt->execute([$documentNumber]);

            // Si había registros huérfanos en MA_CXC_DETPAG y se forzó:
            if ($force && $cruces > 0) {
                $delDet = $pdo->prepare("DELETE FROM MA_CXC_DETPAG WHERE c_Documento = ? OR c_Documento_Pago = ?");
                $delDet->execute([$documentNumber, $documentNumber]);
            }

            // 5. Registrar Bitácora de Auditoría
            SupportAuditLog::create([
                'user_name' => $operatorName,
                'client_name' => $clientKey,
                'branch_code' => $branchCode,
                'action_type' => 'CANCEL_ANTICIPO',
                'target_id' => $documentNumber,
                'ticket_number' => $ticketNumber,
                'status' => 'SUCCESS',
                'notes' => $reason ?: "Eliminación segura de anticipo no cruzado {$documentNumber}",
                'before_state' => $anticipo,
                'after_state' => ['deleted' => true, 'forced' => $force],
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);

            return [
                "success" => true,
                "documento" => $documentNumber,
                "cliente" => $clienteCodigo,
                "monto_reversado" => $monto,
                "mensaje" => "Anticipo {$documentNumber} eliminado exitosamente."
            ];
        });
    }
}
