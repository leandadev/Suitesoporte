<x-filament-panels::page>
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">

        <!-- Selector de Cliente y Sucursal Conectada -->
        <div class="ops-card">
            <div class="ops-card-top-bar"></div>
            <div class="ops-card-header">
                <div class="ops-card-icon">
                    <x-heroicon-o-server-stack style="width: 20px; height: 20px;" />
                </div>
                <div>
                    <h3 class="ops-card-title">Instancia SQL Server Activa</h3>
                    <p style="font-size: 0.775rem; color: #94A3B8; margin: 0;">Selecciona el cliente y la base de datos sobre la que se ejecutará la operación técnica.</p>
                </div>
            </div>

            <div class="ops-grid-2">
                <div>
                    <label class="ops-label">Cliente Stellar ERP</label>
                    <select wire:model.live="selectedClient" class="ops-select">
                        @foreach(app(\App\Services\StellarConnectionService::class)->getAvailableClients() as $key => $name)
                            <option value="{{ $key }}">{{ $name }} ({{ $key }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="ops-label">Sucursal / Base de Datos</label>
                    <select wire:model.live="selectedBranch" class="ops-select">
                        @foreach(app(\App\Services\StellarConnectionService::class)->getBranchesForClient($selectedClient) as $code => $branchName)
                            <option value="{{ $code }}">{{ $branchName }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- Pestañas de Selección de Procesos Técnicos -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
            @foreach($this->operations as $op)
                @php
                    $isActive = ($activeOperationId === $op->getId());
                    $riskColor = match($op->getRiskLevel()) {
                        'critical' => '#DC2626',
                        'high' => '#EA580C',
                        'medium' => '#D97706',
                        default => '#059669',
                    };
                @endphp
                <div 
                    wire:click="selectOperation('{{ $op->getId() }}')"
                    class="ops-tab-card {{ $isActive ? 'active' : '' }}">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                        <span class="ops-badge ops-badge-cyan">
                            {{ $op->getCategory() }}
                        </span>
                        <span style="font-size: 0.7rem; font-weight: 700; color: {{ $riskColor }}; background: {{ $riskColor }}15; padding: 2px 8px; border-radius: 6px;">
                            Riesgo: {{ strtoupper($op->getRiskLevel()) }}
                        </span>
                    </div>

                    <h4 style="margin: 0.35rem 0; font-size: 0.95rem; font-weight: 800; color: var(--ops-text-main, #0F172A);">
                        {{ $op->getTitle() }}
                    </h4>
                    <p style="font-size: 0.75rem; color: #94A3B8; margin: 0; line-height: 1.4;">
                        {{ Str::limit($op->getDescription(), 95) }}
                    </p>
                </div>
            @endforeach
        </div>

        @php
            $currentOp = app(\App\Support\Operations\OperationRegistry::class)->get($activeOperationId);
        @endphp

        <!-- Formulario de Parámetros de la Operación Seleccionada -->
        <div class="ops-card">
            <div class="ops-card-top-bar"></div>
            <div class="ops-card-header">
                <div class="ops-card-icon">
                    <x-heroicon-o-adjustments-horizontal style="width: 20px; height: 20px;" />
                </div>
                <div>
                    <h3 class="ops-card-title">{{ $currentOp->getTitle() }}</h3>
                    <p style="font-size: 0.8rem; color: #94A3B8; margin: 0;">{{ $currentOp->getDescription() }}</p>
                </div>
            </div>

            <!-- Formulario Específico según la Operación -->
            @if($activeOperationId === 'delete_purchase')
                <div class="ops-grid-2" style="margin-bottom: 1.25rem;">
                    <div>
                        <label class="ops-label">Número de Documento de Compra *</label>
                        <input type="text" wire:model="params.documento" placeholder="Ej. 00054321 o COM-100" class="ops-input" />
                        <span style="font-size: 0.7rem; color: #94A3B8;">Se buscará en MA_COMPRAS, TR_COMPRAS, TR_INVENTARIO y MA_CXP.</span>
                    </div>

                    <div>
                        <label class="ops-label">Concepto del Documento (Opcional)</label>
                        <input type="text" wire:model="params.concepto" placeholder="Ej. COM, FCC o REC (dejar vacío si no aplica)" class="ops-input" />
                    </div>
                </div>

                <div style="background: rgba(220, 38, 38, 0.08); border: 1px dashed rgba(220, 38, 38, 0.35); border-radius: 10px; padding: 12px 16px; margin-bottom: 1.25rem;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" wire:model="params.force_cxp" style="width: 16px; height: 16px; border-radius: 4px; accent-color: #DC2626;" />
                        <span style="font-size: 0.825rem; font-weight: 700; color: #EF4444;">
                            Forzar borrado si posee pagos registrados en CxP (Requiere justificación especial de soporte)
                        </span>
                    </label>
                    <p style="font-size: 0.75rem; color: #F87171; margin: 6px 0 0 24px; line-height: 1.4;">
                        💡 <strong>Regla de Stock:</strong> En Stellar ERP, los movimientos se purgan en <strong>TR_INVENTARIO</strong> para que el <strong>Agente DepoProd</strong> consolide el saldo real en <strong>MA_DEPOPROD</strong> sin descuadres.
                    </p>
                </div>

            @elseif($activeOperationId === 'manual_kardex_adjustment')
                <div class="ops-grid-3" style="margin-bottom: 1.25rem;">
                    <div>
                        <label class="ops-label">Código de Artículo *</label>
                        <input type="text" wire:model="params.codigo_articulo" placeholder="Ej. 75010001 o ART-01" class="ops-input" />
                    </div>

                    <div>
                        <label class="ops-label">Depósito *</label>
                        <input type="text" wire:model="params.deposito" placeholder="01" class="ops-input" />
                    </div>

                    <div>
                        <label class="ops-label">Tipo de Movimiento *</label>
                        <select wire:model="params.tipo_movimiento" class="ops-select">
                            <option value="Cargo">Cargo (Entrada / Incrementa existencia)</option>
                            <option value="Descargo">Descargo (Salida / Reduce existencia)</option>
                        </select>
                    </div>
                </div>

                <div class="ops-grid-3" style="margin-bottom: 1.25rem;">
                    <div>
                        <label class="ops-label">Cantidad a Ajustar *</label>
                        <input type="number" step="0.01" wire:model="params.cantidad" placeholder="1.00" class="ops-input" />
                    </div>

                    <div>
                        <label class="ops-label">Costo Unitario (Opcional)</label>
                        <input type="number" step="0.0001" wire:model="params.costo_unitario" placeholder="Dejar en 0 para tomar costo ERP" class="ops-input" />
                    </div>

                    <div>
                        <label class="ops-label">Motivo Técnico del Ajuste *</label>
                        <input type="text" wire:model="params.motivo" placeholder="Ej. Corrección por inventario físico cíclico" class="ops-input" />
                    </div>
                </div>

                <div style="background: rgba(4, 157, 217, 0.08); border: 1px dashed rgba(4, 157, 217, 0.35); border-radius: 10px; padding: 12px 16px; margin-bottom: 1.25rem;">
                    <p style="font-size: 0.75rem; color: #38BDF8; margin: 0; line-height: 1.4;">
                        💡 <strong>Consolidación con Agente DepoProd:</strong> Este ajuste asienta un registro de auditoría en <strong>TR_INVENTARIO</strong> con concepto <strong>AJU</strong>. No modifica MA_DEPOPROD en crudo para evitar que el Agente DepoProd lo sobreescriba en su próxima corrida.
                    </p>
                </div>

            @elseif($activeOperationId === 'kardex_reconciliation_audit')
                <div class="ops-grid-2" style="margin-bottom: 1.25rem;">
                    <div>
                        <label class="ops-label">Código de Artículo a Diagnosticar *</label>
                        <input type="text" wire:model="params.codigo_articulo" placeholder="Ej. 75010001" class="ops-input" />
                    </div>

                    <div>
                        <label class="ops-label">Depósito (Opcional, vacío para todos)</label>
                        <input type="text" wire:model="params.deposito" placeholder="Ej. 01 o dejar vacío" class="ops-input" />
                    </div>
                </div>

                <div style="background: rgba(16, 185, 129, 0.08); border: 1px dashed rgba(16, 185, 129, 0.35); border-radius: 10px; padding: 12px 16px; margin-bottom: 1.25rem;">
                    <p style="font-size: 0.75rem; color: #34D399; margin: 0; line-height: 1.4;">
                        🔍 <strong>Auditoría Kardex:</strong> Suma algebraicamente todos los cargos y descargos en <strong>TR_INVENTARIO</strong> y los compara contra el balance consolidado en <strong>MA_DEPOPROD</strong> para detectar descuadres técnicos o corridas pendientes.
                    </p>
                </div>

            @elseif($activeOperationId === 'bulk_product_update')
                <div class="ops-grid-2" style="margin-bottom: 1.25rem;">
                    <div>
                        <label class="ops-label">Filtrar Productos Por *</label>
                        <select wire:model="params.filter_type" class="ops-select">
                            <option value="department">Código de Departamento (c_departamento)</option>
                            <option value="group">Código de Grupo (c_grupo)</option>
                            <option value="brand">Marca del Producto (c_marca)</option>
                            <option value="code_list">Lista de Códigos de Artículo (separados por coma)</option>
                        </select>
                    </div>

                    <div>
                        <label class="ops-label">Valor del Filtro *</label>
                        <input type="text" wire:model="params.filter_value" placeholder="Ej. 01 para dpto, o COD1,COD2" class="ops-input" />
                    </div>
                </div>

                <div class="ops-grid-3" style="margin-bottom: 1.25rem;">
                    <div>
                        <label class="ops-label">Acción a Ejecutar *</label>
                        <select wire:model="params.action_type" class="ops-select">
                            <option value="percentage_price">Aumento/Disminución Porcentual (%) de Precio</option>
                            <option value="fixed_price">Fijar Precio Específico ($)</option>
                            <option value="status_update">Cambiar Estado (1=Activo, 0=Inactivo)</option>
                            <option value="change_department">Reasignar a Nuevo Departamento</option>
                            <option value="change_group">Reasignar a Nuevo Grupo</option>
                        </select>
                    </div>

                    <div>
                        <label class="ops-label">Nuevo Valor / Porcentaje *</label>
                        <input type="text" wire:model="params.action_value" placeholder="Ej. 10 (para +10%), o 1 (Activo)" class="ops-input" />
                    </div>

                    <div>
                        <label class="ops-label">Precio a Modificar</label>
                        <select wire:model="params.price_tier" class="ops-select">
                            <option value="n_precio1">Precio 1 (PVP Principal)</option>
                            <option value="n_precio2">Precio 2 (Mayorista)</option>
                            <option value="n_precio3">Precio 3 (Especial)</option>
                            <option value="all">Todos los Precios (1, 2 y 3)</option>
                        </select>
                    </div>
                </div>

                <div style="background: rgba(4, 157, 217, 0.08); border: 1px dashed rgba(4, 157, 217, 0.35); border-radius: 10px; padding: 12px 16px; margin-bottom: 1.25rem;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" wire:model="params.round_two_decimals" style="width: 16px; height: 16px; border-radius: 4px; accent-color: #049DD9;" />
                        <span style="font-size: 0.825rem; font-weight: 700; color: #38BDF8;">
                            Redondear precios finales a 2 decimales (Recomendado para retail)
                        </span>
                    </label>
                    <p style="font-size: 0.75rem; color: #7DD3FC; margin: 4px 0 0 24px;">
                        ⚡ <strong>Sincronización Total:</strong> Cada artículo modificado generará automáticamente su réplica en <strong>TR_PEND_PRODUCTOS</strong> para que las cajas registradoras tomen los nuevos precios sin reiniciar.
                    </p>
                </div>

            @elseif($activeOperationId === 'delete_anticipo')
                <div class="ops-grid-2" style="margin-bottom: 1.25rem;">
                    <div>
                        <label class="ops-label">Número de Documento / Recibo de Anticipo *</label>
                        <input type="text" wire:model="params.documento" placeholder="Ej. ANT-000456 o REC-1029" class="ops-input" />
                        <span style="font-size: 0.7rem; color: #94A3B8;">Se buscarán registros colgados en MA_CXC_DETPAG.</span>
                    </div>
                </div>
            @endif

            <!-- Botón de Simulación Dry-Run -->
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button 
                    wire:click="runPreview" 
                    wire:loading.attr="disabled" 
                    class="ops-btn-primary">
                    <span wire:loading.remove wire:target="runPreview">🔍 Analizar & Simular (Dry-Run)</span>
                    <span wire:loading wire:target="runPreview">Inspeccionando SQL Server...</span>
                </button>
            </div>
        </div>

        <!-- Alerta de No Encontrado -->
        @if(!empty($previewResult) && !($previewResult['found'] ?? false))
            <div style="background: rgba(234, 88, 12, 0.1); border: 1px solid rgba(234, 88, 12, 0.35); border-radius: 12px; padding: 14px 18px; color: #FB923C; margin-bottom: 0.5rem;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <x-heroicon-o-exclamation-triangle style="width: 20px; height: 20px; color: #F97316; flex-shrink: 0;" />
                    <strong style="font-size: 0.95rem; color: #FDBA74;">No se encontraron registros en SQL Server</strong>
                </div>
                <p style="margin: 4px 0 0 28px; font-size: 0.85rem; color: #E2E8F0;">
                    {{ $previewResult['message'] ?? 'No se hallaron coincidencias para los filtros especificados en la sucursal seleccionada.' }}
                </p>
            </div>
        @endif

        <!-- Tarjeta de Resultados de Simulación / Dry-Run (Solo si se simuló) -->
        @if(!empty($previewResult) && ($previewResult['found'] ?? false))
            <div class="ops-card" style="border-left: 4px solid #049DD9;">
                <div class="ops-card-top-bar" style="background: linear-gradient(90deg, #049DD9, #10B981);"></div>
                
                <div class="ops-card-header">
                    <div class="ops-card-icon" style="background: rgba(16, 185, 129, 0.15); color: #10B981;">
                        <x-heroicon-o-shield-check style="width: 20px; height: 20px;" />
                    </div>
                    <div>
                        <h3 class="ops-card-title">Resultados de la Simulación (Fase 1: Solo Lectura)</h3>
                        <p style="font-size: 0.8rem; color: #94A3B8; margin: 0;">Verifica cuidadosamente los datos inspeccionados antes de proceder a la ejecución definitiva.</p>
                    </div>
                </div>

                <!-- Advertencias Detectadas -->
                @if(!empty($previewResult['warnings']))
                    <div style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 1.25rem;">
                        @foreach($previewResult['warnings'] as $warn)
                            <div style="padding: 10px 14px; border-radius: 8px; background: rgba(234, 88, 12, 0.12); border: 1px solid rgba(234, 88, 12, 0.35); color: #FB923C; font-size: 0.8rem; font-weight: 600;">
                                {{ $warn }}
                            </div>
                        @endforeach
                    </div>
                @endif

                <!-- Detalle específico para Borrado de Compras -->
                @if($activeOperationId === 'delete_purchase')
                    <div class="ops-grid-3" style="margin-bottom: 1.25rem;">
                        <div class="ops-stat-box">
                            <span class="ops-stat-label">Proveedor</span>
                            <div class="ops-stat-value">{{ $previewResult['header']['proveedor'] }}</div>
                        </div>
                        <div class="ops-stat-box">
                            <span class="ops-stat-label">Monto Total de Compra</span>
                            <div class="ops-stat-value" style="color: #10B981;">${{ number_format($previewResult['header']['total'], 2) }}</div>
                        </div>
                        <div class="ops-stat-box">
                            <span class="ops-stat-label">Renglones TR_COMPRAS / TR_INV</span>
                            <div class="ops-stat-value" style="color: #38BDF8;">{{ $previewResult['items_count'] }} ítems / {{ $previewResult['inventory_movements_count'] }} movs</div>
                        </div>
                    </div>

                    @if(!empty($previewResult['items']))
                        <div class="ops-table-container" style="margin-bottom: 1.5rem; max-height: 240px; overflow-y: auto;">
                            <table class="ops-table">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Descripción</th>
                                        <th>Cantidad</th>
                                        <th>Costo Unitario</th>
                                        <th>Total</th>
                                        <th>Depósito</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($previewResult['items'] as $it)
                                        <tr>
                                            <td style="font-weight: 700; font-family: monospace;">{{ $it['c_CODARTICULO'] }}</td>
                                            <td>{{ $it['c_descripcion'] }}</td>
                                            <td style="font-weight: 800; color: #38BDF8;">{{ number_format($it['n_CANTIDAD'], 2) }}</td>
                                            <td>${{ number_format($it['n_COSTO'], 4) }}</td>
                                            <td style="font-weight: 700;">${{ number_format($it['n_total'], 2) }}</td>
                                            <td><span class="ops-badge ops-badge-cyan">{{ $it['C_DEPOSITO'] }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                <!-- Detalle específico para Ajuste en TR_INVENTARIO -->
                @elseif($activeOperationId === 'manual_kardex_adjustment')
                    <div class="ops-grid-3" style="margin-bottom: 1.25rem;">
                        <div class="ops-stat-box">
                            <span class="ops-stat-label">Producto / Código</span>
                            <div class="ops-stat-value">{{ $previewResult['producto']['codigo'] }}</div>
                            <span style="font-size: 0.75rem; color: #94A3B8;">{{ $previewResult['producto']['descripcion'] }}</span>
                        </div>
                        <div class="ops-stat-box">
                            <span class="ops-stat-label">Saldo Actual MA_DEPOPROD</span>
                            <div class="ops-stat-value">{{ number_format($previewResult['producto']['saldo_actual_depoprod'], 2) }} unid.</div>
                        </div>
                        <div class="ops-stat-box">
                            <span class="ops-stat-label">Saldo Proyectado Post-DepoProd</span>
                            <div class="ops-stat-value" style="color: #10B981;">
                                {{ number_format($previewResult['producto']['saldo_proyectado_post_agente'], 2) }} unid. ({{ $previewResult['producto']['ajuste_propuesto'] }})
                            </div>
                        </div>
                    </div>

                    @if(!empty($previewResult['recent_kardex']))
                        <div style="font-size: 0.8rem; font-weight: 700; margin-bottom: 6px; color: #94A3B8;">Últimos Movimientos en TR_INVENTARIO:</div>
                        <div class="ops-table-container" style="margin-bottom: 1.5rem; max-height: 200px; overflow-y: auto;">
                            <table class="ops-table">
                                <thead>
                                    <tr>
                                        <th>Documento</th>
                                        <th>Concepto</th>
                                        <th>Tipo</th>
                                        <th>Cantidad</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($previewResult['recent_kardex'] as $km)
                                        <tr>
                                            <td style="font-family: monospace;">{{ $km['c_Documento'] }}</td>
                                            <td><span class="ops-badge ops-badge-cyan">{{ $km['c_Concepto'] }}</span></td>
                                            <td>{{ $km['c_TipoMov'] }}</td>
                                            <td style="font-weight: 700;">{{ number_format($km['n_Cantidad'], 2) }}</td>
                                            <td style="color: #94A3B8;">{{ substr($km['f_Fecha'] ?? '', 0, 10) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                <!-- Detalle específico para Diagnóstico TR_INVENTARIO vs MA_DEPOPROD -->
                @elseif($activeOperationId === 'kardex_reconciliation_audit')
                    <div class="ops-stat-box" style="margin-bottom: 1.25rem;">
                        <span class="ops-stat-label">Producto Evaluado</span>
                        <div class="ops-stat-value">{{ $previewResult['producto']['codigo'] }} - {{ $previewResult['producto']['descripcion'] }}</div>
                    </div>

                    <div class="ops-table-container" style="margin-bottom: 1.5rem;">
                        <table class="ops-table">
                            <thead>
                                <tr>
                                    <th>Depósito</th>
                                    <th>Saldo MA_DEPOPROD</th>
                                    <th>Historial TR_INVENTARIO</th>
                                    <th>Descuadre</th>
                                    <th>Movs Kardex</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($previewResult['comparison'] as $cmp)
                                    <tr>
                                        <td style="font-weight: 700;"><span class="ops-badge ops-badge-cyan">{{ $cmp['deposito'] }}</span></td>
                                        <td>{{ number_format($cmp['saldo_consolidado_depoprod'], 2) }}</td>
                                        <td>{{ number_format($cmp['saldo_calculado_kardex'], 2) }}</td>
                                        <td style="font-weight: 800; color: {{ abs($cmp['descuadre']) > 0.001 ? '#EF4444' : '#10B981' }};">
                                            {{ ($cmp['descuadre'] > 0 ? '+' : '') }}{{ number_format($cmp['descuadre'], 2) }}
                                        </td>
                                        <td>{{ $cmp['movimientos_kardex'] }}</td>
                                        <td>
                                            <span class="ops-badge {{ $cmp['estado'] === 'CUADRADO' ? 'ops-badge-success' : 'ops-badge-danger' }}">
                                                {{ $cmp['estado'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                <!-- Detalle específico para Cambios Masivos de Productos -->
                @elseif($activeOperationId === 'bulk_product_update')
                    <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.3); padding: 14px 18px; border-radius: 10px; margin-bottom: 1.25rem;">
                        <strong style="color: #34D399; font-size: 1rem;">📦 Total de Productos que se Actualizarán: {{ number_format($previewResult['total_affected']) }}</strong>
                        <p style="color: #10B981; font-size: 0.8rem; margin: 4px 0 0 0;">
                            Se previsualizan a continuación los primeros 25 artículos con sus valores antes vs. simulados después de aplicar la regla.
                        </p>
                    </div>

                    @if(!empty($previewResult['sample_rows']))
                        <div class="ops-table-container" style="margin-bottom: 1.5rem; max-height: 280px; overflow-y: auto;">
                            <table class="ops-table">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Descripción</th>
                                        <th>Dpto</th>
                                        <th>Marca</th>
                                        <th>Precio Actual</th>
                                        <th>Precio Simulado</th>
                                        <th>Variación</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($previewResult['sample_rows'] as $row)
                                        <tr>
                                            <td style="font-weight: 700; font-family: monospace;">{{ $row['codigo'] }}</td>
                                            <td>{{ $row['descripcion'] }}</td>
                                            <td>{{ $row['departamento'] }}</td>
                                            <td>{{ $row['marca'] }}</td>
                                            <td>${{ number_format($row['actual_p1'], 2) }}</td>
                                            <td style="font-weight: 800; color: #10B981;">${{ number_format($row['simulado_p1'], 2) }}</td>
                                            <td>
                                                <span class="ops-badge {{ ($row['diferencia'] ?? 0) >= 0 ? 'ops-badge-success' : 'ops-badge-danger' }}">
                                                    {{ ($row['diferencia'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($row['diferencia'] ?? 0, 2) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                @elseif($activeOperationId === 'delete_anticipo')
                    <div style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.3); padding: 14px 18px; border-radius: 10px; margin-bottom: 1.25rem;">
                        <strong style="color: #FBBF24;">💳 Anticipo Encontrado: {{ $previewResult['records_count'] }} registros en MA_CXC_DETPAG</strong>
                        <div style="font-size: 1.1rem; font-weight: 800; color: #F59E0B; margin-top: 4px;">
                            Monto a Liberar: ${{ number_format($previewResult['total_monto'], 2) }}
                        </div>
                    </div>
                @endif

                <!-- Sección de Confirmación y Ejecución Definitiva -->
                @if($previewResult['can_execute'] ?? false)
                    <div style="border-top: 2px dashed rgba(255, 255, 255, 0.1); padding-top: 1.25rem;">
                        <div class="ops-grid-2" style="margin-bottom: 1rem;">
                            <div>
                                <label class="ops-label">Número de Ticket de Soporte / Incidencia</label>
                                <input type="text" wire:model="ticketNumber" placeholder="Ej. TICKET-9921 o REQ-SOPORTE" class="ops-input" />
                            </div>
                            <div>
                                <label class="ops-label">Motivo de la Intervención Técnica</label>
                                <input type="text" wire:model="operatorNotes" placeholder="Ej. Corrección autorizada por gerencia técnica" class="ops-input" />
                            </div>
                        </div>

                        <div style="display: flex; justify-content: flex-end; gap: 12px;">
                            <button 
                                wire:click="runExecution" 
                                wire:loading.attr="disabled" 
                                onclick="return confirm('⚠️ ATENCIÓN: Esta acción modificará registros en la base de datos de producción de Stellar ERP bajo transacción segura. ¿Deseas proceder?');"
                                class="ops-btn-danger" 
                                style="font-size: 0.95rem; padding: 0.8rem 1.6rem;">
                                <span wire:loading.remove wire:target="runExecution">⚡ Ejecutar Proceso Definitivo (SQL Transaccional)</span>
                                <span wire:loading wire:target="runExecution">Aplicando cambios en SQL Server...</span>
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <!-- Banner de Ejecución Exitosa -->
        @if(!empty($executionResult) && ($executionResult['success'] ?? false))
            <div style="background: rgba(16, 185, 129, 0.1); border: 2px solid #10B981; border-radius: 14px; padding: 1.5rem; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.2);">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                    <span style="font-size: 1.5rem;">✅</span>
                    <h3 style="margin: 0; color: #34D399; font-size: 1.15rem; font-weight: 800;">
                        Operación Técnica Finalizada Exitosamente
                    </h3>
                </div>
                <p style="font-size: 0.875rem; color: #E2E8F0; margin: 0 0 12px 0; line-height: 1.5;">
                    {{ $executionResult['message'] ?? 'Los cambios fueron aplicados y quedaron auditados en la bitácora inmutable.' }}
                </p>
                <div style="display: flex; gap: 10px;">
                    <a href="/admin/audit-log-viewers" class="ops-btn-primary" style="font-size: 0.775rem; padding: 6px 12px; text-decoration: none;">
                        📋 Ver Entrada en Bitácora de Auditoría
                    </a>
                </div>
            </div>
        @endif

    </div>

    <!-- Estilos CSS con tokens de diseño modernos y Dark Mode First -->
    <style>
        :root {
            --ops-card-bg: #FFFFFF;
            --ops-card-border: #E2E8F0;
            --ops-text-main: #0F172A;
            --ops-text-muted: #64748B;
            --ops-input-bg: #F8FAFC;
            --ops-input-border: #CBD5E1;
            --ops-stat-bg: #F8FAFC;
            --ops-stat-border: #E2E8F0;
            --ops-table-border: #E2E8F0;
            --ops-table-th-bg: #F1F5F9;
        }

        .dark {
            --ops-card-bg: #0F172A;
            --ops-card-border: rgba(255, 255, 255, 0.08);
            --ops-text-main: #F8FAFC;
            --ops-text-muted: #94A3B8;
            --ops-input-bg: #1E293B;
            --ops-input-border: rgba(255, 255, 255, 0.12);
            --ops-stat-bg: #1E293B;
            --ops-stat-border: rgba(255, 255, 255, 0.08);
            --ops-table-border: rgba(255, 255, 255, 0.08);
            --ops-table-th-bg: #1E293B;
        }

        .ops-card {
            position: relative;
            background: var(--ops-card-bg);
            border: 1px solid var(--ops-card-border);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .ops-card-top-bar {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #049DD9, #2E62A6);
        }

        .ops-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 1.25rem;
        }

        .ops-card-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(4, 157, 217, 0.12);
            color: #049DD9;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .ops-card-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--ops-text-main);
            margin: 0;
            letter-spacing: -0.01em;
        }

        .ops-tab-card {
            cursor: pointer;
            border-radius: 14px;
            padding: 1.25rem;
            border: 2px solid var(--ops-card-border);
            background: var(--ops-card-bg);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            transition: all 0.2s ease;
        }

        .ops-tab-card:hover {
            border-color: #049DD9;
            transform: translateY(-2px);
        }

        .ops-tab-card.active {
            border-color: #049DD9;
            background: rgba(4, 157, 217, 0.06);
            box-shadow: 0 4px 14px rgba(4, 157, 217, 0.2);
        }

        .ops-label {
            display: block;
            font-size: 0.775rem;
            font-weight: 700;
            color: var(--ops-text-main);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .ops-input, .ops-select {
            width: 100%;
            background: var(--ops-input-bg);
            border: 1px solid var(--ops-input-border);
            color: var(--ops-text-main);
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 0.875rem;
            transition: all 0.2s;
            outline: none;
        }

        .ops-input:focus, .ops-select:focus {
            border-color: #049DD9;
            box-shadow: 0 0 0 2px rgba(4, 157, 217, 0.2);
        }

        .ops-grid-2 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
        }

        .ops-grid-3 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
        }

        .ops-stat-box {
            background: var(--ops-stat-bg);
            padding: 14px;
            border-radius: 10px;
            border: 1px solid var(--ops-stat-border);
        }

        .ops-stat-label {
            font-size: 0.7rem;
            color: var(--ops-text-muted);
            font-weight: 700;
            text-transform: uppercase;
            display: block;
            margin-bottom: 4px;
        }

        .ops-stat-value {
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--ops-text-main);
        }

        .ops-table-container {
            border: 1px solid var(--ops-table-border);
            border-radius: 10px;
            overflow: hidden;
        }

        .ops-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
            text-align: left;
        }

        .ops-table th {
            background: var(--ops-table-th-bg);
            color: var(--ops-text-muted);
            padding: 10px 14px;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--ops-table-border);
        }

        .ops-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--ops-table-border);
            color: var(--ops-text-main);
        }

        .ops-badge {
            display: inline-block;
            font-size: 0.7rem;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 6px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .ops-badge-cyan {
            background: rgba(4, 157, 217, 0.15);
            color: #049DD9;
        }

        .ops-badge-success {
            background: rgba(16, 185, 129, 0.15);
            color: #10B981;
        }

        .ops-badge-danger {
            background: rgba(239, 68, 68, 0.15);
            color: #EF4444;
        }

        .ops-btn-primary {
            background: linear-gradient(135deg, #049DD9, #2E62A6);
            color: #FFFFFF;
            border: none;
            border-radius: 10px;
            padding: 0.7rem 1.4rem;
            font-size: 0.875rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(4, 157, 217, 0.25);
            transition: all 0.2s;
        }

        .ops-btn-primary:hover {
            filter: brightness(1.1);
            transform: translateY(-1px);
        }

        .ops-btn-danger {
            background: linear-gradient(135deg, #DC2626, #991B1B);
            color: #FFFFFF;
            border: none;
            border-radius: 10px;
            padding: 0.7rem 1.4rem;
            font-size: 0.875rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
            transition: all 0.2s;
        }

        .ops-btn-danger:hover {
            filter: brightness(1.1);
            transform: translateY(-1px);
        }
    </style>
</x-filament-panels::page>
