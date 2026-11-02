<?php

namespace App\Filament\Pages;

use App\Models\SupportAuditLog;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class AuditLogViewer extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Bitácora de Auditoría';
    protected static ?string $title = 'Bitácora y Trazabilidad de Acciones de Soporte';
    protected static string | UnitEnum | null $navigationGroup = 'Seguridad y Auditoría';
    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.audit-log-viewer';

    public string $searchClient = '';
    public string $actionType = '';

    public function getLogs()
    {
        $q = SupportAuditLog::query()->latest();
        if (!empty($this->searchClient)) {
            $q->where('client_key', 'like', '%' . $this->searchClient . '%');
        }
        if (!empty($this->actionType)) {
            $q->where('action_type', $this->actionType);
        }
        return $q->take(50)->get();
    }

    protected function getViewData(): array
    {
        return [
            'logs' => $this->getLogs(),
        ];
    }
}
