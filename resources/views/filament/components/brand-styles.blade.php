<style>
    /* =====================================================================
     * SISTEMA DE DISEÑO CORPORATIVO: LEANDATECH SOLUTION
     * Paleta Oficial: #2E62A6 | #3270A6 | #049DD9 | #79D0F2 | #F2F2F2
     * ===================================================================== */

    :root {
        --leanda-navy: #2E62A6;
        --leanda-blue: #3270A6;
        --leanda-cyan: #049DD9;
        --leanda-sky: #79D0F2;
        --leanda-bg: #F2F2F2;
    }

    /* Fondo general */
    html:not(.dark) body,
    html:not(.dark) .fi-simple-layout,
    html:not(.dark) .fi-layout {
        background-color: #F8FAFC !important;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Inter', sans-serif !important;
    }

    /* REGLA CRÍTICA: Control estricto de iconos SVG para evitar desbordamientos */
    .fi-main svg:not(.fi-loading-indicator):not(.fi-icon-btn-icon) {
        max-width: 1.25rem !important;
        max-height: 1.25rem !important;
        width: 1.25rem !important;
        height: 1.25rem !important;
        display: inline-block !important;
        vertical-align: middle !important;
    }

    .fi-logo svg,
    .fi-simple-header svg {
        max-width: 24px !important;
        max-height: 24px !important;
        width: 24px !important;
        height: 24px !important;
    }

    /* =====================================================================
     * LOGIN VIEW
     * ===================================================================== */
    .fi-simple-main-ctn {
        max-width: 27rem !important;
        margin: auto !important;
    }

    html:not(.dark) .fi-simple-main {
        background: #FFFFFF !important;
        border-radius: 20px !important;
        border: 1px solid rgba(46, 98, 166, 0.12) !important;
        box-shadow: 0 20px 40px -15px rgba(46, 98, 166, 0.14) !important;
        position: relative !important;
        overflow: hidden !important;
        padding: 2.25rem 2rem !important;
    }

    .fi-simple-main::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #2E62A6 0%, #3270A6 50%, #049DD9 100%);
        pointer-events: none !important;
    }

    .dark .fi-simple-main {
        background: #0F172A !important;
        border: 1px solid rgba(121, 208, 242, 0.15) !important;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5) !important;
        border-radius: 20px !important;
        position: relative !important;
        overflow: hidden !important;
    }

    .fi-simple-header-heading {
        font-size: 1.25rem !important;
        font-weight: 800 !important;
        letter-spacing: -0.025em !important;
        color: #1E293B !important;
        text-align: center !important;
    }

    .dark .fi-simple-header-heading {
        color: #F8FAFC !important;
    }

    .fi-simple-header-subheading {
        font-size: 0.8rem !important;
        color: #64748B !important;
        text-align: center !important;
        margin-top: 0.25rem !important;
    }

    /* Botón Primario Gradient */
    .fi-btn-primary,
    button[type="submit"].fi-btn {
        background: linear-gradient(135deg, #2E62A6 0%, #3270A6 50%, #049DD9 100%) !important;
        color: #FFFFFF !important;
        font-weight: 700 !important;
        border-radius: 12px !important;
        padding: 0.75rem 1.25rem !important;
        box-shadow: 0 4px 14px 0 rgba(4, 157, 217, 0.35) !important;
        border: none !important;
        transition: all 0.2s ease !important;
        width: 100% !important;
        margin-top: 0.5rem !important;
        cursor: pointer !important;
    }

    button[type="submit"].fi-btn * {
        pointer-events: none !important;
    }

    /* =====================================================================
     * COMPONENTES DE OPERACIONES (OPS HUB)
     * ===================================================================== */
    .ops-card {
        background: #FFFFFF;
        border: 1px solid #E2E8F0;
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        margin-bottom: 1.5rem;
        position: relative;
        overflow: hidden;
    }

    .dark .ops-card {
        background: #1E293B;
        border-color: #334155;
    }

    .ops-card-top-bar {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #2E62A6 0%, #049DD9 100%);
    }

    .ops-card-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1.25rem;
    }

    .ops-card-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: rgba(4, 157, 217, 0.12);
        color: #2E62A6;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .dark .ops-card-icon {
        background: rgba(4, 157, 217, 0.2);
        color: #79D0F2;
    }

    .ops-card-title {
        font-size: 1.05rem;
        font-weight: 700;
        color: #0F172A;
        margin: 0;
    }

    .dark .ops-card-title {
        color: #F8FAFC;
    }

    .ops-grid-3 {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1rem;
    }

    .ops-grid-2 {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
    }

    .ops-label {
        display: block;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #475569;
        margin-bottom: 0.4rem;
    }

    .dark .ops-label {
        color: #94A3B8;
    }

    .ops-input,
    .ops-select {
        width: 100%;
        border: 1px solid #CBD5E1;
        border-radius: 10px;
        padding: 0.6rem 0.85rem;
        font-size: 0.875rem;
        color: #0F172A;
        background: #FFFFFF;
        box-sizing: border-box;
        transition: all 0.15s ease;
    }

    .dark .ops-input,
    .dark .ops-select {
        background: #0F172A;
        border-color: #334155;
        color: #F8FAFC;
    }

    .ops-input:focus,
    .ops-select:focus {
        outline: none;
        border-color: #049DD9;
        box-shadow: 0 0 0 3px rgba(4, 157, 217, 0.15);
    }

    .ops-btn-primary {
        background: linear-gradient(135deg, #2E62A6 0%, #049DD9 100%);
        color: #FFFFFF;
        font-weight: 700;
        font-size: 0.875rem;
        border: none;
        border-radius: 10px;
        padding: 0.65rem 1.25rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s ease;
        box-shadow: 0 2px 8px rgba(4, 157, 217, 0.25);
    }

    .ops-btn-primary:hover {
        opacity: 0.95;
        transform: translateY(-1px);
        box-shadow: 0 4px 14px rgba(4, 157, 217, 0.35);
    }

    .ops-btn-danger {
        background: #DC2626;
        color: #FFFFFF;
        font-weight: 700;
        font-size: 0.875rem;
        border: none;
        border-radius: 10px;
        padding: 0.65rem 1.25rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s ease;
    }

    .ops-btn-danger:hover {
        background: #B91C1C;
    }

    /* Tablas Elegantes */
    .ops-table-container {
        overflow-x: auto;
        border-radius: 12px;
        border: 1px solid #E2E8F0;
    }

    .dark .ops-table-container {
        border-color: #334155;
    }

    .ops-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.825rem;
        text-align: left;
    }

    .ops-table th {
        background: #F1F5F9;
        color: #475569;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.7rem;
        letter-spacing: 0.05em;
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #E2E8F0;
    }

    .dark .ops-table th {
        background: #0F172A;
        color: #94A3B8;
        border-color: #334155;
    }

    .ops-table td {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #F1F5F9;
        color: #1E293B;
    }

    .dark .ops-table td {
        border-color: #1E293B;
        color: #E2E8F0;
    }

    .ops-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.2rem 0.6rem;
        border-radius: 6px;
        font-size: 0.725rem;
        font-weight: 700;
        font-family: monospace;
    }

    .ops-badge-cyan {
        background: rgba(4, 157, 217, 0.15);
        color: #0284C7;
    }

    .ops-badge-success {
        background: rgba(16, 185, 129, 0.15);
        color: #059669;
    }

    .ops-badge-danger {
        background: rgba(239, 68, 68, 0.15);
        color: #DC2626;
    }
</style>
