<?php

namespace App\Filament\Pages;

use App\Services\StellarConnectionService;
use App\Support\Operations\Contracts\TechnicalOperationInterface;
use App\Support\Operations\OperationRegistry;
use BackedEnum;
use Exception;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class TechnicalOperationsHub extends Page
{
    protected static string | UnitEnum | null $navigationGroup = 'Operaciones ERP';
    protected static ?string $navigationLabel = 'Centro de Operaciones Técnicas';
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.technical-operations-hub';

    public string $selectedClient = 'LOCAL_DEV';
    public string $selectedBranch = '01';
    public string $activeOperationId = 'delete_purchase';

    // Parámetros dinámicos del proceso
    public array $params = [];

    public string $ticketNumber = '';
    public string $operatorNotes = '';

    public ?array $previewResult = null;
    public ?array $executionResult = null;
    public bool $isExecuting = false;
    public bool $isPreviewing = false;

    public function getTitle(): string | Htmlable
    {
        return 'Centro de Operaciones Técnicas Stellar ERP';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'Automatización de procesos técnicos manuales: reversión de compras con ajuste en TR_INVENTARIO, cambios masivos con réplica en TR_PEND, ajustes de kardex y liberación de anticipos.';
    }

    public function mount(StellarConnectionService $connectionService, OperationRegistry $registry): void
    {
        $clients = $connectionService->getAvailableClients();
        if (!empty($clients)) {
            $this->selectedClient = array_key_first($clients);
            $branches = $connectionService->getBranchesForClient($this->selectedClient);
            if (!empty($branches)) {
                $this->selectedBranch = array_key_first($branches);
            }
        }

        $this->initParamsForOperation($this->activeOperationId, $registry);
    }

    protected function initParamsForOperation(string $opId, OperationRegistry $registry): void
    {
        try {
            $op = $registry->get($opId);
            $schema = $op->getParametersSchema();
            foreach ($schema as $key => $conf) {
                if (!isset($this->params[$key])) {
                    $this->params[$key] = $conf['default'] ?? '';
                }
            }
        } catch (Exception $e) {}
    }

    public function updatedSelectedClient(string $value, StellarConnectionService $connectionService): void
    {
        $branches = $connectionService->getBranchesForClient($value);
        $this->selectedBranch = !empty($branches) ? array_key_first($branches) : '01';
        $this->resetResults();
    }

    public function selectOperation(string $opId, OperationRegistry $registry): void
    {
        $this->activeOperationId = $opId;
        $this->resetResults();
        $this->initParamsForOperation($opId, $registry);
    }

    public function resetResults(): void
    {
        $this->previewResult = null;
        $this->executionResult = null;
    }

    public function runPreview(OperationRegistry $registry): void
    {
        $this->resetResults();
        $this->isPreviewing = true;

        try {
            $op = $registry->get($this->activeOperationId);
            $this->previewResult = $op->preview($this->selectedClient, $this->selectedBranch, $this->params);

            if ($this->previewResult['found'] ?? false) {
                Notification::make()
                    ->title('Simulación Completada')
                    ->body('Se inspeccionaron los registros correctamente en SQL Server.')
                    ->info()
                    ->send();
            } else {
                Notification::make()
                    ->title('No Encontrado')
                    ->body($this->previewResult['message'] ?? 'No se hallaron coincidencias.')
                    ->warning()
                    ->send();
            }
        } catch (Exception $e) {
            Notification::make()
                ->title('Error en Simulación')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->isPreviewing = false;
        }
    }

    public function runExecution(OperationRegistry $registry): void
    {
        if (empty($this->previewResult) || !($this->previewResult['can_execute'] ?? false)) {
            Notification::make()
                ->title('Acción Requerida')
                ->body('Primero debe realizar la simulación y verificar que los datos sean aptos para ejecución.')
                ->warning()
                ->send();
            return;
        }

        $this->isExecuting = true;
        try {
            $op = $registry->get($this->activeOperationId);
            $operator = auth()->user()?->name ?? 'Soporte Leandatech';

            $this->executionResult = $op->execute(
                $this->selectedClient,
                $this->selectedBranch,
                $this->params,
                $operator,
                $this->ticketNumber,
                $this->operatorNotes
            );

            Notification::make()
                ->title('¡Operación Ejecutada con Éxito!')
                ->body($this->executionResult['message'] ?? 'Proceso técnico completado y auditado.')
                ->success()
                ->send();

            // Limpiar simulación para evitar doble ejecución accidental
            $this->previewResult = null;
        } catch (Exception $e) {
            Notification::make()
                ->title('Error en Ejecución Transaccional')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->isExecuting = false;
        }
    }

    public function getOperationsProperty(OperationRegistry $registry): array
    {
        return $registry->getAll();
    }
}
