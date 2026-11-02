<?php

namespace App\Support\Operations\Playbooks;

use App\Models\SupportAuditLog;
use App\Services\StellarConnectionService;
use App\Support\Operations\Contracts\TechnicalOperationInterface;
use Exception;
use PDO;
use RuntimeException;

class BulkProductUpdateOperation implements TechnicalOperationInterface
{
    public function __construct(
        protected StellarConnectionService $connectionService
    ) {}

    public function getId(): string
    {
        return 'bulk_product_update';
    }

    public function getTitle(): string
    {
        return 'Cambios Masivos de Productos & Sincronización TR_PEND';
    }

    public function getDescription(): string
    {
        return 'Ajusta precios por porcentaje, activa/desactiva productos o reasigna departamentos por lote en MA_PRODUCTOS, encolando automáticamente cada registro en TR_PEND_PRODUCTOS para su réplica inmediata hacia cajas registradoras y sucursales.';
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
        return 'heroicon-o-arrows-up-down';
    }

    public function getParametersSchema(): array
    {
        return [
            'filter_type' => [
                'type' => 'select',
                'label' => 'Filtrar Productos Por',
                'options' => [
                    'department' => 'Código de Departamento',
                    'group' => 'Código de Grupo',
                    'brand' => 'Marca del Producto',
                    'code_list' => 'Lista Específica de Códigos (separados por coma)',
                ],
                'default' => 'department',
                'required' => true,
            ],
            'filter_value' => [
                'type' => 'text',
                'label' => 'Valor del Filtro',
                'placeholder' => 'Ej. 01 (para dpto), o ART-1,ART-2',
                'required' => true,
            ],
            'action_type' => [
                'type' => 'select',
                'label' => 'Tipo de Modificación Masiva',
                'options' => [
                    'percentage_price' => 'Ajuste Porcentual de Precio (+% o -%)',
                    'set_active' => 'Activar o Desactivar Productos',
                    'change_department' => 'Reasignar a Nuevo Departamento',
                    'change_brand' => 'Reasignar Marca',
                ],
                'default' => 'percentage_price',
                'required' => true,
            ],
            'action_value' => [
                'type' => 'text',
                'label' => 'Valor a Aplicar',
                'placeholder' => 'Ej. 10 (para +10%), -5 (para -5%), o 1 (Activo) / 0 (Inactivo)',
                'required' => true,
            ],
            'price_tier' => [
                'type' => 'select',
                'label' => 'Precio a Modificar (Solo para ajuste porcentual)',
                'options' => [
                    'n_precio1' => 'Precio 1 (PVP Principal)',
                    'n_precio2' => 'Precio 2 (Mayorista)',
                    'n_precio3' => 'Precio 3 (Especial)',
                    'all' => 'Todos los Precios (1, 2 y 3)',
                ],
                'default' => 'n_precio1',
                'required' => false,
            ],
            'round_two_decimals' => [
                'type' => 'boolean',
                'label' => 'Redondear precios resultantes a 2 decimales',
                'default' => true,
            ],
        ];
    }

    /**
     * Construye la condición WHERE según los filtros.
     */
    protected function buildWhereClause(array $params): array
    {
        $filterType = $params['filter_type'] ?? 'department';
        $filterVal = trim($params['filter_value'] ?? '');

        if (empty($filterVal)) {
            throw new RuntimeException("Debe ingresar el valor del filtro de productos.");
        }

        $where = "";
        $queryParams = [];

        switch ($filterType) {
            case 'department':
                $where = "c_departamento = ?";
                $queryParams = [$filterVal];
                break;
            case 'group':
                $where = "c_grupo = ?";
                $queryParams = [$filterVal];
                break;
            case 'brand':
                $where = "c_marca LIKE ?";
                $queryParams = ["%{$filterVal}%"];
                break;
            case 'code_list':
                $codes = array_filter(array_map('trim', explode(',', $filterVal)));
                if (empty($codes)) {
                    throw new RuntimeException("No ingresó códigos de producto válidos.");
                }
                $placeholders = implode(',', array_fill(0, count($codes), '?'));
                $where = "c_Codigo IN ({$placeholders})";
                $queryParams = array_values($codes);
                break;
            default:
                throw new RuntimeException("Tipo de filtro no reconocido.");
        }

        return [$where, $queryParams];
    }

    /**
     * Fase 1: Simulación / Análisis previo (Dry-Run de solo lectura).
     */
    public function preview(string $clientKey, string $branchCode, array $params): array
    {
        [$where, $queryParams] = $this->buildWhereClause($params);
        $actionType = $params['action_type'] ?? 'percentage_price';
        $actionValue = trim($params['action_value'] ?? '0');
        $priceTier = $params['price_tier'] ?? 'n_precio1';
        $round = !empty($params['round_two_decimals']);

        $pdo = $this->connectionService->getConnection($clientKey, $branchCode, "vad10");

        // 1. Contar total de productos coincidentes
        $countStmt = $pdo->prepare("SELECT COUNT(*) AS total FROM MA_PRODUCTOS WHERE {$where}");
        $countStmt->execute($queryParams);
        $total = intval($countStmt->fetchColumn() ?: 0);

        if ($total === 0) {
            return [
                'found' => false,
                'message' => "No se encontraron productos que coincidan con el filtro ingresado.",
            ];
        }

        // 2. Extraer muestra de los primeros 25 productos para simulación
        $sampleStmt = $pdo->prepare("SELECT TOP 25 c_Codigo, c_Descripcio, c_departamento, c_marca, n_precio1, n_precio2, n_precio3, n_costoact, n_activo FROM MA_PRODUCTOS WHERE {$where} ORDER BY c_Codigo ASC");
        $sampleStmt->execute($queryParams);
        $samples = $sampleStmt->fetchAll();

        $previewRows = [];
        $pct = floatval($actionValue);

        foreach ($samples as $p) {
            $row = [
                'codigo' => $p['c_Codigo'],
                'descripcion' => $p['c_Descripcio'],
                'departamento' => $p['c_departamento'],
                'marca' => $p['c_marca'],
                'actual_p1' => floatval($p['n_precio1']),
                'simulado_p1' => floatval($p['n_precio1']),
            ];

            if ($actionType === 'percentage_price') {
                $multiplier = 1.0 + ($pct / 100.0);
                $newP1 = floatval($p['n_precio1']) * $multiplier;
                if ($round) $newP1 = round($newP1, 2);
                $row['simulado_p1'] = $newP1;
                $row['diferencia'] = round($newP1 - floatval($p['n_precio1']), 2);
            } elseif ($actionType === 'set_active') {
                $row['estado_actual'] = intval($p['n_activo']) === 1 ? 'Activo' : 'Inactivo';
                $row['estado_simulado'] = intval($actionValue) === 1 ? 'Activo' : 'Inactivo';
            } elseif ($actionType === 'change_department') {
                $row['departamento_actual'] = $p['c_departamento'];
                $row['departamento_simulado'] = $actionValue;
            }

            $previewRows[] = $row;
        }

        return [
            'found' => true,
            'total_affected' => $total,
            'action_type' => $actionType,
            'action_value' => $actionValue,
            'sample_rows' => $previewRows,
            'can_execute' => true,
        ];
    }

    /**
     * Fase 2: Ejecución Atómica & Encolado en TR_PEND_PRODUCTOS.
     */
    public function execute(
        string $clientKey,
        string $branchCode,
        array $params,
        string $operator,
        ?string $ticket = null,
        ?string $notes = null
    ): array {
        [$where, $queryParams] = $this->buildWhereClause($params);
        $actionType = $params['action_type'] ?? 'percentage_price';
        $actionValue = trim($params['action_value'] ?? '0');
        $priceTier = $params['price_tier'] ?? 'n_precio1';
        $round = !empty($params['round_two_decimals']);

        return $this->connectionService->transaction($clientKey, $branchCode, "vad10", function (PDO $pdo) use (
            $clientKey, $branchCode, $where, $queryParams, $actionType, $actionValue, $priceTier, $round, $operator, $ticket, $notes
        ) {
            // 1. Obtener lista de códigos a afectar
            $stmtCodes = $pdo->prepare("SELECT c_Codigo FROM MA_PRODUCTOS WHERE {$where}");
            $stmtCodes->execute($queryParams);
            $codes = $stmtCodes->fetchAll(PDO::FETCH_COLUMN);

            if (empty($codes)) {
                throw new RuntimeException("No hay productos para actualizar.");
            }

            // 2. Construir el UPDATE SQL según acción
            $updateSql = "";
            $updateParams = [];

            if ($actionType === 'percentage_price') {
                $factor = 1.0 + (floatval($actionValue) / 100.0);
                if ($priceTier === 'n_precio1') {
                    $updateSql = $round
                        ? "UPDATE MA_PRODUCTOS SET n_precio1 = ROUND(n_precio1 * ?, 2), update_date = GETDATE(), c_usuarioupd = ? WHERE {$where}"
                        : "UPDATE MA_PRODUCTOS SET n_precio1 = n_precio1 * ?, update_date = GETDATE(), c_usuarioupd = ? WHERE {$where}";
                    $updateParams = array_merge([$factor, substr($operator, 0, 50)], $queryParams);
                } elseif ($priceTier === 'all') {
                    $updateSql = $round
                        ? "UPDATE MA_PRODUCTOS SET n_precio1 = ROUND(n_precio1 * ?, 2), n_precio2 = ROUND(n_precio2 * ?, 2), n_precio3 = ROUND(n_precio3 * ?, 2), update_date = GETDATE(), c_usuarioupd = ? WHERE {$where}"
                        : "UPDATE MA_PRODUCTOS SET n_precio1 = n_precio1 * ?, n_precio2 = n_precio2 * ?, n_precio3 = n_precio3 * ?, update_date = GETDATE(), c_usuarioupd = ? WHERE {$where}";
                    $updateParams = array_merge([$factor, $factor, $factor, substr($operator, 0, 50)], $queryParams);
                } else {
                    $updateSql = $round
                        ? "UPDATE MA_PRODUCTOS SET {$priceTier} = ROUND({$priceTier} * ?, 2), update_date = GETDATE(), c_usuarioupd = ? WHERE {$where}"
                        : "UPDATE MA_PRODUCTOS SET {$priceTier} = {$priceTier} * ?, update_date = GETDATE(), c_usuarioupd = ? WHERE {$where}";
                    $updateParams = array_merge([$factor, substr($operator, 0, 50)], $queryParams);
                }
            } elseif ($actionType === 'set_active') {
                $valActive = intval($actionValue) === 1 ? 1 : 0;
                $updateSql = "UPDATE MA_PRODUCTOS SET n_activo = ?, update_date = GETDATE(), c_usuarioupd = ? WHERE {$where}";
                $updateParams = array_merge([$valActive, substr($operator, 0, 50)], $queryParams);
            } elseif ($actionType === 'change_department') {
                $updateSql = "UPDATE MA_PRODUCTOS SET c_departamento = ?, update_date = GETDATE(), c_usuarioupd = ? WHERE {$where}";
                $updateParams = array_merge([$actionValue, substr($operator, 0, 50)], $queryParams);
            } elseif ($actionType === 'change_brand') {
                $updateSql = "UPDATE MA_PRODUCTOS SET c_marca = ?, update_date = GETDATE(), c_usuarioupd = ? WHERE {$where}";
                $updateParams = array_merge([$actionValue, substr($operator, 0, 50)], $queryParams);
            }

            // Ejecutar actualización
            $stmtUpdate = $pdo->prepare($updateSql);
            $stmtUpdate->execute($updateParams);
            $affectedCount = $stmtUpdate->rowCount();

            // 3. ENCOLADO ATÓMICO EN TR_PEND_PRODUCTOS (Garantiza réplica a cajas VAD20 y sucursales)
            $colStmt = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'TR_PEND_PRODUCTOS'");
            $pendColumns = $colStmt->fetchAll(PDO::FETCH_COLUMN);

            // Obtenemos los estados actualizados en bloques para insertar en TR_PEND_PRODUCTOS
            $chunkSize = 100;
            $codeChunks = array_chunk($codes, $chunkSize);
            $totalEnqueued = 0;

            foreach ($codeChunks as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '?'));
                $stmtFetch = $pdo->prepare("SELECT * FROM MA_PRODUCTOS WHERE c_Codigo IN ({$placeholders})");
                $stmtFetch->execute($chunk);
                $updatedRows = $stmtFetch->fetchAll();

                foreach ($updatedRows as $row) {
                    $insertCols = [];
                    $insertPlaceholders = [];
                    $insertVals = [];

                    foreach ($row as $colName => $colVal) {
                        if ($colName === 'ID') continue;
                        if (in_array($colName, $pendColumns, true)) {
                            $insertCols[] = $colName;
                            $insertPlaceholders[] = "?";
                            if ($colName === 'c_usuarioAdd') {
                                $insertVals[] = substr("SOPORTE_" . $operator, 0, 50);
                            } elseif ($colName === 'add_date' || $colName === 'update_date') {
                                $insertVals[] = date('Y-m-d H:i:s');
                            } else {
                                $insertVals[] = $colVal;
                            }
                        }
                    }

                    if (!empty($insertCols)) {
                        $sqlPend = "INSERT INTO TR_PEND_PRODUCTOS (" . implode(',', $insertCols) . ") VALUES (" . implode(',', $insertPlaceholders) . ")";
                        $stmtPend = $pdo->prepare($sqlPend);
                        $stmtPend->execute($insertVals);
                        $totalEnqueued++;
                    }
                }
            }

            // 4. Registrar Bitácora de Auditoría
            SupportAuditLog::create([
                'user_name' => $operator,
                'client_name' => $clientKey,
                'branch_code' => $branchCode,
                'action_type' => 'BULK_PRODUCT_UPDATE',
                'target_id' => "Lote de {$affectedCount} productos",
                'ticket_number' => $ticket,
                'status' => 'SUCCESS',
                'notes' => $notes ?: "Modificación masiva ({$actionType}: {$actionValue}) aplicada a {$affectedCount} productos con réplica de {$totalEnqueued} registros en TR_PEND_PRODUCTOS.",
                'before_state' => ['filter' => $params['filter_type'], 'filter_value' => $params['filter_value']],
                'after_state' => ['affected' => $affectedCount, 'tr_pend_enqueued' => $totalEnqueued, 'executed_at' => date('Y-m-d H:i:s')],
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);

            return [
                'success' => true,
                'affected_products' => $affectedCount,
                'tr_pend_enqueued' => $totalEnqueued,
                'message' => "Se actualizaron exitosamente {$affectedCount} productos en MA_PRODUCTOS y se generaron {$totalEnqueued} réplicas en TR_PEND_PRODUCTOS para sincronización inmediata de cajas y tiendas.",
            ];
        });
    }
}
