# 🛠️ Stellar Ops Hub — Consola de Operaciones y Soporte ERP

Plataforma web interna para el equipo de soporte técnico de **Leandatech / Stellar ERP**. Permite ejecutar correcciones críticas sobre las bases de datos de clientes (VAD10 / VAD20) de forma segura, transaccional, con trazabilidad completa y sin necesidad de ejecutar queries manuales por SQL Server Management Studio.

---

## 🚀 Acceso Rápido al Panel

* **URL Local:** [http://127.0.0.1:8095/admin](http://127.0.0.1:8095/admin)
* **Usuario:** `soporte@leandatech.com`
* **Contraseña:** `admin1234`
* **Lanzador Directo:** Haz doble clic en `INICIAR_OPS_HUB.bat`

---

## 📦 Módulos Incluidos (Fase 1)

### 1. 🏷️ Corrector de Productos & Sincronización `TR_PEND`
* **Ruta:** `/admin/product-fixer`
* **Problema que resuelve:** Cuando se ajusta un precio, descripción o departamento en `MA_PRODUCTOS`, las cajas registradoras no se enteran y siguen cobrando el precio viejo.
* **Solución:** Al guardar cualquier cambio, el sistema ejecuta un `UPDATE` transaccional en `MA_PRODUCTOS` e inserta automáticamente la réplica en la tabla **`TR_PEND_PRODUCTOS`**, permitiendo que el motor de réplica de Stellar distribuya inmediatamente los cambios a las cajas POS y demás sucursales.

### 2. 💰 Gestor de Anticipos CXC (`MA_CXC`)
* **Ruta:** `/admin/anticipo-manager`
* **Problema que resuelve:** Anticipos bloqueados o erróneos que traban clientes o cuentas por cobrar.
* **Solución:**
  * Búsqueda por Nro. de Documento o Cédula/RIF de Cliente.
  * Análisis de dependencias: detecta en tiempo real si el anticipo ya fue cruzado en **`MA_CXC_DETPAG`** o si tiene saldo amortizado (`n_Pagado > 0`).
  * Asistente de eliminación segura con registro obligatorio del Nro. de Ticket de soporte.

### 3. 📋 Bitácora de Auditoría (`support_audit_logs`)
* **Ruta:** `/admin/audit-log-viewer`
* Registro inmutable de cada acción ejecutada:
  * Fecha, hora e IP del operador.
  * Cliente y sucursal afectada.
  * Número de ticket de soporte y justificación.
  * Captura del estado del registro **antes** y **después** (`before_state` / `after_state`).

---

## 🏗️ Arquitectura y Escalabilidad (Playbook Pattern)

Cada corrección es un **Playbook** independiente ubicado en `app/Services/Playbooks/`:
1. `ProductSyncPlaybook.php`: Lógica de actualización y réplica a `TR_PEND_PRODUCTOS`.
2. `AnticipoFixPlaybook.php`: Lógica de validación de cruces y reversión de anticipos.

Para agregar una nueva corrección (ej. desbloqueo de cajas, corrección de inventario o ajuste de saldos), solo se crea un nuevo archivo en `app/Services/Playbooks/` y su vista correspondiente en `app/Filament/Pages/`.

---

## 🔌 Conexiones Multi-Cliente / Multi-Sucursal

Configurado en `config/stellar.php`:
* Soporta conexiones ODBC directas a **ODBC Driver 18 for SQL Server** en Windows.
* Configuración lista para **COISA HOGAR** (4 sucursales vía Radmin VPN) y **LOCAL_DEV** (instancia local `DESKTOP-6B0Q8VN\SOPORTE`).