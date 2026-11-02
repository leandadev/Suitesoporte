<x-filament-panels::page>
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        
        <!-- Búsqueda y Filtros -->
        <div class="ops-card">
            <div class="ops-card-top-bar"></div>
            
            <div class="ops-card-header">
                <div class="ops-card-icon">
                    <x-heroicon-o-banknotes style="width: 20px; height: 20px;" />
                </div>
                <div>
                    <h3 class="ops-card-title">Gestión y Liberación de Anticipos (MA_CXC)</h3>
                    <p style="font-size: 0.8rem; color: #64748B; margin: 0;">Búsqueda segura con verificación de cruces en MA_CXC_DETPAG</p>
                </div>
            </div>
            
            <div class="ops-grid-3">
                <div>
                    <label class="ops-label">Cliente / Cadena</label>
                    <select wire:model.live="clientKey" class="ops-select">
                        @foreach(app(\App\Services\StellarConnectionService::class)->getAvailableClients() as $k => $v)
                            <option value="{{ $k }}">{{ $v }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="ops-label">Sucursal / Tienda</label>
                    <select wire:model.live="branchCode" class="ops-select">
                        @foreach(app(\App\Services\StellarConnectionService::class)->getBranchesForClient($clientKey) as $bCode => $bName)
                            <option value="{{ $bCode }}">{{ $bName }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="ops-label">Documento o Cód. Cliente</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="text" wire:model.defer="searchQuery" wire:keydown.enter="search" placeholder="Ej: ANT-01, V-123456..." class="ops-input" style="font-family: monospace;" />
                        <button type="button" wire:click="search" class="ops-btn-primary" style="white-space: nowrap;">
                            <x-heroicon-o-magnifying-glass style="width: 16px; height: 16px;" />
                            Buscar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        @if(!empty($anticipos))
            <!-- Listado de Anticipos Encontrados -->
            <div class="ops-card">
                <div style="margin-bottom: 1rem;">
                    <h4 style="font-size: 0.95rem; font-weight: 800; color: #0F172A; margin: 0;">Documentos Encontrados ({{ count($anticipos) }})</h4>
                    <p style="font-size: 0.75rem; color: #64748B; margin: 0.15rem 0 0 0;">Verifique si el anticipo posee cruces activos antes de su eliminación</p>
                </div>

                <div class="ops-table-container">
                    <table class="ops-table">
                        <thead>
                            <tr>
                                <th>Documento</th>
                                <th>Cliente ERP</th>
                                <th>Fecha Emisión</th>
                                <th>Importe</th>
                                <th>Cruces / Afectaciones</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($anticipos as $item)
                                @php
                                    $hasCruces = !empty($item['cruces']);
                                @endphp
                                <tr>
                                    <td style="font-family: monospace; font-weight: 700; color: #2E62A6;">{{ $item['c_documento'] }}</td>
                                    <td>
                                        <div style="font-weight: 600;">{{ $item['c_descripcio'] ?? 'N/D' }}</div>
                                        <span style="font-size: 0.7rem; color: #64748B; font-family: monospace;">{{ $item['c_codcliente'] }}</span>
                                    </td>
                                    <td style="font-size: 0.75rem; color: #64748B;">{{ $item['d_fecha'] ?? 'N/D' }}</td>
                                    <td style="font-size: 0.9rem; font-weight: 800; color: #0F172A;">
                                        ${{ number_format($item['n_importe'] ?? 0, 2) }}
                                    </td>
                                    <td>
                                        @if($hasCruces)
                                            <span class="ops-badge ops-badge-danger" style="margin-bottom: 4px;">{{ count($item['cruces']) }} Cruce(s)</span>
                                            <div style="font-size: 0.7rem; color: #94A3B8;">
                                                @foreach(array_slice($item['cruces'], 0, 2) as $c)
                                                    {{ $c['c_docafecta'] ?? '' }} (${{ number_format($c['n_monto'] ?? 0, 2) }})
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="ops-badge ops-badge-success">Sin Cruces (Limpio)</span>
                                        @endif
                                    </td>
                                    <td>
                                        <button 
                                            type="button" 
                                            wire:click="prepareDelete('{{ $item['c_documento'] }}')"
                                            class="ops-btn-danger" 
                                            style="padding: 0.4rem 0.85rem; font-size: 0.75rem;">
                                            <x-heroicon-o-trash style="width: 14px; height: 14px;" />
                                            Eliminar
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if($selectedDocument)
            <!-- Modal/Panel de Confirmación de Eliminación -->
            <div class="ops-card" style="border: 2px solid #EF4444; background: #FEF2F2;">
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                    <div style="width: 36px; height: 36px; border-radius: 10px; background: rgba(239, 68, 68, 0.2); color: #DC2626; display: flex; align-items: center; justify-content: center;">
                        <x-heroicon-o-exclamation-triangle style="width: 22px; height: 22px;" />
                    </div>
                    <div>
                        <h3 style="font-size: 1.1rem; font-weight: 800; color: #991B1B; margin: 0;">Confirmar Eliminación: {{ $selectedDocument }}</h3>
                        <p style="font-size: 0.8rem; color: #B91C1C; margin: 0.2rem 0 0 0;">Esta acción borrará el registro de MA_CXC y registrará el evento en la auditoría inmutable.</p>
                    </div>
                </div>

                <div class="ops-grid-2" style="margin-bottom: 1.25rem;">
                    <div>
                        <label class="ops-label" style="color: #991B1B;">Nro. de Ticket de Soporte *</label>
                        <input type="text" wire:model.defer="ticketNumber" placeholder="Ej: TICKET-10499" class="ops-input" />
                    </div>
                    <div>
                        <label class="ops-label" style="color: #991B1B;">Motivo / Justificación *</label>
                        <input type="text" wire:model.defer="reason" placeholder="Ej: Anticipo duplicado por corte de luz" class="ops-input" />
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" wire:click="$set('selectedDocument', null)" style="background: #FFFFFF; color: #64748B; border: 1px solid #CBD5E1; border-radius: 10px; padding: 0.65rem 1.25rem; font-weight: 700; font-size: 0.85rem; cursor: pointer;">
                        Cancelar
                    </button>
                    <button type="button" wire:click="executeDelete" class="ops-btn-danger">
                        <x-heroicon-o-trash style="width: 16px; height: 16px;" />
                        Confirmar y Eliminar Definitivamente
                    </button>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
