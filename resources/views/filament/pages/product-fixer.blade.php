<x-filament-panels::page>
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        
        <!-- Tarjeta de Selección de Instancia y Búsqueda -->
        <div class="ops-card">
            <div class="ops-card-top-bar"></div>
            
            <div class="ops-card-header">
                <div class="ops-card-icon">
                    <x-heroicon-o-server-stack style="width: 20px; height: 20px;" />
                </div>
                <div>
                    <h3 class="ops-card-title">Instancia Stellar ERP Destino</h3>
                    <p style="font-size: 0.8rem; color: #64748B; margin: 0;">Seleccione el cliente comercial y la sucursal para corregir el producto</p>
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
                    <label class="ops-label">Buscar Artículo (Código o Barra)</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="text" wire:model.defer="searchQuery" wire:keydown.enter="search" placeholder="Ej: 001, 14H, 759..." class="ops-input" style="font-family: monospace;" />
                        <button type="button" wire:click="search" class="ops-btn-primary" style="white-space: nowrap;">
                            <x-heroicon-o-magnifying-glass style="width: 16px; height: 16px;" />
                            Buscar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        @if($product)
            <!-- Formulario de Edición y Control de Réplica -->
            <div class="ops-card">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #E2E8F0; padding-bottom: 1rem; margin-bottom: 1.5rem;">
                    <div>
                        <span class="ops-badge ops-badge-cyan">CÓDIGO: {{ $product["c_Codigo"] }}</span>
                        <h2 style="font-size: 1.2rem; font-weight: 800; color: #0F172A; margin: 0.35rem 0 0 0;">{{ $product["c_Descripio"] }}</h2>
                        @if(!empty($product["_codigos_barra"]))
                            <p style="font-size: 0.75rem; color: #64748B; margin: 0.25rem 0 0 0;">
                                <strong>Códigos de Barra:</strong> {{ implode(", ", $product["_codigos_barra"]) }}
                            </p>
                        @endif
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 0.7rem; font-weight: 700; color: #94A3B8; text-transform: uppercase;">Última Modificación ERP</span>
                        <p style="font-size: 0.8rem; font-family: monospace; color: #334155; margin: 0.2rem 0 0 0;">{{ $product["update_date"] ?? "N/D" }}</p>
                    </div>
                </div>

                <div class="ops-grid-2">
                    <!-- Datos Maestros -->
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <div>
                            <label class="ops-label">Descripción Comercial</label>
                            <input type="text" wire:model.defer="descripcion" class="ops-input" />
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem;">
                            <div>
                                <label class="ops-label">Departamento</label>
                                <input type="text" wire:model.defer="departamento" class="ops-input" style="font-family: monospace;" />
                            </div>
                            <div>
                                <label class="ops-label">Grupo</label>
                                <input type="text" wire:model.defer="grupo" class="ops-input" style="font-family: monospace;" />
                            </div>
                            <div>
                                <label class="ops-label">Subgrupo</label>
                                <input type="text" wire:model.defer="subgrupo" class="ops-input" style="font-family: monospace;" />
                            </div>
                        </div>

                        <div>
                            <label class="ops-label">Estado en Cajas / POS</label>
                            <select wire:model.defer="activo" class="ops-select">
                                <option value="1">Activo (Facturable)</option>
                                <option value="0">Inactivo / Bloqueado</option>
                            </select>
                        </div>
                    </div>

                    <!-- Precios de Venta -->
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem;">
                            <div style="background: #F8FAFC; padding: 0.75rem; border-radius: 12px; border: 1px solid #E2E8F0;">
                                <label style="font-size: 0.7rem; font-weight: 700; color: #2E62A6; text-transform: uppercase;">Precio 1 (USD)</label>
                                <input type="number" step="0.01" wire:model.defer="precio1" class="ops-input" style="font-size: 1.1rem; font-weight: 800;" />
                                <span style="font-size: 0.65rem; color: #94A3B8; margin-top: 0.25rem; display: block;">ERP: {{ number_format($product["n_precio1"] ?? 0, 2) }}</span>
                            </div>
                            <div style="background: #F8FAFC; padding: 0.75rem; border-radius: 12px; border: 1px solid #E2E8F0;">
                                <label style="font-size: 0.7rem; font-weight: 700; color: #3270A6; text-transform: uppercase;">Precio 2 (Mayor)</label>
                                <input type="number" step="0.01" wire:model.defer="precio2" class="ops-input" style="font-size: 1.1rem; font-weight: 800;" />
                                <span style="font-size: 0.65rem; color: #94A3B8; margin-top: 0.25rem; display: block;">ERP: {{ number_format($product["n_precio2"] ?? 0, 2) }}</span>
                            </div>
                            <div style="background: #F8FAFC; padding: 0.75rem; border-radius: 12px; border: 1px solid #E2E8F0;">
                                <label style="font-size: 0.7rem; font-weight: 700; color: #049DD9; text-transform: uppercase;">Precio 3 (Especial)</label>
                                <input type="number" step="0.01" wire:model.defer="precio3" class="ops-input" style="font-size: 1.1rem; font-weight: 800;" />
                                <span style="font-size: 0.65rem; color: #94A3B8; margin-top: 0.25rem; display: block;">ERP: {{ number_format($product["n_precio3"] ?? 0, 2) }}</span>
                            </div>
                        </div>

                        <!-- Justificación de Auditoría -->
                        <div style="background: rgba(4, 157, 217, 0.06); padding: 1rem; border-radius: 12px; border: 1px solid rgba(4, 157, 217, 0.25);">
                            <div style="display: flex; align-items: center; gap: 0.4rem; margin-bottom: 0.75rem;">
                                <x-heroicon-o-shield-check style="width: 16px; height: 16px; color: #049DD9;" />
                                <span style="font-size: 0.75rem; font-weight: 800; color: #2E62A6; text-transform: uppercase;">Control de Trazabilidad Leandatech</span>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                <div>
                                    <label class="ops-label">Nro. de Ticket *</label>
                                    <input type="text" wire:model.defer="ticketNumber" placeholder="Ej: TICKET-10492" class="ops-input" />
                                </div>
                                <div>
                                    <label class="ops-label">Justificación</label>
                                    <input type="text" wire:model.defer="notes" placeholder="Ej: Corrección precio solicitado" class="ops-input" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid #E2E8F0; display: flex; justify-content: space-between; align-items: center;">
                    <div style="font-size: 0.8rem; color: #64748B; display: flex; align-items: center; gap: 0.5rem;">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #049DD9; display: inline-block;"></span>
                        Se actualiza <strong>MA_PRODUCTOS</strong> y se replica automáticamente en <strong>TR_PEND_PRODUCTOS</strong>.
                    </div>
                    <button type="button" wire:click="applyChanges" class="ops-btn-primary" style="padding: 0.75rem 1.75rem;">
                        <x-heroicon-o-arrow-path-rounded-square style="width: 18px; height: 18px;" />
                        Guardar y Disparar Réplica a Cajas
                    </button>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
