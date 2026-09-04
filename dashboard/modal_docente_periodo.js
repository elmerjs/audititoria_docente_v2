/* modal_docente_periodo.js (v22 - Highlight Titulo Materia Diferente)
   Vista docente con TRES secciones jerárquicas y acordeones:
   - Resalte de fondo únicamente en el nombre de la materia en Oferta cuando el código difiere.
*/
(function () {
    'use strict';

    const API_URL = '../api/api.php';

    /* -------------------------------------------------------------------
       1. ESTILOS (Rediseño UX/UI + Acordeones + Resalte Materia)
       ------------------------------------------------------------------- */
    const CSS = `
    .mdp-overlay { position: fixed; inset: 0; z-index: 9999; display: none; align-items: center; justify-content: center; padding: 20px; background: rgba(15, 23, 42, .65); backdrop-filter: blur(6px); opacity: 0; transition: opacity .2s ease-out; box-sizing: border-box; }
    .mdp-overlay.mdp-visible { opacity: 1; }
    .mdp-modal { display: flex; flex-direction: column; width: 100%; max-width: 1160px; max-height: 90vh; overflow: hidden; border-radius: 16px; background: #ffffff; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, .25); transform: translateY(12px) scale(.99); transition: transform .2s ease-out; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
    .mdp-overlay.mdp-visible .mdp-modal { transform: translateY(0) scale(1); }
    
    /* ENCABEZADO */
    .mdp-header { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; padding: 20px 24px; color: #ffffff; background: linear-gradient(135deg, #1e3a8a, #2563eb); }
    .mdp-avatar { display: flex; align-items: center; justify-content: center; flex: 0 0 auto; width: 48px; height: 48px; border-radius: 12px; background: rgba(255, 255, 255, .18); color: #ffffff; font-size: 18px; font-weight: 700; letter-spacing: .05em; border: 1px solid rgba(255, 255, 255, .25); }
    .mdp-header-info { flex: 1; min-width: 180px; }
    .mdp-header-info h2 { margin: 0; font-size: 19px; line-height: 1.25; font-weight: 700; letter-spacing: -.01em; }
    .mdp-header-info small { display: block; margin-top: 4px; opacity: .9; font-size: 12.5px; font-weight: 400; }
    .mdp-close { width: 34px; height: 34px; margin-left: auto; border: 0; border-radius: 8px; background: rgba(255, 255, 255, .15); color: #ffffff; font-size: 20px; line-height: 1; cursor: pointer; transition: background .15s ease, transform .1s ease; display: flex; align-items: center; justify-content: center; }
    .mdp-close:hover { background: rgba(255, 255, 255, .28); transform: scale(1.05); }

    /* KPIS / METRICAS */
    .mdp-kpis { display: flex; flex-wrap: wrap; gap: 8px; padding: 12px 24px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; }
    .mdp-kpi { display: flex; align-items: center; gap: 6px; padding: 5px 12px; border: 1px solid #e2e8f0; border-radius: 20px; background: #ffffff; color: #475569; font-size: 11.5px; font-weight: 500; box-shadow: 0 1px 2px rgba(0,0,0,.03); }
    .mdp-kpi.mdp-kpi-cero { opacity: .5; background: #f1f5f9; }
    .mdp-kpi b { color: #0f172a; font-size: 13px; font-weight: 700; }
    .mdp-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; }

    /* TABS */
    .mdp-tabs-wrap { padding: 12px 24px 0; background: #ffffff; border-bottom: 1px solid #e2e8f0; }
    .mdp-tabs { display: flex; gap: 6px; padding: 4px; border-radius: 10px; background: #f1f5f9; }
    .mdp-tab { flex: 1; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 9px 16px; border: 0; border-radius: 7px; background: transparent; color: #64748b; font-size: 13px; font-weight: 600; cursor: pointer; transition: all .15s ease; }
    .mdp-tab i { font-size: 13px; color: #64748b; transition: color .15s ease; }
    .mdp-tab:hover:not(.active) { color: #1e293b; background: rgba(255, 255, 255, .5); }
    .mdp-tab.active { background: #ffffff; color: #1d4ed8; box-shadow: 0 1px 3px rgba(0, 0, 0, .1), 0 1px 2px rgba(0, 0, 0, .06); }
    .mdp-tab.active i { color: #1d4ed8; }

    /* BANDERA Y CONTENIDO */
    .mdp-view-flag { display: flex; align-items: center; gap: 8px; margin: 0 0 16px; padding: 8px 14px; border-radius: 8px; background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; font-size: 12px; font-weight: 600; }
    .mdp-view-flag i { color: #2563eb; font-size: 12px; }
    .mdp-view-flag .mdp-view-flag-sep { color: #93c5fd; font-weight: 400; }
    .mdp-view-flag.materia { background: #f5f3ff; border-color: #ddd6fe; color: #5b21b6; }
    .mdp-view-flag.materia i { color: #7c3aed; }
    .mdp-body { flex: 1; overflow-y: auto; padding: 20px 24px; background: #f8fafc; }
    .mdp-tab-content { display: none; }
    .mdp-tab-content.active { display: block; }

    /* SECCIONES Y ACORDEÓN */
    .mdp-section {
        margin: 0 0 20px;
        border-radius: 12px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        box-shadow: 0 2px 4px rgba(0, 0, 0, .02);
        transition: box-shadow .15s ease;
    }
    .mdp-section:last-child { margin-bottom: 0; }
    .mdp-section:hover { box-shadow: 0 4px 12px rgba(0, 0, 0, .05); }

    .mdp-section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 14px 18px;
        background: #fefcf6; /* Fondo crema acorde */
        border-bottom: 1px solid #f3eedc;
    }
    .mdp-accordion-toggle {
        cursor: pointer;
        user-select: none;
        transition: background .15s ease;
    }
    .mdp-accordion-toggle:hover {
        background: #fcf8eb;
    }

    .mdp-section-grupo .mdp-section-header { border-left: 4px solid #2563eb; }
    .mdp-section-hermanos .mdp-section-header { border-left: 4px solid #3b82f6; }
    .mdp-section-other .mdp-section-header { border-left: 4px solid #94a3b8; }

    .mdp-section-header-main { display: flex; align-items: flex-start; gap: 12px; flex: 1; }
    .mdp-section-header-main > i { margin-top: 3px; font-size: 15px; flex-shrink: 0; }
    .mdp-section-grupo .mdp-section-header-main > i { color: #2563eb; }
    .mdp-section-hermanos .mdp-section-header-main > i { color: #3b82f6; }
    .mdp-section-other .mdp-section-header-main > i { color: #64748b; }

    .mdp-section-title { margin: 0; font-size: 14px; line-height: 1.3; font-weight: 700; color: #0f172a; display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }

    .mdp-title-label { display: inline-block; padding: 2px 10px; border-radius: 6px; font-weight: 700; font-size: 12px; line-height: 1.4; }
    .mdp-section-grupo .mdp-title-label { background: #eff6ff; color: #1e40af; }
    .mdp-section-hermanos .mdp-title-label { background: #f0fdf4; color: #166534; }
    .mdp-section-other .mdp-title-label { background: #f1f5f9; color: #334155; }

    .mdp-section-title small { font-weight: 500; font-size: 12px; color: #64748b; }
    .mdp-section-subtitle { margin: 4px 0 0; color: #64748b; font-size: 12px; line-height: 1.4; }
    .mdp-section-subtitle .mdp-materia-nombre { color: #0f172a; font-weight: 600; }
    .mdp-section-subtitle .mdp-grupo-label { margin-left: 6px; color: #64748b; }
    .mdp-section-subtitle .mdp-grupo-badge { display: inline-block; margin-left: 4px; padding: 1px 8px; border-radius: 999px; background: #2563eb; color: #ffffff; font-size: 10.5px; font-weight: 700; vertical-align: middle; }
    .mdp-section-subtitle .mdp-code-badge { display: inline-block; margin-right: 6px; padding: 2px 6px; border-radius: 4px; background: #eff6ff; color: #1d4ed8; font-size: 11px; font-weight: 700; border: 1px solid #bfdbfe; vertical-align: middle; }
    .mdp-section-subtitle .mdp-code-badge-suave { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }

    /* FLECHA INDICADORA ACORDEÓN */
    .mdp-accordion-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        color: #64748b;
        font-size: 12px;
        transition: transform .25s ease, background .15s ease;
        flex-shrink: 0;
    }
    .mdp-accordion-toggle:hover .mdp-accordion-icon { background: #f1f5f9; color: #1e293b; }
    .mdp-section.collapsed .mdp-accordion-icon { transform: rotate(-90deg); }
    .mdp-section.collapsed .mdp-section-header { border-bottom: 0; }
    .mdp-section.collapsed .mdp-section-body { display: none; }

    .mdp-section-body { padding: 16px; background: #ffffff; }
    .mdp-section-empty { padding: 16px; color: #94a3b8; font-size: 12.5px; font-style: italic; text-align: center; }

    /* GRILLA Y LAYOUT COMPARATIVO */
    .mdp-grid { display: grid; grid-template-columns: minmax(0, 1fr) 32px minmax(0, 1fr); gap: 10px; align-items: stretch; margin-bottom: 12px; }
    .mdp-grid:last-child { margin-bottom: 0; }
    .mdp-col-title { display: flex; align-items: center; gap: 6px; min-height: 20px; margin: 0 0 8px; color: #64748b; font-size: 10.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
    .mdp-col-title .mdp-count { padding: 1px 6px; border-radius: 999px; background: #f1f5f9; color: #475569; font-size: 10px; font-weight: 700; }
    .mdp-column-separator { min-height: 20px; }
    .mdp-arrow-cell { display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: 15px; font-weight: bold; }

    /* TARJETAS REDISEÑADAS */
    .mdp-card { position: relative; box-sizing: border-box; padding: 12px 14px; border: 1px solid #e2e8f0; border-left-width: 4px; border-radius: 8px; background: #ffffff; transition: all .15s ease; display: flex; flex-direction: column; justify-content: space-between; gap: 6px; }
    .mdp-card:hover { border-color: #cbd5e1; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, .05); }
    .mdp-card h4 { margin: 0; padding-right: 48px; color: #0f172a; font-size: 13px; line-height: 1.35; font-weight: 700; }
    .mdp-card p { margin: 0; color: #475569; font-size: 11.5px; line-height: 1.4; display: flex; flex-wrap: wrap; align-items: baseline; gap: 0 8px; }
    .mdp-card p b { color: #1e293b; font-weight: 600; min-width: 46px; flex-shrink: 0; font-size: 11px; }
    .mdp-card .mdp-meta { color: #64748b; font-size: 11px; }
    .mdp-card .mdp-meta b { color: #64748b; font-size: 11px; min-width: auto; }
    .mdp-tag { position: absolute; top: 10px; right: 10px; padding: 2px 6px; border-radius: 4px; background: #f1f5f9; color: #64748b; font-size: 9.5px; font-weight: 700; letter-spacing: .02em; }

    /* ESTADOS Y VARIACIONES VISUALES */
    .mdp-estado { display: inline-flex; align-items: center; gap: 4px; margin-top: 4px; padding: 2px 8px; border-radius: 6px; font-size: 10px; font-weight: 700; letter-spacing: .02em; text-transform: uppercase; width: fit-content; }
    
    .mdp-ok { border-left-color: #10b981; } 
    .mdp-ok .mdp-estado { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
    
    .mdp-revisar { border-left-color: #f59e0b; background: #fffbeb; } 
    .mdp-revisar .mdp-estado { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
    
    .mdp-inexistente { border-left-color: #ef4444; background: #fef2f2; } 
    .mdp-inexistente .mdp-estado { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
    
    .mdp-solof { border-left-color: #64748b; background: #f8fafc; } 
    .mdp-solof .mdp-estado { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
    
    .mdp-oferta-sin-labor { opacity: .9; border-left-color: #64748b !important; background: #f8fafc !important; }
    .mdp-sin-labor { border-left-color: #ec4899 !important; background: #fdf2f8; }
    .mdp-sin-labor .mdp-estado { background: #fce7f3; color: #be185d; border: 1px solid #fbcfe8; }
    .mdp-labor-ausente { color: #be185d; font-style: italic; font-size: 11px; margin: 0; }
    
    .mdp-codigo-diff { border-left-color: #6366f1 !important; background: #eef2ff; }
    .mdp-codigo-diff .mdp-estado { background: #e0e7ff; color: #4338ca; border: 1px solid #c7d2fe; }
    .mdp-codigo-diff-nota { color: #4f46e5; font-style: italic; font-size: 10.5px; }

    /* FONDO DE RESALTE PARA NOMBRE DE MATERIA CUANDO EL CÓDIGO DIFIERE */
    .mdp-materia-diff {
        display: inline-block;
        background: #fee2e2;
        color: #991b1b;
        padding: 1px 6px;
        border-radius: 4px;
        border: 1px solid #fca5a5;
    }

    /* GRUPO SELECCIONADO DESTACADO */
    .mdp-selected-group { border: 2px solid #3b82f6 !important; border-left-width: 5px !important; background: #f0f9ff !important; box-shadow: 0 4px 12px rgba(59, 130, 246, .12) !important; }
    .mdp-selected-group-label { display: inline-flex; align-items: center; gap: 3px; margin-left: 4px; padding: 1px 6px; border-radius: 4px; background: #2563eb; color: #ffffff; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }

    /* BADGES Y ALERTAS */
    .mdp-matcero { display: inline-flex; align-items: center; gap: 2px; margin-top: 4px; padding: 1px 6px; border: 1px solid #ddd6fe; border-radius: 4px; background: #f5f3ff; color: #6d28d9; font-size: 10px; font-weight: 700; }
    .mdp-programa-comparacion { display: inline-flex; align-items: center; gap: 2px; margin-top: 4px; padding: 1px 6px; border-radius: 4px; font-size: 10px; font-weight: 700; }
    .mdp-programa-alta { background: #fefce8; color: #854d0e; border: 1px solid #fde047; }
    .mdp-programa-media { background: #fffbeb; color: #92400e; border: 1px solid #fcd34d; }
    .mdp-programa-baja { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

    .mdp-dato-diferente { display: inline; padding: 1px 4px; border-radius: 4px; background: #fee2e2; color: #991b1b; font-weight: 700; }
    .mdp-dato-diferente-label { display: inline-flex; align-items: center; margin-left: 4px; padding: 0 5px; border: 1px solid #fca5a5; border-radius: 4px; background: #fef2f2; color: #991b1b; font-size: 9px; font-weight: 700; text-transform: uppercase; }

    .mdp-codigo-diferente { display: inline-flex; align-items: center; gap: 3px; padding: 1px 5px; border: 1px solid #fca5a5; border-radius: 4px; background: #fee2e2; color: #991b1b; font-weight: 700; font-size: 11px; }
    .mdp-codigo-diferente-label { display: inline-flex; align-items: center; margin-left: 4px; padding: 0 5px; border: 1px solid #fca5a5; border-radius: 4px; background: #fef2f2; color: #991b1b; font-size: 9px; font-weight: 700; text-transform: uppercase; }

    .mdp-pe-critico { display: inline-flex; align-items: center; gap: 3px; padding: 1px 6px; border: 1px solid #fca5a5; border-radius: 4px; background: #fee2e2; color: #991b1b; font-weight: 700; font-size: 11px; }
    .mdp-pe-normal { color: #475569; font-weight: 600; font-size: 11px; }
    
    .mdp-comparacion-alerta { margin-top: 6px; padding: 6px 8px; border-radius: 6px; background: #ffffff; border: 1px solid #fca5a5; color: #991b1b; font-size: 11px; line-height: 1.4; box-shadow: 0 1px 2px rgba(0,0,0,.03); }
    .mdp-comparacion-alerta i { margin-right: 4px; color: #dc2626; }

    /* VISTA MATERIA / GRUPO REDISEÑADA */
    .mdp-resumen { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-bottom: 16px; padding: 14px 16px; border: 1px solid #e2e8f0; border-radius: 8px; background: #ffffff; }
    .mdp-resumen-item { color: #64748b; font-size: 11.5px; }
    .mdp-resumen-item b { color: #0f172a; font-size: 13px; font-weight: 700; display: block; margin-top: 2px; }
    .mdp-alerta-block { margin-bottom: 16px; padding: 12px 16px; border-radius: 8px; font-size: 12.5px; line-height: 1.5; }
    .mdp-alerta-block.danger { border: 1px solid #fbcfe8; background: #fdf2f8; color: #831843; }
    .mdp-alerta-block.success { border: 1px solid #a7f3d0; background: #ecfdf5; color: #065f46; }
    .mdp-table-wrap { overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px; }
    .mdp-table { width: 100%; border-collapse: collapse; font-size: 12px; background: #ffffff; }
    .mdp-table th { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; background: #f8fafc; color: #475569; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-align: left; text-transform: uppercase; }
    .mdp-table td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; color: #1e293b; }
    .mdp-table tr:last-child td { border-bottom: 0; }

    .mdp-loading { padding: 40px 0; color: #64748b; font-size: 14px; text-align: center; }
    .mdp-spinner { width: 32px; height: 32px; margin: 0 auto 12px; border: 3px solid #e2e8f0; border-top-color: #2563eb; border-radius: 50%; animation: mdp-spin .8s linear infinite; }
    .mdp-error { padding: 14px 16px; border: 1px solid #fca5a5; border-radius: 8px; background: #fef2f2; color: #991b1b; font-size: 13px; word-break: break-word; }
    @keyframes mdp-spin { to { transform: rotate(360deg); } }

    @media (max-width: 760px) {
        .mdp-overlay { padding: 10px; }
        .mdp-modal { max-height: 94vh; border-radius: 12px; }
        .mdp-header { padding: 14px 16px; }
        .mdp-kpis { padding: 8px 16px; gap: 6px; }
        .mdp-kpi { flex: 1 1 calc(50% - 6px); font-size: 10.5px; justify-content: space-between; }
        .mdp-tabs-wrap { padding: 8px 16px 0; }
        .mdp-body { padding: 12px 16px; }
        .mdp-grid { grid-template-columns: 1fr; gap: 8px; }
        .mdp-column-separator { display: none; }
        .mdp-arrow-cell { grid-column: 1; padding: 2px 0; transform: rotate(90deg); }
        .mdp-section-header { flex-wrap: wrap; padding: 12px 14px; }
        .mdp-resumen { grid-template-columns: 1fr 1fr; }
    }
    `;
    const styleEl = document.createElement('style');
    styleEl.textContent = CSS;
    document.head.appendChild(styleEl);

    let overlay = null;
    let currentContext = null;

    /* -------------------------------------------------------------------
       2. HELPERS
       ------------------------------------------------------------------- */
    function esc(valor) {
        return String(valor ?? '').replace(/[&<>"']/g, (caracter) => {
            const mapa = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return mapa[caracter];
        });
    }

    function normalizarCodigo(valor) {
        return String(valor ?? '').trim().toUpperCase();
    }

    function normalizarGrupo(valor) {
        return String(valor ?? '').trim().toUpperCase();
    }

    function textoNormalizado(valor) {
        return String(valor ?? '').trim().replace(/\s+/g, ' ').toLowerCase();
    }

    function sonTextosDiferentes(valorA, valorB) {
        const a = textoNormalizado(valorA);
        const b = textoNormalizado(valorB);
        if (!a || !b) return false;
        return a !== b;
    }

    function mostrarDatoComparado(valor, diferente, titulo = '') {
        const contenido = esc(valor || '—');
        if (!diferente) return contenido;
        return `<span class="mdp-dato-diferente" title="${esc(titulo)}">${contenido}</span><span class="mdp-dato-diferente-label" title="${esc(titulo)}">difiere</span>`;
    }

    function normalizarCodigoMateriaVisual(codigo) {
        return String(codigo ?? '')
            .trim()
            .replace(/\s+/g, '')
            .toUpperCase();
    }

    function codigoMateriaDiferente(codigoLabor, codigoOferta) {
        const a = normalizarCodigoMateriaVisual(codigoLabor);
        const b = normalizarCodigoMateriaVisual(codigoOferta);
        if (!a || !b) return false;
        return a !== b;
    }

    function mostrarCodigoComparado(codigo, diferente, titulo = '') {
        const contenido = esc(codigo || '—');
        if (!diferente) return contenido;
        return `<span class="mdp-codigo-diferente" title="${esc(titulo)}">${contenido}</span><span class="mdp-codigo-diferente-label" title="${esc(titulo)}">Código difiere</span>`;
    }

    function mostrarPE(pe) {
        const valor = pe === null || pe === undefined || pe === '' ? '—' : pe;
        if (Number(pe) === 0) {
            return `<span class="mdp-pe-critico" title="El PE de Labor es cero. Este valor requiere revisión.">PE: 0</span>`;
        }
        return `<span class="mdp-pe-normal">PE: ${esc(valor)}</span>`;
    }

    function obtenerCodigo(registro) {
        return registro?.codigo_materia ?? registro?.codigoMateria ?? '';
    }

    function obtenerMateria(registro) {
        return registro?.materia ?? registro?.materia_labor ?? registro?.materia_oferta ?? '';
    }

    function obtenerHoras(registro) {
        return registro?.horas_teoricas ?? registro?.horasTeoricas ?? 0;
    }

    function obtenerTipoContrato(registro) {
        return registro?.tipo_contrato ?? registro?.tipoContrato ?? '';
    }

    function esGrupoSeleccionado(labor, contexto) {
        return normalizarCodigo(obtenerCodigo(labor)) === normalizarCodigo(contexto?.codigoMateria)
            && normalizarGrupo(labor?.grupo) === normalizarGrupo(contexto?.grupo);
    }

    function codigoDeCruce(cruce) {
        return obtenerCodigo(cruce?.labor) || obtenerCodigo(cruce?.oferta) || '';
    }

    function grupoDeCruce(cruce) {
        return cruce?.labor?.grupo ?? cruce?.oferta?.grupo ?? '';
    }

    function codigoDeOfertaSinLabor(oferta) {
        return obtenerCodigo(oferta) || '';
    }

    function grupoDeOfertaSinLabor(oferta) {
        return oferta?.grupo ?? '';
    }

    const ESTADO_TXT = {
        OK: { cls: 'ok', label: 'Cruza OK' },
        REVISAR_PROGRAMA_DIFERENTE: { cls: 'revisar', label: 'Programa diferente' },
        OFERTA_INEXISTENTE: { cls: 'inexistente', label: 'Sin oferta' },
        FALTA_GRUPO_EN_OFERTA: { cls: 'inexistente', label: 'Grupo sin oferta' },
        LABOR_INEXISTENTE: { cls: 'sin-labor', label: 'No existe en Labor' },
        REVISAR_CODIGO_MATERIA_DIFERENTE: { cls: 'codigo-diff', label: 'Revisar código materia' },
    };

    function badgeMatCero(oferta) {
        if (!oferta) return '';
        const matriculados = Number(oferta.matriculados ?? 0);
        if (matriculados !== 0) return '';
        return `<span class="mdp-matcero" title="Este grupo no tiene estudiantes matriculados en Oferta.">0 matriculados</span>`;
    }

function lineaOcupacion(oferta) {
    if (!oferta) return '';
    
    const matriculados = Number(oferta.matriculados ?? 0);
    const cupo = oferta.cupo !== null && oferta.cupo !== undefined && oferta.cupo !== '' ? Number(oferta.cupo) : null;
    
    // Si no hay cupo definido
    if (cupo === null) {
        return `<div style="margin: 4px 0 0 0; padding: 4px 8px; background: #f8fafc; border-radius: 4px; border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 10px; font-weight: 600; color: #64748b;">Matriculados</span>
            <span style="font-size: 13px; font-weight: 700; color: #0f172a;">${esc(matriculados)}</span>
            <span style="font-size: 10px; color: #94a3b8; margin-left: auto;">(cupo no definido)</span>
        </div>`;
    }
    
    // Calcular porcentaje de ocupación
    const porcentaje = cupo > 0 ? Math.round((matriculados / cupo) * 100) : 0;
    
    // Determinar color y estado visual
    let color = '#475569';
    let estadoTexto = '';
    let estadoColor = '';
    let icono = '';
    
    if (matriculados === 0) {
        color = '#dc2626';
        estadoTexto = 'Sin estudiantes';
        estadoColor = '#fee2e2';
        icono = '⚠️';
    } else if (porcentaje >= 80) {
        color = '#059669';
        estadoTexto = 'Alta ocupación';
        estadoColor = '#d1fae5';
        icono = '✅';
    } else if (porcentaje >= 40) {
        color = '#d97706';
        estadoTexto = 'Ocupación media';
        estadoColor = '#fef3c7';
        icono = '📊';
    } else {
        color = '#2563eb';
        estadoTexto = 'Baja ocupación';
        estadoColor = '#e0f2fe';
        icono = '📉';
    }
    
    // Barra de progreso visual
    const barraWidth = Math.min(porcentaje, 100);
    
    return `<div style="margin: 4px 0 0 0; padding: 6px 10px; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0;">
        <!-- Fila superior: título + datos principales -->
        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
            <span style="font-size: 10px; font-weight: 600; color: #64748b; white-space: nowrap;">Matriculados</span>
            <span style="font-size: 14px; font-weight: 700; color: #0f172a;">${esc(matriculados)}</span>
            <span style="font-size: 12px; color: #94a3b8; font-weight: 400;">/ ${esc(cupo)}</span>
            <span style="font-size: 12px; font-weight: 700; color: ${color};">${porcentaje}%</span>
            <span style="font-size: 9px; padding: 1px 8px; border-radius: 10px; background: ${estadoColor}; color: ${color}; font-weight: 600; border: 1px solid ${color}33; white-space: nowrap;">
                ${icono} ${estadoTexto}
            </span>
            <span style="font-size: 9px; color: #94a3b8; font-weight: 500; margin-left: auto; white-space: nowrap;">cupo disponible</span>
        </div>
        
        <!-- Barra de progreso (más delgada) -->
        <div style="width: 100%; height: 4px; background: #e2e8f0; border-radius: 4px; overflow: hidden; margin-top: 4px;">
            <div style="width: ${barraWidth}%; height: 100%; background: ${color}; border-radius: 4px; transition: width 0.3s ease;"></div>
        </div>
        
        <!-- Mensaje compacto para grupos sin estudiantes (solo cuando es 0) -->
        ${matriculados === 0 ? `
        <div style="margin-top: 4px; font-size: 9px; color: #dc2626; font-weight: 600;">
            ⚠️ Sin estudiantes matriculados
        </div>` : ''}
    </div>`;
}
    function badgeComparacionPrograma(cruce) {
        const coincidencia = cruce?.coincidencia_programa;
        if (!coincidencia || coincidencia === 'EXACTA' || coincidencia === 'NO_APLICA') return '';
        const pct = cruce.similitud_programa !== null && cruce.similitud_programa !== undefined ? Math.round(cruce.similitud_programa * 100) + '%' : 'N/D';
        let clase = 'mdp-programa-baja';
        let texto = `Programa diferente: ${pct}`;
        if (coincidencia === 'ALTA') { clase = 'mdp-programa-alta'; texto = `Programa parecido: ${pct}`; }
        else if (coincidencia === 'MEDIA') { clase = 'mdp-programa-media'; texto = `Programa parecido: ${pct}`; }
        return `<span class="mdp-programa-comparacion ${clase}" title="${esc(cruce.motivo_programa || '')}">${esc(texto)}</span>`;
    }

    /* -------------------------------------------------------------------
       3. TARJETAS LABOR / OFERTA
       ------------------------------------------------------------------- */
    function cardLabor(labor, estado, parNum, seleccionado = false, oferta = null) {
        const meta = ESTADO_TXT[estado] ?? { cls: 'inexistente', label: estado || 'Sin estado' };
        const claseSeleccionada = seleccionado ? 'mdp-selected-group' : '';
        const etiquetaSeleccionada = seleccionado ? `<span class="mdp-selected-group-label">Grupo seleccionado</span>` : '';

        const codigoLabor = obtenerCodigo(labor);
        const codigoOferta = oferta ? obtenerCodigo(oferta) : '';
        const codigoDifiere = codigoMateriaDiferente(codigoLabor, codigoOferta);
        const tituloCodigo = codigoDifiere ? `Código Labor: ${codigoLabor || '—'} | Código Oferta: ${codigoOferta || '—'}` : '';

        const materiaLabor = obtenerMateria(labor);
        const programaLabor = labor?.programa ?? '';
        const horas = obtenerHoras(labor);
        const contrato = obtenerTipoContrato(labor);
        const materiaOferta = obtenerMateria(oferta);
        const programaOferta = oferta?.programa ?? '';

        const materiaDiferente = codigoDifiere ? false : sonTextosDiferentes(materiaLabor, materiaOferta);
        const programaDiferente = sonTextosDiferentes(programaLabor, programaOferta);
        const tituloMateria = materiaDiferente ? `Materia Labor: ${materiaLabor || '—'} | Materia Oferta: ${materiaOferta || '—'}` : '';
        const tituloPrograma = programaDiferente ? `Programa Labor: ${programaLabor || '—'} | Programa Oferta: ${programaOferta || '—'}` : '';

        const requiereRevision = estado === 'REVISAR_PROGRAMA_DIFERENTE' || estado === 'REVISAR_CODIGO_MATERIA_DIFERENTE' || materiaDiferente || programaDiferente || codigoDifiere;

        const detalleRevision = [
            materiaDiferente ? 'la materia de Labor no coincide con la materia de Oferta.' : '',
            programaDiferente ? 'el programa de Labor no coincide con el programa de Oferta.' : '',
            codigoDifiere ? 'el código de materia de Labor no coincide con el código de Oferta.' : ''
        ].filter(Boolean).join('<br>');

        return `<div class="mdp-card mdp-${meta.cls} ${claseSeleccionada}">
            ${parNum ? `<span class="mdp-tag">Par ${esc(parNum)}</span>` : ''}
            <div>
                <h4>${mostrarDatoComparado(materiaLabor, materiaDiferente, tituloMateria)}</h4>
                <p style="margin-top:4px"><b>Código:</b> ${mostrarCodigoComparado(codigoLabor, codigoDifiere, tituloCodigo)} <b>Grupo:</b> ${esc(labor?.grupo ?? '—')} ${etiquetaSeleccionada}</p>
                <p><b>Programa:</b> ${mostrarDatoComparado(programaLabor, programaDiferente, tituloPrograma)}</p>
                <p class="mdp-meta">${esc(horas)} h. teóricas ${contrato ? '· ' + esc(contrato) : ''}</p>
                <p>${mostrarPE(labor?.pe)}</p>
            </div>
            <div>
                <span class="mdp-estado">${esc(meta.label)}</span>
                ${requiereRevision && detalleRevision ? `<div class="mdp-comparacion-alerta"><i class="fa-solid fa-triangle-exclamation"></i><b>Revisión:</b> ${detalleRevision}</div>` : ''}
            </div>
        </div>`;
    }

    function cardOferta(oferta, estado, parNum, cruce = null) {
        if (!oferta) {
            return `<div class="mdp-card mdp-inexistente">
                ${parNum ? `<span class="mdp-tag">Par ${esc(parNum)}</span>` : ''}
                <div>
                    <h4 style="color:#b91c1c">Sin registro en Oferta</h4>
                    <p style="margin-top:4px">Esta materia no fue encontrada en la oferta académica del período para este docente.</p>
                </div>
                <span class="mdp-estado">Posible prestación de servicio</span>
            </div>`;
        }

        const clase = estado === 'OK' ? 'mdp-ok' : 'mdp-revisar';
        const texto = estado === 'OK' ? 'Coincide con Labor' : 'Revisión requerida';
        const badgeComparacion = cruce ? badgeComparacionPrograma(cruce) : '';

        const codigoOferta = obtenerCodigo(oferta);
        const codigoLabor = cruce?.labor ? obtenerCodigo(cruce.labor) : '';
        const codigoDifiere = codigoMateriaDiferente(codigoLabor, codigoOferta);
        const tituloCodigo = codigoDifiere ? `Código Labor: ${codigoLabor || '—'} | Código Oferta: ${codigoOferta || '—'}` : '';

        const materiaLabor = cruce?.labor?.materia ?? '';
        const programaLabor = cruce?.labor?.programa ?? '';
        const materiaOferta = obtenerMateria(oferta);
        const programaOferta = oferta?.programa ?? '';

        const materiaDiferente = codigoDifiere ? false : sonTextosDiferentes(materiaLabor, materiaOferta);
        const programaDiferente = sonTextosDiferentes(programaLabor, programaOferta);
        const tituloMateria = materiaDiferente ? `Materia Labor: ${materiaLabor || '—'} | Materia Oferta: ${materiaOferta || '—'}` : '';
        const tituloPrograma = programaDiferente ? `Programa Labor: ${programaLabor || '—'} | Programa Oferta: ${programaOferta || '—'}` : '';

        return `<div class="mdp-card ${clase}">
            ${parNum ? `<span class="mdp-tag">Par ${esc(parNum)}</span>` : ''}
            <div>
                <h4>${mostrarDatoComparado(materiaOferta, materiaDiferente, tituloMateria)}</h4>
                <p style="margin-top:4px"><b>Código:</b> ${mostrarCodigoComparado(codigoOferta, codigoDifiere, tituloCodigo)} <b>Grupo:</b> ${esc(oferta?.grupo ?? '—')}</p>
                <p><b>Programa:</b> ${mostrarDatoComparado(programaOferta, programaDiferente, tituloPrograma)}</p>
                ${lineaOcupacion(oferta)}
            </div>
            <div>
                <span class="mdp-estado">${esc(texto)}</span>
                ${badgeMatCero(oferta)}
                ${badgeComparacion}
            </div>
        </div>`;
    }

    function cardOfertaSinLabor(oferta) {
        return `<div class="mdp-card mdp-solof mdp-oferta-sin-labor">
            <div>
                <h4>${esc(obtenerMateria(oferta))}</h4>
                <p style="margin-top:4px"><b>Código:</b> ${esc(obtenerCodigo(oferta))} <b>Grupo:</b> ${esc(oferta?.grupo ?? '—')}</p>
                <p><b>Programa:</b> ${esc(oferta?.programa ?? '—')}</p>
                ${lineaOcupacion(oferta)}
            </div>
            <div>
                <span class="mdp-estado">Solo en Oferta (sin labor asociada)</span>
                ${badgeMatCero(oferta)}
            </div>
        </div>`;
    }

    function cardLaborSinRegistro(oferta, parNum) {
        return `<div class="mdp-card mdp-sin-labor">
            ${parNum ? `<span class="mdp-tag">Par ${esc(parNum)}</span>` : ''}
            <div>
                <h4 style="color:#be185d">Sin registro en Labor</h4>
                <p class="mdp-labor-ausente" style="margin-top:4px">Esta materia está registrada en Oferta, pero no se encontró una asignación de Labor para este docente y período.</p>
            </div>
            <span class="mdp-estado">NO EXISTE EN LABOR</span>
        </div>`;
    }

       function cardOfertaCodigoDiferente(diagnostico, parNum) {
        if (!diagnostico) {
            return `<div class="mdp-card mdp-inexistente">
                ${parNum ? `<span class="mdp-tag">Par ${esc(parNum)}</span>` : ''}
                <div>
                    <h4 style="color:#b91c1c">Sin registro en Oferta</h4>
                    <p style="margin-top:4px">No se encontró ninguna oferta relacionable para este docente y materia.</p>
                </div>
                <span class="mdp-estado">Posible prestación de servicio</span>
            </div>`;
        }

        const pctMateria = diagnostico.similitud_materia !== null && diagnostico.similitud_materia !== undefined 
            ? Math.round(diagnostico.similitud_materia * 100) + '%' 
            : 'N/D';
        const pctPrograma = diagnostico.similitud_programa !== null && diagnostico.similitud_programa !== undefined 
            ? Math.round(diagnostico.similitud_programa * 100) + '%' 
            : 'N/D';

        const codigoLabor = diagnostico.codigo_materia_labor ?? '';
        const codigoOferta = diagnostico.codigo_materia_oferta ?? '';
        const codigoDifiere = codigoMateriaDiferente(codigoLabor, codigoOferta);
        const tituloCodigo = codigoDifiere ? `Código Labor: ${codigoLabor || '—'} | Código Oferta: ${codigoOferta || '—'}` : '';

        const programaDiferente = sonTextosDiferentes(diagnostico.programa_labor, diagnostico.programa_oferta);
        const tituloMateriaResalte = `Materia Labor: ${diagnostico.materia_labor || '—'} | Materia Oferta: ${diagnostico.materia_oferta || '—'}`;

        /* Titulo resaltado ÚNICAMENTE con la clase de fondo sin etiquetas extra */
        const tituloOfertaHtml = `<span class="mdp-materia-diff" title="${esc(tituloMateriaResalte)}">${esc(diagnostico.materia_oferta || 'Materia en Oferta')}</span>`;

        // CONSTRUIR DATOS DE OCUPACIÓN DESDE EL DIAGNÓSTICO
        const datosOcupacion = {
            matriculados: diagnostico.matriculados_oferta ?? 0,
            cupo: diagnostico.cupo_oferta ?? null
        };
        const lineaOcupacionHtml = lineaOcupacion(datosOcupacion);

        return `<div class="mdp-card mdp-codigo-diff">
            ${parNum ? `<span class="mdp-tag">Par ${esc(parNum)}</span>` : ''}
            <div>
                <h4>${tituloOfertaHtml}</h4>
                <p style="margin-top:4px"><b>Código en Oferta:</b> ${mostrarCodigoComparado(codigoOferta, codigoDifiere, tituloCodigo)} <span class="mdp-codigo-diff-nota">(en Labor: ${esc(codigoLabor)})</span> <b>Grupo:</b> ${esc(diagnostico.grupo_oferta ?? '—')}</p>
                <p><b>Programa:</b> ${mostrarDatoComparado(diagnostico.programa_oferta, programaDiferente, `Programa Labor: ${diagnostico.programa_labor || '—'} | Programa Oferta: ${diagnostico.programa_oferta || '—'}`)}</p>
                ${lineaOcupacionHtml}
                <p class="mdp-codigo-diff-nota" title="${esc(diagnostico.motivo || '')}">Similitud materia: ${esc(pctMateria)} <span style="color:#cbd5e1">·</span> Similitud programa: ${esc(pctPrograma)}</p>
            </div>
            <div>
                <span class="mdp-estado">Revisar código de materia</span>
                ${Number(datosOcupacion.matriculados) === 0 ? `<span class="mdp-matcero" style="margin-top:4px;">0 matriculados</span>` : ''}
            </div>
        </div>`;
    }

    /* -------------------------------------------------------------------
       4. RENDERIZADO DE GRILLA LABOR / OFERTA
       ------------------------------------------------------------------- */
    function renderGrillaCruces(cruces, ofertasSinLabor, contexto, marcarSeleccionado = false) {
        if (!cruces.length && !ofertasSinLabor.length) {
            return `<div class="mdp-section-empty">No hay registros para mostrar en esta sección.</div>`;
        }
        let html = `<div class="mdp-grid">
            <p class="mdp-col-title" style="grid-column:1"><span>LABOR</span><span class="mdp-count">${esc(cruces.length)}</span></p>
            <div class="mdp-column-separator" aria-hidden="true"></div>
            <p class="mdp-col-title" style="grid-column:3"><span>OFERTA</span></p>`;
        let par = 0;
        cruces.forEach(cruce => {
            par++;
            const grupoSeleccionado = marcarSeleccionado && esGrupoSeleccionado(cruce.labor, contexto);
            html += cardLabor(cruce.labor, cruce.estado, par, grupoSeleccionado, cruce.oferta);
            html += `<div class="mdp-arrow-cell" aria-hidden="true"><span>&#8594;</span></div>`;
            html += cruce.estado === 'REVISAR_CODIGO_MATERIA_DIFERENTE'
                ? cardOfertaCodigoDiferente(cruce.detalle_codigo_posible_error, par)
                : cardOferta(cruce.oferta, cruce.estado, par, cruce);
        });
        ofertasSinLabor.forEach(oferta => {
            par++;
            html += cardLaborSinRegistro(oferta, par);
            html += `<div class="mdp-arrow-cell" aria-hidden="true"><span>&#8594;</span></div>`;
            html += cardOfertaSinLabor(oferta);
        });
        return html + `</div>`;
    }

    /* -------------------------------------------------------------------
       5. CREACIÓN Y CONTROL DEL MODAL
       ------------------------------------------------------------------- */
    function buildOverlay() {
        overlay = document.createElement('div');
        overlay.className = 'mdp-overlay';
        overlay.innerHTML = `
        <div class="mdp-modal" role="dialog" aria-modal="true" aria-label="Detalle de auditoría docente">
            <div class="mdp-header">
                <div class="mdp-avatar" id="mdp-avatar">--</div>
                <div class="mdp-header-info"><h2 id="mdp-nombre">Cargando...</h2><small id="mdp-sub"></small></div>
                <button type="button" class="mdp-close" id="mdp-close" title="Cerrar" aria-label="Cerrar">&times;</button>
            </div>
            <div class="mdp-kpis" id="mdp-kpis"></div>
            <div class="mdp-tabs-wrap">
                <div class="mdp-tabs" id="mdp-tabs" role="tablist" aria-label="Vistas del detalle de auditoría">
                    <button type="button" class="mdp-tab active" data-tab="docente" role="tab" aria-selected="true"><i class="fa-solid fa-chalkboard-user" aria-hidden="true"></i><span class="mdp-tab-text">Vista docente</span></button>
                    <button type="button" class="mdp-tab" data-tab="materia" role="tab" aria-selected="false"><i class="fa-solid fa-chart-column" aria-hidden="true"></i><span class="mdp-tab-text">Vista materia/grupo</span></button>
                </div>
            </div>
            <div class="mdp-body"><div class="mdp-tab-content active" id="tab-docente"></div><div class="mdp-tab-content" id="tab-materia"></div></div>
        </div>`;
        document.body.appendChild(overlay);
        
        // Event Listeners Globales
        overlay.addEventListener('click', evento => { 
            if (evento.target === overlay) cerrar(); 
            
            // Delegación de eventos para acordeones
            const accordionHeader = evento.target.closest('.mdp-accordion-toggle');
            if (accordionHeader) {
                const section = accordionHeader.closest('.mdp-section');
                if (section) {
                    section.classList.toggle('collapsed');
                }
            }
        });

        overlay.querySelector('#mdp-close').addEventListener('click', cerrar);
        document.addEventListener('keydown', evento => { if (evento.key === 'Escape' && overlay?.classList.contains('mdp-visible')) cerrar(); });
        
        overlay.querySelectorAll('.mdp-tab').forEach(tab => {
            tab.addEventListener('click', function () {
                const destino = this.dataset.tab;
                activarTab(destino);
                if (destino === 'materia' && currentContext) {
                    const contMateria = document.getElementById('tab-materia');
                    if (contMateria.dataset.cargado !== '1') cargarVistaMateriaGrupo(currentContext);
                }
            });
        });
    }

    function cerrar() {
        if (!overlay) return;
        overlay.classList.remove('mdp-visible');
        window.setTimeout(() => { if (overlay) overlay.style.display = 'none'; }, 180);
        currentContext = null;
    }

    function mostrarOverlay() {
        if (!overlay) buildOverlay();
        overlay.style.display = 'flex';
        window.requestAnimationFrame(() => overlay.classList.add('mdp-visible'));
    }

    function activarTab(destino) {
        overlay.querySelectorAll('.mdp-tab').forEach(tab => {
            const activo = tab.dataset.tab === destino;
            tab.classList.toggle('active', activo);
            tab.setAttribute('aria-selected', activo ? 'true' : 'false');
        });
        overlay.querySelectorAll('.mdp-tab-content').forEach(contenido => contenido.classList.toggle('active', contenido.id === `tab-${destino}`));
    }

    /* -------------------------------------------------------------------
       6. VISTA DOCENTE (Con Acordeones Replegados por Defecto)
       ------------------------------------------------------------------- */
    async function cargarVistaDocente(contexto) {
        const cont = document.getElementById('tab-docente');
        cont.innerHTML = `<div class="mdp-loading"><div class="mdp-spinner"></div>Cargando datos del docente...</div>`;
        const identificacion = String(contexto?.identificacion ?? '').trim();
        const periodo = String(contexto?.periodo ?? '').trim();
        if (!identificacion || !periodo) {
            cont.innerHTML = `<div class="mdp-error"><b>Error:</b> faltan la identificación o el período para consultar el detalle docente.</div>`;
            return;
        }
        try {
            const res = await fetch(`${API_URL}?action=docente_periodo&identificacion=${encodeURIComponent(identificacion)}&periodo=${encodeURIComponent(periodo)}`);
            const texto = await res.text();
            let json;
            try { json = JSON.parse(texto); } catch { throw new Error(`El servidor no devolvió JSON válido. HTTP ${res.status}. Inicio de respuesta: ${texto.slice(0, 180)}`); }
            if (!json.success) throw new Error(json.error || 'Error desconocido al consultar el docente.');

            const iniciales = String(json.docente ?? '').trim().split(/\s+/).filter(Boolean).slice(0, 2).map(p => p.charAt(0).toUpperCase()).join('');
            document.getElementById('mdp-avatar').textContent = iniciales || '--';
            document.getElementById('mdp-nombre').textContent = json.docente || 'Docente no encontrado';
            document.getElementById('mdp-sub').textContent = [`CC ${json.identificacion || identificacion}`, `Periodo ${json.periodo || periodo}`, json.tipo_contrato || '', json.departamento || ''].filter(Boolean).join(' · ');

            const r = json.resumen ?? {};
            const kpi = (color, etiqueta, valor, atenuarSiCero = true) => `<div class="mdp-kpi ${atenuarSiCero && !Number(valor) ? 'mdp-kpi-cero' : ''}"><span class="mdp-dot" style="background:${color}"></span><span>${esc(etiqueta)}</span><b>${esc(valor ?? 0)}</b></div>`;
            document.getElementById('mdp-kpis').innerHTML = kpi('#3b82f6', 'En Labor', r.total_labor, false) + kpi('#8b5cf6', 'En Oferta', r.total_oferta, false) + kpi('#10b981', 'Cruzan OK', r.ok) + kpi('#f59e0b', 'Programa diferente', r.programa_diferente) + kpi('#ef4444', 'Sin oferta', r.oferta_inexistente) + kpi('#6366f1', 'Revisar código materia', r.revisar_codigo_materia ?? 0) + kpi('#ec4899', 'Solo en oferta (sin Labor)', r.oferta_sin_labor) + kpi('#8b5cf6', 'Sin matrícula', r.matriculados_cero ?? 0);

            const cruces = Array.isArray(json.cruces) ? json.cruces : [];
            const ofertasSinLabor = json.oferta_sin_labor ?? json.ofertasinlabor ?? [];
            const codigoSeleccionado = normalizarCodigo(contexto.codigoMateria);
            const grupoSeleccionadoNorm = normalizarGrupo(contexto.grupo);
            const crucesMismaMateria = cruces.filter(c => normalizarCodigo(codigoDeCruce(c)) === codigoSeleccionado);
            const crucesOtrasMaterias = cruces.filter(c => normalizarCodigo(codigoDeCruce(c)) !== codigoSeleccionado);
            const ofertasMismaMateria = ofertasSinLabor.filter(o => normalizarCodigo(codigoDeOfertaSinLabor(o)) === codigoSeleccionado);
            const ofertasOtrasMaterias = ofertasSinLabor.filter(o => normalizarCodigo(codigoDeOfertaSinLabor(o)) !== codigoSeleccionado);
            const crucesGrupoSeleccionado = crucesMismaMateria.filter(c => normalizarGrupo(grupoDeCruce(c)) === grupoSeleccionadoNorm);
            const crucesOtrosGrupos = crucesMismaMateria.filter(c => normalizarGrupo(grupoDeCruce(c)) !== grupoSeleccionadoNorm);
            const ofertasGrupoSeleccionado = ofertasMismaMateria.filter(o => normalizarGrupo(grupoDeOfertaSinLabor(o)) === grupoSeleccionadoNorm);
            const ofertasOtrosGrupos = ofertasMismaMateria.filter(o => normalizarGrupo(grupoDeOfertaSinLabor(o)) !== grupoSeleccionadoNorm);
            const primerCruceGrupo = crucesGrupoSeleccionado[0];
            const primeraOfertaGrupo = ofertasGrupoSeleccionado[0];
            const nombreMateria = obtenerMateria(primerCruceGrupo?.labor) || obtenerMateria(primerCruceGrupo?.oferta) || obtenerMateria(primeraOfertaGrupo) || contexto.programa || 'Materia seleccionada';
            const existeGrupo = crucesGrupoSeleccionado.length > 0 || ofertasGrupoSeleccionado.length > 0;
            const existenOtrosGrupos = crucesOtrosGrupos.length > 0 || ofertasOtrosGrupos.length > 0;
            const existenOtrasMaterias = crucesOtrasMaterias.length > 0 || ofertasOtrasMaterias.length > 0;

            const totalOtrosGrupos = crucesOtrosGrupos.length + ofertasOtrosGrupos.length;
            const totalOtrasMaterias = crucesOtrasMaterias.length + ofertasOtrasMaterias.length;

            /* BLOQUE 1: Grupo Seleccionado (Abierto por defecto) */
            const bloqueGrupo = `<section class="mdp-section mdp-section-grupo">
                <header class="mdp-section-header">
                    <div class="mdp-section-header-main">
                        <i class="fa-solid fa-bullseye" aria-hidden="true"></i>
                        <div>
                            <h3 class="mdp-section-title"><span class="mdp-title-label">Grupo seleccionado</span> <small>${esc(contexto.codigoMateria || '')} · ${esc(contexto.grupo || '')}</small></h3>
                            <p class="mdp-section-subtitle"><span class="mdp-code-badge">${esc(contexto.codigoMateria || 'SIN CÓDIGO')}</span><span class="mdp-materia-nombre">${esc(nombreMateria)}</span><span class="mdp-grupo-label">Grupo:</span><span class="mdp-grupo-badge">${esc(contexto.grupo || 'No especificado')}</span></p>
                        </div>
                    </div>
                </header>
                <div class="mdp-section-body">${existeGrupo ? renderGrillaCruces(crucesGrupoSeleccionado, ofertasGrupoSeleccionado, contexto, true) : '<div class="mdp-section-empty">El grupo seleccionado no fue encontrado en el detalle del docente para el período consultado.</div>'}</div>
            </section>`;

            /* BLOQUE 2: Otros Grupos (Acordeón replegado) */
            const bloqueOtrosGrupos = `<section class="mdp-section mdp-section-hermanos collapsed">
                <header class="mdp-section-header mdp-accordion-toggle" title="Clic para desplegar / replegar">
                    <div class="mdp-section-header-main">
                        <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                        <div>
                            <h3 class="mdp-section-title"><span class="mdp-title-label">Otros grupos de la misma materia</span> <small>(${totalOtrosGrupos} registro${totalOtrosGrupos !== 1 ? 's' : ''})</small></h3>
                            <p class="mdp-section-subtitle"><span class="mdp-code-badge mdp-code-badge-suave">${esc(contexto.codigoMateria || 'SIN CÓDIGO')}</span><span class="mdp-materia-nombre">${esc(nombreMateria)}</span></p>
                        </div>
                    </div>
                    <div class="mdp-accordion-icon"><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></div>
                </header>
                <div class="mdp-section-body">${existenOtrosGrupos ? renderGrillaCruces(crucesOtrosGrupos, ofertasOtrosGrupos, contexto, false) : '<div class="mdp-section-empty">Este docente no tiene otros grupos de esta misma materia en el período.</div>'}</div>
            </section>`;

            /* BLOQUE 3: Otras Materias (Acordeón replegado) */
            const bloqueOtrasMaterias = `<section class="mdp-section mdp-section-other collapsed">
                <header class="mdp-section-header mdp-accordion-toggle" title="Clic para desplegar / replegar">
                    <div class="mdp-section-header-main">
                        <i class="fa-solid fa-list" aria-hidden="true"></i>
                        <div>
                            <h3 class="mdp-section-title"><span class="mdp-title-label">Otras materias del docente en el período</span> <small>(${totalOtrasMaterias} registro${totalOtrasMaterias !== 1 ? 's' : ''})</small></h3>
                            <p class="mdp-section-subtitle">Demás asignaciones del docente en materias distintas</p>
                        </div>
                    </div>
                    <div class="mdp-accordion-icon"><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></div>
                </header>
                <div class="mdp-section-body">${existenOtrasMaterias ? renderGrillaCruces(crucesOtrasMaterias, ofertasOtrasMaterias, contexto, false) : '<div class="mdp-section-empty">No hay otras materias para este docente en el período seleccionado.</div>'}</div>
            </section>`;

            const bandera = `<div class="mdp-view-flag docente"><i class="fa-solid fa-chalkboard-user" aria-hidden="true"></i><span>Estás viendo la Vista docente</span><span class="mdp-view-flag-sep">·</span><span>consolidado de Labor y Oferta para este docente en el período ${esc(periodo)}</span></div>`;
            cont.innerHTML = bandera + bloqueGrupo + bloqueOtrosGrupos + bloqueOtrasMaterias;
        } catch (error) {
            cont.innerHTML = `<div class="mdp-error"><b>Error:</b> ${esc(error.message)}</div>`;
        }
    }


    /* -------------------------------------------------------------------
    7. VISTA MATERIA / GRUPO (Ajuste: PE nulo/vacío tratado como 0)
    ------------------------------------------------------------------- */
    async function cargarVistaMateriaGrupo(contexto) {
        const cont = document.getElementById('tab-materia');
        cont.innerHTML = `<div class="mdp-loading"><div class="mdp-spinner"></div>Cargando detalle de materia/grupo...</div>`;

        const periodo = String(contexto?.periodo ?? '').trim();
        const codigoMateria = String(contexto?.codigoMateria ?? '').trim();
        const programa = String(contexto?.programa ?? '').trim();
        const grupo = String(contexto?.grupo ?? '').trim();

        if (!periodo || !codigoMateria || !programa || !grupo) {
            cont.innerHTML = `<div class="mdp-error"><b>Error:</b> faltan datos para consultar la vista materia/grupo.</div>`;
            return;
        }

        try {
            const res = await fetch(`${API_URL}?action=labor_materia_grupo&periodo=${encodeURIComponent(periodo)}&codigo_materia=${encodeURIComponent(codigoMateria)}&programa=${encodeURIComponent(programa)}&grupo=${encodeURIComponent(grupo)}`);
            const texto = await res.text();
            let json;
            try { 
                json = JSON.parse(texto); 
            } catch { 
                throw new Error(`El servidor no devolvió JSON válido. HTTP ${res.status}. Inicio de respuesta: ${texto.slice(0, 180)}`); 
            }

            if (!json.success) throw new Error(json.error || 'Error desconocido al consultar materia/grupo.');

            cont.dataset.cargado = '1';

            // TRATAMIENTO DE PE VACÍO / NULL COMO CERO
            const peRaw = json.pe_unico;
            const peNormalizado = (peRaw === null || peRaw === undefined || String(peRaw).trim() === '') ? 0 : Number(peRaw);

            const horasTotales = Number(json.horas_totales ?? 0);
            const excesoHoras = json.exceso_horas !== undefined ? Number(json.exceso_horas) : Math.max(0, horasTotales - peNormalizado);
            const hayAlerta = json.hay_alerta || excesoHoras > 0 || peNormalizado === 0;

            const alertaHtml = hayAlerta 
                ? `<div class="mdp-alerta-block danger">
                    <b>DUPLICIDAD DE GRUPO / EXCESO DE PE (PE = ${esc(peNormalizado)})</b><br>
                    La suma de horas teóricas (${esc(horasTotales)} h) supera el Plan de Estudios registrado (${esc(peNormalizado)} h). Verifique si hay un error en la denominación del grupo o si las horas deben distribuirse entre diferentes grupos.
                   </div>` 
                : `<div class="mdp-alerta-block success">
                    <b>DISTRIBUCIÓN DENTRO DEL PE</b><br>
                    La suma de horas teóricas (${esc(horasTotales)} h) está dentro del límite asignado por el Plan de Estudios (${esc(peNormalizado)} h).
                   </div>`;

            const resumen = `<div class="mdp-resumen">
                <div class="mdp-resumen-item">Materia<b>${esc(json.materia)}</b></div>
                <div class="mdp-resumen-item">Código<b>${esc(json.codigo_materia)}</b></div>
                <div class="mdp-resumen-item">Programa<b>${esc(json.programa)}</b></div>
                <div class="mdp-resumen-item">Grupo<b>${esc(json.grupo)}</b></div>
                <div class="mdp-resumen-item">Registros<b>${esc(json.cantidad_registros)}</b></div>
                <div class="mdp-resumen-item">Docentes<b>${esc(json.docentes_involucrados)}</b></div>
                <div class="mdp-resumen-item">Horas totales<b>${esc(horasTotales)}</b></div>
                <div class="mdp-resumen-item">PE único<b>${esc(peNormalizado)}</b></div>
                <div class="mdp-resumen-item">Exceso<b>${esc(excesoHoras > 0 ? excesoHoras : 0)}</b></div>
            </div>`;

            const registros = Array.isArray(json.registros) ? json.registros : [];
            const tabla = `<div class="mdp-table-wrap">
                <table class="mdp-table">
                    <thead>
                        <tr>
                            <th>Docente</th>
                            <th>Identificación</th>
                            <th>Programa</th>
                            <th>Grupo</th>
                            <th>Horas teóricas</th>
                            <th>PE</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${registros.length ? registros.map(registro => {
                            const peReg = (registro.pe === null || registro.pe === undefined || String(registro.pe).trim() === '') ? 0 : registro.pe;
                            return `<tr>
                                <td>${esc(registro.docente)}</td>
                                <td>${esc(registro.identificacion)}</td>
                                <td>${esc(registro.programa)}</td>
                                <td>${esc(registro.grupo)}</td>
                                <td>${esc(obtenerHoras(registro))}</td>
                                <td>${esc(peReg)}</td>
                            </tr>`;
                        }).join('') : '<tr><td colspan="6" style="text-align:center;color:#94a3b8">No hay registros para esta combinación.</td></tr>'}
                    </tbody>
                </table>
            </div>`;

            const bandera = `<div class="mdp-view-flag materia">
                <i class="fa-solid fa-chart-column" aria-hidden="true"></i>
                <span>Estás viendo la Vista materia/grupo</span>
                <span class="mdp-view-flag-sep">·</span>
                <span>detalle global de "${esc(json.materia)}" (grupo ${esc(json.grupo)}), sin importar el docente</span>
            </div>`;

            cont.innerHTML = bandera + resumen + alertaHtml + tabla;
        } catch (error) {
            cont.innerHTML = `<div class="mdp-error"><b>Error:</b> ${esc(error.message)}</div>`;
        }
    }
    /* -------------------------------------------------------------------
       8. FUNCIÓN PÚBLICA
       ------------------------------------------------------------------- */
    function abrirModalAuditoriaFila(contexto) {
        if (!contexto || !String(contexto.identificacion ?? '').trim() || !String(contexto.periodo ?? '').trim()) {
            console.error('Faltan parámetros obligatorios: identificacion y periodo.');
            return;
        }
        currentContext = {
            identificacion: String(contexto.identificacion ?? '').trim(),
            periodo: String(contexto.periodo ?? '').trim(),
            codigoMateria: String(contexto.codigoMateria ?? '').trim(),
            programa: String(contexto.programa ?? '').trim(),
            grupo: String(contexto.grupo ?? '').trim(),
            estadoAlerta: String(contexto.estadoAlerta ?? '').trim(),
            alertaLabor: String(contexto.alertaLabor ?? '').trim(),
        };
        if (!overlay) buildOverlay();
        document.getElementById('tab-docente').innerHTML = '';
        document.getElementById('tab-materia').innerHTML = '';
        document.getElementById('tab-materia').dataset.cargado = '0';
        document.getElementById('mdp-kpis').innerHTML = '';
        document.getElementById('mdp-avatar').textContent = '--';
        document.getElementById('mdp-nombre').textContent = 'Cargando...';
        document.getElementById('mdp-sub').textContent = '';
        const tabInicial = currentContext.alertaLabor === 'DUPLICIDAD_GRUPO_EXCESO_PE' ? 'materia' : 'docente';
        activarTab(tabInicial);
        mostrarOverlay();
        if (tabInicial === 'materia') {
            cargarVistaMateriaGrupo(currentContext);
            window.setTimeout(() => { if (currentContext) cargarVistaDocente(currentContext); }, 100);
        } else {
            cargarVistaDocente(currentContext);
        }
    }

    window.abrirModalAuditoriaFila = abrirModalAuditoriaFila;
})();