<?php

namespace App\Services\Playbooks;

use App\Models\SupportAuditLog;
use App\Services\StellarConnectionService;
use Exception;
use PDO;
use RuntimeException;

class ProductSyncPlaybook
{
    public function __construct(
        protected StellarConnectionService $connectionService
    ) {}

    /**
     * Busca un producto por código interno o código de barra en VAD10.
     */
    public function findProduct(string $clientKey, string $branchCode, string $query): ?array
    {
        $pdo = $this->connectionService->getConnection($clientKey, $branchCode, "vad10");
        $queryClean = trim($query);

        // 1. Búsqueda directa por c_Codigo
        $sql = "SELECT TOP 1 * FROM MA_PRODUCTOS WHERE c_Codigo = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$queryClean]);
        $prod = $stmt->fetch();

        // 2. Búsqueda por código de barra en MA_CODIGOS
        if (!$prod) {
            $sqlBar = "SELECT TOP 1 c_Codigo FROM MA_CODIGOS WHERE c_CodigoBarra = ? OR c_Codigo = ?";
            $stmtBar = $pdo->prepare($sqlBar);
            $stmtBar->execute([$queryClean, $queryClean]);
            $barRow = $stmtBar->fetch();
            if ($barRow && !empty($barRow["c_Codigo"])) {
                $stmt = $pdo->prepare("SELECT TOP 1 * FROM MA_PRODUCTOS WHERE c_Codigo = ?");
                $stmt->execute([$barRow["c_Codigo"]]);
                $prod = $stmt->fetch();
            }
        }

        if (!$prod) {
            return null;
        }

        // Obtener códigos de barra asociados
        $stmtBars = $pdo->prepare("SELECT c_CodigoBarra FROM MA_CODIGOS WHERE c_Codigo = ?");
        $stmtBars->execute([$prod["c_Codigo"]]);
        $barras = $stmtBars->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $prod["_codigos_barra"] = $barras;
        return $prod;
    }

    /**
     * Aplica la actualización sobre MA_PRODUCTOS y genera la réplica inmediata en TR_PEND_PRODUCTOS.
     */
    public function updateAndSync(
        string $clientKey,
        string $branchCode,
        string $productCode,
        array $updates,
        string $operatorName,
        ?string $ticketNumber = null,
        ?string $notes = null
    ): array {
        return $this->connectionService->transaction($clientKey, $branchCode, "vad10", function (PDO $pdo) use (
            $clientKey, $branchCode, $productCode, $updates, $operatorName, $ticketNumber, $notes
        ) {
            // 1. Obtener estado previo
            $stmtBefore = $pdo->prepare("SELECT * FROM MA_PRODUCTOS WHERE c_Codigo = ?");
            $stmtBefore->execute([$productCode]);
            $beforeState = $stmtBefore->fetch();

            if (!$beforeState) {
                throw new RuntimeException("El producto '{$productCode}' no existe en la base de datos.");
            }

            // 2. Construir UPDATE dinámico para MA_PRODUCTOS
            $allowedFields = [
                "c_Descripcio", "c_departamento", "c_grupo", "c_subgrupo",
                "c_marca", "c_modelo", "n_precio1", "n_precio2", "n_precio3",
                "n_activo", "n_tipopeso", "c_presenta", "CANT_DECIMALES"
            ];

            $setParts = [];
            $params = [];
            $appliedChanges = [];

            foreach ($updates as $field => $value) {
                if (in_array($field, $allowedFields, true) && array_key_exists($field, $beforeState)) {
                    $setParts[] = "{$field} = ?";
                    $params[] = $value;
                    $appliedChanges[$field] = [
                        "antes" => $beforeState[$field],
                        "despues" => $value
                    ];
                }
            }

            if (empty($setParts)) {
                throw new RuntimeException("No se especificaron cambios válidos para actualizar.");
            }

            $setParts[] = "update_date = GETDATE()";
            $setParts[] = "c_usuarioupd = ?";
            $params[] = substr($operatorName, 0, 50);

            // Clausula WHERE
            $params[] = $productCode;

            $updateSql = "UPDATE MA_PRODUCTOS SET " . implode(", ", $setParts) . " WHERE c_Codigo = ?";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute($params);

            // 3. Obtener el estado actualizado completo
            $stmtAfter = $pdo->prepare("SELECT * FROM MA_PRODUCTOS WHERE c_Codigo = ?");
            $stmtAfter->execute([$productCode]);
            $afterState = $stmtAfter->fetch();

            // 4. Inserción en TR_PEND_PRODUCTOS para réplica hacia cajas y sucursales
            // Leemos las columnas que existen en TR_PEND_PRODUCTOS
            $colStmt = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'TR_PEND_PRODUCTOS'");
            $pendColumns = $colStmt->fetchAll(PDO::FETCH_COLUMN);

            $insertCols = [];
            $insertPlaceholders = [];
            $insertVals = [];

            foreach ($afterState as $colName => $colVal) {
                if ($colName === "ID") {
                    // ID en TR_PEND suele ser identity o auto
                    continue;
                }
                if (in_array($colName, $pendColumns, true)) {
                    $insertCols[] = $colName;
                    $insertPlaceholders[] = "?";
                    if ($colName === "c_usuarioAdd") {
                        $insertVals[] = substr("SOPORTE_" . $operatorName, 0, 50);
                    } elseif ($colName === "add_date" || $colName === "update_date") {
                        $insertVals[] = date("Y-m-d H:i:s");
                    } else {
                        $insertVals[] = $colVal;
                    }
                }
            }

            if (!empty($insertCols)) {
                $sqlPend = "INSERT INTO TR_PEND_PRODUCTOS (" . implode(", ", $insertCols) . ") VALUES (" . implode(", ", $insertPlaceholders) . ")";
                $stmtPend = $pdo->prepare($sqlPend);
                $stmtPend->execute($insertVals);
            }

            // 5. Registrar Bitácora de Auditoría
            SupportAuditLog::create([
                'user_name' => $operatorName,
                'client_name' => $clientKey,
                'branch_code' => $branchCode,
                'action_type' => 'PRODUCT_UPDATE_SYNC',
                'target_id' => $productCode,
                'ticket_number' => $ticketNumber,
                'status' => 'SUCCESS',
                'notes' => $notes ?: 'Actualización de producto y generación automática de réplica en TR_PEND_PRODUCTOS',
                'before_state' => $beforeState,
                'after_state' => $afterState,
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);

            return [
                "success" => true,
                "codigo" => $productCode,
                "cambios" => $appliedChanges,
                "tr_pend_creado" => true
            ];
        });
    }
}
