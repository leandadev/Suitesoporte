# 🌟 STELLAR OPS HUB & CORE APIS — DOCUMENTO MAESTRO DE CONTINUIDAD
> **Fecha de Emisión**: 26 de Octubre de 2026  
> **Servidor Host**: `DESKTOP-6B0Q8VN` (Windows 11 Pro)  
> **Directorio Base**: `C:\ProjectsDEV\APISTELLAR\`  
> **Repositorio Hub**: `C:\ProjectsDEV\APISTELLAR\stellar-ops-hub`  
> **Estado del Sistema**: Activo en puerto local `8095` (`http://127.0.0.1:8095/admin/technical-operations-hub`)

---

## 📌 1. RESUMEN EJECUTIVO Y VISIÓN DEL PRODUCTO

**Stellar Ops Hub (Centro de Operaciones Técnicas)** evoluciona de un portal de soporte administrativo puntual a una plataforma web integral para el ecosistema retail de Saint / Stellar. 

El producto se comercializa y despliega en **3 versiones progresivas**:

```
+---------------------------------------------------------------------------------+
|                                STELLAR OPS HUB                                  |
+---------------------------------------------------------------------------------+
| 1. CORE (Operaciones Técnicas)                                                  |
|    - Playbooks de rescate DB: Eliminación segura de compras/anticipos           |
|    - Ajuste de Kardex vía TR_INVENTARIO (respetando Agente DepoProd)            |
|    - Actualización masiva de productos (costos, precios, departamentos)         |
|    - Auditoría criptográfica de acciones técnicas (SupportAuditLog)             |
+---------------------------------------------------------------------------------+
| 2. PRO-BI (Reportes & Analítica Consolidada)                                    |
|    - Suite de Cuentas por Pagar (CxP) con balance estricto y semáforo de mora   |
|    - Explorador Multi-Sucursal de Facturación (POS + Food + Business)           |
|    - CRM Customer 360 (perfil de compra, saldo pendiente, frecuencia)           |
|    - Trazabilidad de transferencias de mercancía (TRS/TRA pareadas)             |
+---------------------------------------------------------------------------------+
| 3. ENTERPRISE (Integridad & Conciliación Tienda vs Central)                     |
|    - Asistente de 3 pasos: Test Conexión -> Confirmar Sync -> Auditoría Read-Only |
|    - Comparativa en 8 módulos críticos (Ventas, POS, Cobros, Compras, Kardex...) |
|    - Exportador de faltantes en CSV y sincronización atómica ("todo o nada")     |
|    - Tolerancia a fallos por Jobs en segundo plano con bloqueo por sucursal     |
+---------------------------------------------------------------------------------+
```

---

## 🛰️ 2. TRACK 1: MEJORAS CRÍTICAS EN STELLAR RETAIL CORE APIS

Para alimentar la capa de reportes y conciliación, las APIs clientes (`stellar-api-*`) requieren **6 mejoras 100% aditivas y retrocompatibles** (sin alterar contratos existentes):

### 📋 Matriz de Estado y Rutas de APIs
| Instancia API | Directorio en Servidor | Dominio Público / Puerto | Rutas Actuales | Rutas Objetivo |
| :--- | :--- | :--- | :--- | :--- |
| **Ferrehogar** | `stellar-api-ferrehogar` | `https://ferrehogar.leandatech.com` (:8006) | 26 rutas (100% completas) | 27 rutas (+ transferencias) |
| **Multicarnes** | `stellar-api-multicarnes` | `https://multicarnes.leandatech.com` | 26 rutas (Radmin VPN Suc 01 y 02) | 27 rutas (+ multi-sucursal ALL) |
| **LM Market** | `stellar-api-lmmarket` | `https://glmm.leandatech.com` | **13 rutas** (Faltan reportes) | 27 rutas (Igualar Ferrehogar) |
| **COISA Hogar** | `stellar-api-coisahogar` | Puerto local :8007 | 26 rutas | 27 rutas |
| **Hiper Solidario** | `stellar-api-hypersolida` | Instancia dedicada | 26 rutas | 27 rutas |

---

### 🔧 Detalle de las 6 Mejoras Aditivas

#### 1. [CRÍTICO – LM Market] Habilitación de Endpoints Faltantes
* **Problema**: La instancia LM (`glmm.leandatech.com`) responde 404 en todas las rutas de reportes.
* **Solución**: Replicar e integrar los routers y servicios de `stellar-api-ferrehogar`:
  * `GET /api/ventas` y `GET /api/ventas/{id}`
  * `GET /api/compras` y `GET /api/compras/{id}`
  * `GET /api/inventario/movimientos`
  * `GET /api/proveedores` y `GET /api/clientes`
  * `GET /api/cajas` y `GET /api/cajas/turnos`
* **Contrato**: Mantener paginación idéntica (`results`, `totalPages`, `totalElements`, `limit`, `page`).

#### 2. [LM Market] Inclusión de la 4ta Sucursal (Almacén Central / Mayorista)
* **Descubrimiento SQL**:
  * Tabla `MA_SUCURSALES`: Código `'00'` (*"CENTRAL LM MARKET"*).
  * Tabla `MA_DEPOSITO`: Código `'0001'` (*"ALMACEN CENTRAL."*, ligado a `c_codlocalidad = '00'`).
* **Acción**: Incorporar la sucursal `00` al diccionario `BRANCH_CONFIGS` en `config.py` y exponerla en `GET /api/sucursales`.

#### 3. [GLOBAL] Soporte Consolidado Multi-Sucursal (`ALL` o comas)
* **Comportamiento**: Si `sucursal` se omite, o es `ALL`, o es `01,02`:
  * En APIs multi-instancia VPN (como Multicarnes): Consultar sucursales concurrentemente vía `concurrent.futures.ThreadPoolExecutor`, unificar resultados, ordenar cronológicamente y paginar.
  * En base de datos unificada: Omitir la cláusula `WHERE c_codlocalidad = ...`.
  * **Regla estricta**: Cada fila retornada debe conservar obligatoriamente el atributo `"sucursal": "01"`.

#### 4. [GLOBAL] Datos de CxP y Vencimiento en `/api/compras`
* **Enriquecimiento SQL**: Realizar `LEFT JOIN MA_CXP CXP ON CXP.C_Codigo = C.c_CODPROVEEDOR AND CXP.C_Documento = C.c_DOCUMENTO`.
* **Nuevos Campos**:
  * `fechaFactura`: `CXP.f_fechae`
  * `fechaRegistro`: `C.f_fecha`
  * `fechaVencimiento`: `CXP.f_fechav`
  * `diasCredito`: `DATEDIFF(day, CXP.f_fechae, CXP.f_fechav)`
  * `saldoPendiente`: `ROUND(CXP.n_total - CXP.n_Pagado, 2)`
  * `estadoPago`: `'PAGADA'` si saldo <= 0, `'PARCIAL'` si saldo < total, `'PENDIENTE'` si saldo == total.
  * `fechaUltimoPago`: `CXP.F_CANCELACION`
* **Filtros Nuevos**: `?pendientes=true`, `?venceDesde=YYYY-MM-DD`, `?venceHasta=YYYY-MM-DD`.

#### 5. [GLOBAL] Endpoint Dedicado de Transferencias entre Depósitos
* **Nuevo Endpoint**: `GET /api/inventario/transferencias`
* **Lógica**: Agrupar movimientos de `TR_INVENTARIO` por documento padre en `MA_INVENTARIO` donde `c_CONCEPTO IN ('TRA', 'TRS')`. Deduplicar el par Cargo/Descargo para presentar una sola entidad de transferencia con:
  * `documento`, `fecha`, `hora`, `sucursalOrigen`, `depositoOrigen`, `sucursalDestino`, `depositoDestino`, `estado`, `usuario`, `lineas[]`.
* **En `/api/inventario/movimientos`**: Añadir sucursal/depósito de origen y destino para renglones TRS/TRA.

#### 6. [GLOBAL] Registro y Filtrado de Sucursal en Carritos Activos
* En `GET /api/carts/ActiveCarts`: Aceptar filtro `?sucursal=`.
* En `save_cart`: Registrar la clave de sucursal en el documento/objeto JSON para separar pedidos de pickup por tienda.

---

## 📊 3. TRACK 2: INTEGRACIÓN DE LAS MINUTAS AL STELLAR OPS HUB

A partir de los documentos analizados en `scratch/minuta-1.md` y `scratch/minuta-2.md`, se define la arquitectura para las vistas y servicios en **Filament v5**:

```
stellar-ops-hub/
├── app/
│   ├── Filament/
│   │   └── Pages/
│   │       ├── TechnicalOperationsHub.php   <-- [EXISTENTE] Core Ops Playbooks
│   │       ├── AnticipoManager.php          <-- [EXISTENTE] Rescate de Anticipos
│   │       ├── ProductFixer.php             <-- [EXISTENTE] Corrector de Productos
│   │       ├── AuditLogViewer.php           <-- [EXISTENTE] Trazabilidad y Logs
│   │       ├── Reports/
│   │       │   ├── CuentasPorPagarPage.php   <-- [MINUTA 1] Suite CxP y Aging
│   │       │   ├── FacturacionConsolidada.php<-- [MINUTA 1] Multi-Sucursal POS+Food+Admin
│   │       │   └── TransferenciasKardex.php <-- [MINUTA 1] Tracking de Mercancía
│   │       └── Reconciliation/
│   │           └── TiendaCentralWizard.php  <-- [MINUTA 2] Conciliador 3 Pasos
│   └── Support/
│       └── Operations/
│           ├── Contracts/TechnicalOperationInterface.php
│           └── Playbooks/
│               ├── DeletePurchaseOperation.php
│               ├── ManualKardexAdjustmentOperation.php
│               ├── BulkProductUpdateOperation.php
│               └── DeleteAnticipoOperation.php
```

### 📑 A. Minuta 1: Suite de Cuentas por Pagar (CxP) y Facturación Multi-Sucursal

#### 1. Fórmula Matemática Inquebrantable de CxP
Para garantizar la exactitud contable en los dashboards:
$$\text{Saldo Inicial} + \text{Facturas del Período} - \text{Abonos y Retenciones} = \text{Saldo Final}$$

#### 2. Componentes de la Vista CxP:
* **Tarjetas KPI**: Deuda Total ($ / Bs), Deuda Vencida (Alerta Roja), Vence Hoy/Próximos 7 días (Alerta Amarilla), Saldo Corriente (Verde).
* **Matriz de Envejecimiento (Aging)**:
  * Corriente (0 días)
  * 1 a 15 días de vencido
  * 16 a 30 días de vencido
  * Más de 30 días (Crítico / Bloqueo de proveedor)
* **Drawer de Detalle por Factura**:
  * Documento original, número de control.
  * Desglose de Retención IVA y Retención ISLR.
  * Historial de abonos con fecha, banco y número de referencia.
  * Anticipos disponibles a cruzar.
* **Generador de Comprobantes**: Exportación de recibos de pago en PDF/Excel formateados para entrega a proveedores.

#### 3. Explorador de Facturación Consolidada:
* Integración de múltiples canales: **POS Caja Retail**, **Saint Food / Restaurante**, y **Administrativo / Facturación Libre**.
* **Desglose Impositivo**:
  * Base Imponible
  * Ventas Exentas
  * Débito Fiscal IVA (16% / 8%)
  * Percepción IGTF (3%)
* **Customer 360 CRM**: Historial de consumo por cliente, clasificación automática (Frecuente, Ocasional, Inactivo >60 días), saldo pendiente a crédito y límite de crédito.

---

### 🔄 B. Minuta 2: Conciliador Tienda contra Central ("Auditor de Replicación")

**Lema Operativo**: *"Todo lo que pasó en tu tienda llegó completo al total"*.

#### 1. Flujo en 3 Pasos (Wizard Visual)
```
[ PASO 1: TEST CONEXIÓN ] ---> [ PASO 2: ESTADO SYNC ] ---> [ PASO 3: AUDITORÍA COMPARATIVA ]
Ping & Latencia a DB           Servicios Saint Sync         Cálculo de deltas (Solo Lectura)
Tienda Local vs Central        activos y sin cola parada     Conteo de documentos y montos
```

#### 2. Cobertura en 8 Módulos Críticos
1. **Ventas Totales**: Conteo de tickets y monto bruto.
2. **Facturación Fiscal / POS**: Rango de números fiscales consecutivos emitidos vs recibidos.
3. **Devoluciones y Notas de Crédito**: Documentos `DEV` procesados.
4. **Cobranzas y Medios de Pago**: Total por punto de venta, divisas en efectivo, Zelle, biopago.
5. **Compras a Proveedores**: Facturas recepcionadas en tienda vs centralizadas.
6. **Pagos y Egresos CxP**: Recibos y pagos de caja chica.
7. **Kardex y Transferencias**: Validación de que cada `TRS` (salida) tenga su `TRA` (entrada) idéntica.
8. **Cierres de Caja (Reportes Z)**: Zetas de impresoras fiscales cuadradas con la base de datos central.

#### 3. Salvaguardas de Integridad y Recuperación
* **Modo Estrictamente Solo Lectura en la Auditoría**: La comparación no altera ningún registro.
* **Exportador CSV de Faltantes**: Generación de listado de IDs y números de control faltantes para auditoría física.
* **Inserción Atómica "Todo o Nada"**: Si se ordena la resincronización de un lote, se ejecuta dentro de un `DB::beginTransaction()` de SQL Server; si falla un renglón, se revierte por completo.
* **Ejecución en Background**: Procesamiento tolerante a caídas de internet mediante colas de Laravel (`jobs`), evitando timeouts de HTTP.
* **Lock de Concurrencia**: Bloqueo por sucursal (`Cache::lock("reconcile_branch_{$id}", 300)`) para evitar que dos operadores concilien la misma tienda al mismo tiempo.

---

## 🖥️ 4. TRACK 3: INFRAESTRUCTURA, SERVICIOS Y ENTORNO ACTIVO

### Variables de Sistema y Rutas Absolutas
* **Host Físico**: `DESKTOP-6B0Q8VN`
* **SQL Server**: Instancia `DESKTOP-6B0Q8VN\SOPORTE`
* **Python Runtime**: `C:\ProjectsDEV\APISTELLAR\stellar-retail-analytics\backend\.venv\Scripts\python.exe`
* **PHP / Composer**: PHP 8.2+ con extensiones `pdo_sqlsrv`, `sqlsrv`, `curl`, `mbstring`.

### Servicios en Ejecución
* **Stellar Ops Hub**: `http://127.0.0.1:8095` (Proceso PHP PID 38008).
* **Grandstream Portal Cautivo**: Puerto `8000` (PID 39164).
* **Ferrehogar Core API**: Puerto `8006` (PID 14672).
* **COISA Hogar API**: Puerto `8007` (PID 46112).
* **Cloudflare Tunnel Daemon**: PID 21544.

---

## 🚀 5. PLAN DE ACCIÓN INMEDIATO PARA LA PRÓXIMA SESIÓN

Al retomar el proyecto, ejecutar las tareas en el siguiente orden secuencial:

```
FASE 1: CORE APIS (Backend Python)
  ├── 1.1 Inyectar rutas y servicios de reportes en `stellar-api-lmmarket`.
  ├── 1.2 Incorporar sucursal '00' en `stellar-api-lmmarket/config.py`.
  ├── 1.3 Implementar consulta concurrente 'ALL' en `stellar-api-multicarnes`.
  ├── 1.4 Agregar join con `MA_CXP` en `purchases_service.py` en todas las APIs.
  ├── 1.5 Crear endpoint `GET /api/inventario/transferencias`.
  └── 1.6 Regenerar `openapi.json` de cada instancia.

FASE 2: STELLAR OPS HUB (Frontend Filament v5)
  ├── 2.1 Crear grupo de navegación "Reportes & Analítica":
  │     ├── Implementar `CuentasPorPagarPage` (Cards balance, Aging, Filtros).
  │     └── Implementar `FacturacionConsolidadaPage` (Canales POS/Food/Admin, IVA/IGTF).
  └── 2.2 Crear grupo de navegación "Integridad & Conciliación":
        └── Implementar `TiendaCentralWizard` (Wizard 3 pasos, comparador 8 módulos, export CSV).
```

---
*Este documento maestro asegura continuidad técnica total y cero pérdida de contexto.*
