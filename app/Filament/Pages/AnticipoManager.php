<?php

namespace App\Filament\Pages;

use App\Services\Playbooks\AnticipoFixPlaybook;
use App\Services\StellarConnectionService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Throwable;
use UnitEnum;

class AnticipoManager extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Gestor de Anticipos CXC';
    protected static ?string $title = 'Eliminación Segura y Gestión de Anticipos';
    protected static string | UnitEnum | null $navigationGroup = 'Operaciones ERP';
    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.anticipo-manager';

    public string $clientKey = 'COISA_HOGAR';
    public string $branchCode = '01';
    public string $searchQuery = '';
    public array $anticipos = [];

    // Modal de confirmación
    public ?string $selectedDocument = null;
    public string $ticketNumber = '';
    public string $reason = '';
    public bool $forceDelete = false;

    public function mount(StellarConnectionService $connService): void
    {
        $clients = array_keys($connService->getAvailableClients());
        if (!empty($clients)) {
            $this->clientKey = in_array('COISA_HOGAR', $clients, true) ? 'COISA_HOGAR' : $clients[0];
        }
    }

    public function search(AnticipoFixPlaybook $playbook): void
    {
        if (empty(trim($this->searchQuery))) {
            Notification::make()->warning()->title('Ingrese un criterio de búsqueda')->body('Número de anticipo, cédula o código de cliente.')->send();
            return;
        }

        try {
            $this->anticipos = $playbook->searchAnticipos($this->clientKey, $this->branchCode, $this->searchQuery);
            if (empty($this->anticipos)) {
                Notification::make()->info()->title('Sin resultados')->body("No se encontraron anticipos coincidentes con '{$this->searchQuery}'.")->send();
            } else {
                Notification::make()->success()->title('Búsqueda completada')->body("Se encontraron " . count($this->anticipos) . " documentos.")->send();
            }
        } catch (Throwable $e) {
            Notification::make()->danger()->title('Error en consulta SQL')->body($e->getMessage())->send();
        }
    }

    public function prepareDelete(string $docNumber): void
    {
        $this->selectedDocument = $docNumber;
        $this->ticketNumber = '';
        $this->reason = '';
        $this->forceDelete = false;
    }

    public function executeDelete(AnticipoFixPlaybook $playbook): void
    {
        if (empty($this->selectedDocument)) {
            return;
        }

        if (empty(trim($this->ticketNumber))) {
            Notification::make()->warning()->title('Requerido Nro. de Ticket')->body('Indique el número de ticket o solicitud para la bitácora de auditoría.')->send();
            return;
        }

        try {
            $operator = auth()->user()->name ?? 'Operador Soporte';
            $res = $playbook->cancelAnticipo(
                $this->clientKey,
                $this->branchCode,
                $this->selectedDocument,
                $operator,
                $this->ticketNumber,
                $this->reason,
                $this->forceDelete
            );

            Notification::make()
                ->success()
                ->title('Anticipo Eliminado')
                ->body("El documento {$this->selectedDocument} ha sido eliminado exitosamente y registrado en la bitácora.")
                ->duration(8000)
                ->send();

            $this->selectedDocument = null;
            $this->search($playbook);
        } catch (Throwable $e) {
            Notification::make()->danger()->title('No se pudo eliminar el anticipo')->body($e->getMessage())->duration(10000)->send();
        }
    }
}