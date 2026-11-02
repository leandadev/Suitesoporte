<x-filament-panels::page>
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        
        <div class="ops-card">
            <div class="ops-card-top-bar"></div>
            
            <div class="ops-card-header">
                <div class="ops-card-icon">
                    <x-heroicon-o-clipboard-document-list style="width: 20px; height: 20px;" />
                </div>
                <div>
                    <h3 class="ops-card-title">Bitácora y Trazabilidad de Acciones de Soporte</h3>
                    <p style="font-size: 0.8rem; color: #64748B; margin: 0;">Historial inmutable de modificaciones en bases de datos Stellar ERP</p>
                </div>
            </div>
            
            <div style="display: flex; gap: 1rem; margin-bottom: 1.25rem;">
                <div style="flex: 1;">
                    <label class="ops-label">Filtrar por Cliente</label>
                    <input type="text" wire:model.live="searchClient" placeholder="Ej: COISA_HOGAR..." class="ops-input" />
                </div>
                <div style="flex: 1;">
                    <label class="ops-label">Filtrar por Tipo de Acción</label>
                    <select wire:model.live="actionType" class="ops-select">
                        <option value="">Todas las acciones</option>
                        <option value="PRODUCT_SYNC">PRODUCT_SYNC (Precios/TR_PEND)</option>
                        <option value="ANTICIPO_DELETE">ANTICIPO_DELETE (Liberación MA_CXC)</option>
                    </select>
                </div>
            </div>

            <!-- Tabla de Logs -->
            <div class="ops-table-container">
                <table class="ops-table">
                    <thead>
                        <tr>
                            <th>Fecha / Hora</th>
                            <th>Operador</th>
                            <th>Cliente / Sucursal</th>
                            <th>Acción</th>
                            <th>Objetivo</th>
                            <th>Ticket</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr>
                                <td style="font-size: 0.75rem; color: #64748B; font-family: monospace;">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                                <td style="font-weight: 700; color: #0F172A;">{{ $log->user_name }}</td>
                                <td><span class="ops-badge ops-badge-cyan">{{ $log->client_key }} &middot; {{ $log->branch_code }}</span></td>
                                <td style="font-weight: 600;">{{ $log->action_type }}</td>
                                <td style="font-family: monospace; font-weight: 700;">{{ $log->target_id }}</td>
                                <td><span style="font-size: 0.75rem; color: #2E62A6; font-weight: 700;">{{ $log->ticket_number ?? 'S/T' }}</span></td>
                                <td>
                                    @if($log->status === 'SUCCESS')
                                        <span class="ops-badge ops-badge-success">ÉXITO</span>
                                    @else
                                        <span class="ops-badge ops-badge-danger">FALLO</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" style="text-align: center; color: #94A3B8; padding: 2rem;">
                                    No hay registros de auditoría aún. Las correcciones ejecutadas aparecerán aquí.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top: 1rem;">
                
            </div>
        </div>
    </div>
</x-filament-panels::page>
