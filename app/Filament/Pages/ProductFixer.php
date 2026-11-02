<?php

namespace App\Filament\Pages;

use App\Services\Playbooks\ProductSyncPlaybook;
use App\Services\StellarConnectionService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Throwable;
use UnitEnum;

class ProductFixer extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static ?string $navigationLabel = 'Corrector de Productos & TR_PEND';
    protected static ?string $title = 'Corrección de Productos y Sincronización TR_PEND';
    protected static string | UnitEnum | null $navigationGroup = 'Operaciones ERP';
    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.product-fixer';

    public string $clientKey = 'COISA_HOGAR';
    public string $branchCode = '01';
    public string $searchQuery = '';
    public ?array $product = null;

    // Campos editables
    public string $descripcion = '';
    public string $departamento = '';
    public string $grupo = '';
    public string $subgrupo = '';
    public float $precio1 = 0.0;
    public float $precio2 = 0.0;
    public float $precio3 = 0.0;
    public int $activo = 1;

    public string $ticketNumber = '';
    public string $notes = '';

    public function mount(StellarConnectionService $connService): void
    {
        $clients = array_keys($connService->getAvailableClients());
        if (!empty($clients)) {
            $this->clientKey = in_array('COISA_HOGAR', $clients, true) ? 'COISA_HOGAR' : $clients[0];
        }
    }

    public function search(ProductSyncPlaybook $playbook): void
    {
        if (empty(trim($this->searchQuery))) {
            Notification::make()->warning()->title('Ingrese un código o código de barra')->send();
            return;
        }

        try {
            $prod = $playbook->findProduct($this->clientKey, $this->branchCode, $this->searchQuery);
            if (!$prod) {
                $this->product = null;
                Notification::make()->danger()->title('Producto no encontrado')->body("No se encontró el artículo '{$this->searchQuery}' en VAD10 ({$this->clientKey} / {$this->branchCode})")->send();
                return;
            }

            $this->product = $prod;
            $this->descripcion = (string)($prod['c_Descripcio'] ?? '');
            $this->departamento = (string)($prod['c_departamento'] ?? '');
            $this->grupo = (string)($prod['c_grupo'] ?? '');
            $this->subgrupo = (string)($prod['c_subgrupo'] ?? '');
            $this->precio1 = (float)($prod['n_precio1'] ?? 0.0);
            $this->precio2 = (float)($prod['n_precio2'] ?? 0.0);
            $this->precio3 = (float)($prod['n_precio3'] ?? 0.0);
            $this->activo = (int)($prod['n_activo'] ?? 1);

            Notification::make()->success()->title('Producto cargado')->body("Código: {$prod['c_Codigo']}")->send();
        } catch (Throwable $e) {
            Notification::make()->danger()->title('Error consultando base de datos')->body($e->getMessage())->send();
        }
    }

    public function applyChanges(ProductSyncPlaybook $playbook): void
    {
        if (!$this->product) {
            Notification::make()->danger()->title('Debe buscar un producto primero')->send();
            return;
        }

        if (empty(trim($this->ticketNumber))) {
            Notification::make()->warning()->title('Requerido Nro. de Ticket')->body('Indique el número de ticket o solicitud para auditoría.')->send();
            return;
        }

        try {
            $updates = [
                'c_Descripcio' => $this->descripcion,
                'c_departamento' => $this->departamento,
                'c_grupo' => $this->grupo,
                'c_subgrupo' => $this->subgrupo,
                'n_precio1' => $this->precio1,
                'n_precio2' => $this->precio2,
                'n_precio3' => $this->precio3,
                'n_activo' => $this->activo,
            ];

            $operator = auth()->user()->name ?? 'Operador Soporte';
            $res = $playbook->updateAndSync(
                $this->clientKey,
                $this->branchCode,
                $this->product['c_Codigo'],
                $updates,
                $operator,
                $this->ticketNumber,
                $this->notes
            );

            Notification::make()
                ->success()
                ->title('¡Cambio aplicado y sincronizado!')
                ->body("Producto {$this->product['c_Codigo']} actualizado en MA_PRODUCTOS y registrado en TR_PEND_PRODUCTOS para réplica a cajas.")
                ->duration(8000)
                ->send();

            // Refrescar
            $this->search($playbook);
        } catch (Throwable $e) {
            Notification::make()->danger()->title('Fallo al aplicar cambios')->body($e->getMessage())->send();
        }
    }
}