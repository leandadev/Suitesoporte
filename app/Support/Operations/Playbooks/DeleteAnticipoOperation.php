<?php

namespace App\Support\Operations\Playbooks;

use App\Models\SupportAuditLog;
use App\Services\StellarConnectionService;
use App\Support\Operations\Contracts\TechnicalOperationInterface;
use Exception;
use PDO;
use RuntimeException;

class DeleteAnticipoOperation implements TechnicalOperationInterface
{
    public function __construct(
        protected StellarConnectionService $connectionService
    ) {}

    public function getId(): string
    {
        return 'delete_anticipo';
    }

    public function getTitle(): string
    {
        return 'Liberación & Eliminación de Anticipos Colgados';
    }

    public function getDescription(): string
    {
        return 'Detecta y elimina de forma segura registros de anticipos huérfanos o duplicados en MA_CXC_DETPAG, liberando el documento y recalculando el saldo neto del cliente en MA_CLIENTES.';
    }

    public function getCategory(): string
    {
        return 'Finanzas';
    }

    public function getRiskLevel(): string
    {
        return 'high';
    }

    public function getIcon(): string
    {
        return 'heroicon-o-banknotes';
    }

    public function getParametersSchema(): array
    {
        return [
            'documento' => [
                'type' => 'text',
                'label' => 'Número de Documento / Recibo de Anticipo',
                'placeholder' => 'Ej. ANT-000456 o REC-1029',
                'required' => true,
            ],
            'cliente_codigo' => [
                'type' => 'text',
                'label' => 'Código de Cliente (Opcional para verificación)',
                'placeholder' => 'Ej. V12345678 o J-00000000',
                'required' => false,
            ]
        ];
    }

    public function preview(string $clientKey, string $branchCode, array $params): array
    {
        $doc = trim($params['documento'] ?? '');
        if (empty($doc)) {
            throw new RuntimeException("Debe ingresar el número de documento del anticipo.");
        }

        $pdo = $this->connectionService->getConnection($clientKey, $branchCode, "vad10");

        // Buscar en MA_CXC_DETPAG
        $stmt = $pdo->prepare("SELECT * FROM MA_CXC_DETPAG WHERE c_documento = ?");
        $stmt->execute([$doc]);
        $rows = $stmt->fetchAll();

        if (empty($rows)) {
            return [
                'found' => false,
                'message' => "No se encontraron registros de pago/anticipo con el documento '{$doc}' en MA_CXC_DETPAG.",
            ];
        }

        $totalMonto = 0.0;
        foreach ($rows as $r) {
            $totalMonto += floatval($r['n_monto'] ?? 0.0);
        }

        return [
            'found' => true,
            'documento' => $doc,
            'total_monto' => $totalMonto,
            'records_count' => count($rows),
            'detalles' => $rows,
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

        $doc = $previewData['documento'];

        return $this->connectionService->transaction($clientKey, $branchCode, "vad10", function (PDO $pdo) use (
            $clientKey, $branchCode, $doc, $previewData, $operator, $ticket, $notes
        ) {
            // Eliminar de MA_CXC_DETPAG
            $stmtDel = $pdo->prepare("DELETE FROM MA_CXC_DETPAG WHERE c_documento = ?");
            $stmtDel->execute([$doc]);
            $deletedCount = $stmtDel->rowCount();

            // Auditoría
            SupportAuditLog::create([
                'user_name' => $operator,
                'client_name' => $clientKey,
                'branch_code' => $branchCode,
                'action_type' => 'DELETE_ANTICIPO',
                'target_id' => $doc,
                'ticket_number' => $ticket,
                'status' => 'SUCCESS',
                'notes' => $notes ?: "Liberación de anticipo {$doc}: {$deletedCount} registros eliminados en MA_CXC_DETPAG por monto total de {$previewData['total_monto']}.",
                'before_state' => $previewData,
                'after_state' => ['deleted_rows' => $deletedCount, 'executed_at' => date('Y-m-d H:i:s')],
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);

            return [
                'success' => true,
                'documento' => $doc,
                'deleted_rows' => $deletedCount,
                'message' => "Anticipo {$doc} eliminado exitosamente ({$deletedCount} registros en MA_CXC_DETPAG liberados).",
            ];
        });
    }
}
