/* =====================================================================
 * modal_glosa.js
 * Módulo de Observaciones (glosas) para el dashboard de Auditoría Docente.
 * - Modal de creación: título + descripción + lista de filas seleccionadas.
 * - Modal de detalle: muestra las filas de una observación y botón para
 *   descargar el reporte en Word.
 * Sigue el mismo patrón de overlay + build-once usado en
 * modal_docente_periodo.js para mantener consistencia visual y de código.
 * ===================================================================== */
(function () {
    'use strict';

    const API_URL = '../api/api.php';
    let overlayCrear = null;
    let overlayDetalle = null;
    let filasParaGuardar = [];
    let periodoActualObs = null;

    /* ------------------------------------------------------------- */
    /* 1. ESTILOS                                                     */
    /* ------------------------------------------------------------- */
    const CSS = `
    .glosa-overlay {
        position: fixed; inset: 0; z-index: 9999;
        display: none; align-items: center; justify-content: center;
        padding: 20px; background: rgba(15,23,42,.65);
        backdrop-filter: blur(6px); opacity: 0; transition: opacity .2s ease-out;
    }
    .glosa-overlay.glosa-visible { opacity: 1; }
    .glosa-modal {
        display: flex; flex-direction: column; width: 100%; max-width: 720px;
        max-height: 88vh; overflow: hidden; border-radius: 14px; background: #fff;
        box-shadow: 0 25px 50px -12px rgba(0,0,0,.25);
        transform: translateY(12px) scale(.99); transition: transform .2s ease-out;
        font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    }
    .glosa-overlay.glosa-visible .glosa-modal { transform: translateY(0) scale(1); }
    .glosa-header {
        display: flex; align-items: center; gap: 12px; padding: 18px 22px;
        color: #fff; background: linear-gradient(135deg, #3730a3, #4338ca);
    }
    .glosa-header h2 { margin: 0; font-size: 17px; font-weight: 700; }
    .glosa-header small { display: block; margin-top: 2px; opacity: .85; font-size: 11.5px; }
    .glosa-close {
        width: 32px; height: 32px; margin-left: auto; border: 0; border-radius: 8px;
        background: rgba(255,255,255,.15); color: #fff; font-size: 18px; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
    }
    .glosa-close:hover { background: rgba(255,255,255,.28); }
    .glosa-body { flex: 1; overflow-y: auto; padding: 20px 22px; background: #f8fafc; }
    .glosa-label { display: block; font-size: 11.5px; font-weight: 700; color: #475569; margin-bottom: 5px; text-transform: uppercase; letter-spacing: .02em; }
    .glosa-input, .glosa-textarea {
        width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 9px 11px;
        font-size: 13px; font-family: inherit; box-sizing: border-box;
    }
    .glosa-input:focus, .glosa-textarea:focus { outline: none; border-color: #4338ca; box-shadow: 0 0 0 3px rgba(67,56,202,.15); }
    .glosa-field { margin-bottom: 16px; }
    .glosa-lista {
        border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; max-height: 220px;
        overflow-y: auto;
    }
    .glosa-fila-item {
        display: flex; align-items: center; justify-content: space-between; gap: 8px;
        padding: 8px 12px; border-bottom: 1px solid #f1f5f9; font-size: 12px;
    }
    .glosa-fila-item:last-child { border-bottom: 0; }
    .glosa-fila-tag {
        display: inline-block; padding: 1px 7px; border-radius: 999px; background: #eef2ff;
        color: #4338ca; font-size: 9.5px; font-weight: 700;
    }
    .glosa-footer {
        display: flex; justify-content: flex-end; gap: 8px; padding: 14px 22px;
        border-top: 1px solid #e2e8f0; background: #fff;
    }
    .glosa-btn {
        padding: 8px 16px; border-radius: 8px; font-size: 12.5px; font-weight: 700;
        cursor: pointer; border: 0; transition: filter .12s ease;
    }
    .glosa-btn:hover { filter: brightness(1.08); }
    .glosa-btn-cancelar { background: #e2e8f0; color: #475569; }
    .glosa-btn-guardar { background: #4338ca; color: #fff; }
    .glosa-btn-descargar { background: #15803d; color: #fff; }
    .glosa-btn:disabled { opacity: .5; cursor: not-allowed; }
    .glosa-error { padding: 10px 14px; border: 1px solid #fca5a5; border-radius: 8px; background: #fef2f2; color: #991b1b; font-size: 12.5px; margin-bottom: 12px; }
    .glosa-exito { padding: 10px 14px; border: 1px solid #a7f3d0; border-radius: 8px; background: #ecfdf5; color: #065f46; font-size: 12.5px; margin-bottom: 12px; }
    .glosa-kv { display: flex; flex-wrap: wrap; gap: 6px 16px; margin-bottom: 14px; padding: 10px 14px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 11.5px; color: #475569; }
    .glosa-kv b { color: #0f172a; }
    .glosa-estado-badge { display: inline-flex; padding: 2px 9px; border-radius: 999px; font-size: 10.5px; font-weight: 700; }
    .glosa-estado-abierta { background: #fef3c7; color: #b45309; }
    .glosa-estado-subsanada { background: #d1fae5; color: #047857; }
    .glosa-estado-cerrada { background: #e2e8f0; color: #475569; }
    .glosa-tabla { width: 100%; border-collapse: collapse; font-size: 11.5px; margin-top: 10px; }
    .glosa-tabla th { background: #f8fafc; color: #475569; padding: 8px 10px; text-align: left; border-bottom: 1px solid #e2e8f0; font-size: 10px; text-transform: uppercase; }
    .glosa-tabla td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; color: #1e293b; }
    `;
    const styleEl = document.createElement('style');
    styleEl.textContent = CSS;
    document.head.appendChild(styleEl);

    /* ------------------------------------------------------------- */
    /* 2. HELPERS                                                     */
    /* ------------------------------------------------------------- */
    function esc(valor) {
        return String(valor ?? '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    function etiquetaTipoAlerta(tipo) {
        const mapa = {
            'DUPLICIDAD_GRUPO_EXCESO_PE': 'Exceso de horas (PE)',
            'FALTA_GRUPO_EN_OFERTA': 'Falta grupo en Oferta',
            'REVISAR_CODIGO_MATERIA_DIFERENTE': 'Código materia diferente',
            'OFERTA_INEXISTENTE': 'Oferta inexistente',
            'LABOR_INEXISTENTE': 'Labor inexistente (solo Oferta)',
        };
        return mapa[tipo] || tipo || 'Sin clasificar';
    }

    function claseEstadoObs(estado) {
        if (estado === 'ABIERTA') return 'glosa-estado-abierta';
        if (estado === 'SUBSANADA') return 'glosa-estado-subsanada';
        return 'glosa-estado-cerrada';
    }

    /* ------------------------------------------------------------- */
    /* 3. MODAL DE CREACIÓN                                           */
    /* ------------------------------------------------------------- */
    function buildOverlayCrear() {
        overlayCrear = document.createElement('div');
        overlayCrear.className = 'glosa-overlay';
        overlayCrear.innerHTML = `
        <div class="glosa-modal" role="dialog" aria-modal="true" aria-label="Crear observación">
            <div class="glosa-header">
                <div>
                    <h2>Crear Observación</h2>
                    <small id="glosa-crear-sub"></small>
                </div>
                <button type="button" class="glosa-close" id="glosa-crear-close" aria-label="Cerrar">&times;</button>
            </div>
            <div class="glosa-body">
                <div id="glosa-crear-mensaje"></div>
                <div class="glosa-field">
                    <label class="glosa-label" for="glosa-input-titulo">Título</label>
                    <input type="text" id="glosa-input-titulo" class="glosa-input" placeholder="Ej: Revisar exceso de horas en Cálculo I" maxlength="255">
                </div>
                <div class="glosa-field">
                    <label class="glosa-label" for="glosa-input-descripcion">Descripción</label>
                    <textarea id="glosa-input-descripcion" class="glosa-textarea" rows="3" placeholder="Ej: Solicitar ajuste de grupos o códigos"></textarea>
                </div>
                <div class="glosa-field">
                    <label class="glosa-label">Filas seleccionadas (<span id="glosa-crear-count">0</span>)</label>
                    <div class="glosa-lista" id="glosa-crear-lista"></div>
                </div>
            </div>
            <div class="glosa-footer">
                <button type="button" class="glosa-btn glosa-btn-cancelar" id="glosa-crear-cancelar">Cancelar</button>
                <button type="button" class="glosa-btn glosa-btn-guardar" id="glosa-crear-guardar">Guardar Observación</button>
            </div>
        </div>`;
        document.body.appendChild(overlayCrear);

        overlayCrear.addEventListener('click', (e) => { if (e.target === overlayCrear) cerrarModalCrear(); });
        overlayCrear.querySelector('#glosa-crear-close').addEventListener('click', cerrarModalCrear);
        overlayCrear.querySelector('#glosa-crear-cancelar').addEventListener('click', cerrarModalCrear);
        overlayCrear.querySelector('#glosa-crear-guardar').addEventListener('click', guardarObservacion);
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && overlayCrear?.classList.contains('glosa-visible')) cerrarModalCrear();
        });
    }

    function cerrarModalCrear() {
        if (!overlayCrear) return;
        overlayCrear.classList.remove('glosa-visible');
        window.setTimeout(() => { if (overlayCrear) overlayCrear.style.display = 'none'; }, 180);
    }

    /**
     * Punto de entrada público. Recibe el arreglo de filas seleccionadas
     * (cada una con identificacion, periodo, codigo_materia, grupo,
     * estado_alerta_original) y el periodo activo del dashboard.
     */
    function abrirModalCrearObservacion(filas, periodo) {
        if (!overlayCrear) buildOverlayCrear();

        filasParaGuardar = Array.isArray(filas) ? filas : [];
        periodoActualObs = periodo;

        document.getElementById('glosa-input-titulo').value = '';
        document.getElementById('glosa-input-descripcion').value = '';
        document.getElementById('glosa-crear-mensaje').innerHTML = '';
        document.getElementById('glosa-crear-sub').textContent = `Periodo ${periodo}`;
        document.getElementById('glosa-crear-count').textContent = filasParaGuardar.length;

        document.getElementById('glosa-crear-lista').innerHTML = filasParaGuardar.map(f => `
            <div class="glosa-fila-item">
                <div>
                    <b>${esc(f.identificacion)}</b> &middot; Cód ${esc(f.codigo_materia)} &middot; Grupo ${esc(f.grupo)}
                </div>
                <span class="glosa-fila-tag">${esc(etiquetaTipoAlerta(f.estado_alerta_original))}</span>
            </div>
        `).join('') || '<div class="glosa-fila-item">No hay filas seleccionadas.</div>';

        overlayCrear.style.display = 'flex';
        window.requestAnimationFrame(() => overlayCrear.classList.add('glosa-visible'));
    }

    async function guardarObservacion() {
        const titulo = document.getElementById('glosa-input-titulo').value.trim();
        const descripcion = document.getElementById('glosa-input-descripcion').value.trim();
        const mensajeDiv = document.getElementById('glosa-crear-mensaje');
        mensajeDiv.innerHTML = '';

        if (!titulo) {
            mensajeDiv.innerHTML = '<div class="glosa-error">El título es obligatorio.</div>';
            return;
        }
        if (filasParaGuardar.length === 0) {
            mensajeDiv.innerHTML = '<div class="glosa-error">No hay filas seleccionadas.</div>';
            return;
        }

        const btnGuardar = document.getElementById('glosa-crear-guardar');
        btnGuardar.disabled = true;
        btnGuardar.textContent = 'Guardando...';

        try {
            const res = await fetch(`${API_URL}?action=guardar_observacion`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    periodo: periodoActualObs,
                    titulo,
                    descripcion,
                    filas: filasParaGuardar,
                }),
            });
            const json = await res.json();
            if (!json.success) throw new Error(json.error || 'Error desconocido al guardar la observación.');

            mensajeDiv.innerHTML = `<div class="glosa-exito">Observación #${json.id_observacion} creada con ${json.filas_guardadas} fila(s).</div>`;
            window.setTimeout(() => {
                cerrarModalCrear();
                if (typeof window.onObservacionGuardada === 'function') window.onObservacionGuardada();
            }, 900);
        } catch (err) {
            mensajeDiv.innerHTML = `<div class="glosa-error">${esc(err.message)}</div>`;
        } finally {
            btnGuardar.disabled = false;
            btnGuardar.textContent = 'Guardar Observación';
        }
    }

    /* ------------------------------------------------------------- */
    /* 4. MODAL DE DETALLE                                            */
    /* ------------------------------------------------------------- */
    function buildOverlayDetalle() {
        overlayDetalle = document.createElement('div');
        overlayDetalle.className = 'glosa-overlay';
        overlayDetalle.innerHTML = `
        <div class="glosa-modal" role="dialog" aria-modal="true" aria-label="Detalle de observación">
            <div class="glosa-header">
                <div>
                    <h2 id="glosa-detalle-titulo">Observación</h2>
                    <small id="glosa-detalle-sub"></small>
                </div>
                <button type="button" class="glosa-close" id="glosa-detalle-close" aria-label="Cerrar">&times;</button>
            </div>
            <div class="glosa-body" id="glosa-detalle-body">
                <div class="glosa-loading">Cargando...</div>
            </div>
            <div class="glosa-footer">
                <button type="button" class="glosa-btn glosa-btn-cancelar" id="glosa-detalle-cerrar">Cerrar</button>
                <button type="button" class="glosa-btn glosa-btn-descargar" id="glosa-detalle-descargar">
                    <i class="fa-solid fa-file-word"></i> Descargar Word
                </button>
            </div>
        </div>`;
        document.body.appendChild(overlayDetalle);

        overlayDetalle.addEventListener('click', (e) => { if (e.target === overlayDetalle) cerrarModalDetalle(); });
        overlayDetalle.querySelector('#glosa-detalle-close').addEventListener('click', cerrarModalDetalle);
        overlayDetalle.querySelector('#glosa-detalle-cerrar').addEventListener('click', cerrarModalDetalle);
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && overlayDetalle?.classList.contains('glosa-visible')) cerrarModalDetalle();
        });
    }

    function cerrarModalDetalle() {
        if (!overlayDetalle) return;
        overlayDetalle.classList.remove('glosa-visible');
        window.setTimeout(() => { if (overlayDetalle) overlayDetalle.style.display = 'none'; }, 180);
    }

    async function abrirModalDetalleObservacion(idObservacion) {
        if (!overlayDetalle) buildOverlayDetalle();

        const body = document.getElementById('glosa-detalle-body');
        body.innerHTML = '<div class="glosa-loading">Cargando detalle...</div>';
        document.getElementById('glosa-detalle-titulo').textContent = `Observación #${idObservacion}`;
        document.getElementById('glosa-detalle-sub').textContent = '';

        overlayDetalle.style.display = 'flex';
        window.requestAnimationFrame(() => overlayDetalle.classList.add('glosa-visible'));

        const btnDescargar = document.getElementById('glosa-detalle-descargar');
        btnDescargar.onclick = () => {
            window.open(`${API_URL}?action=exportar_observacion_word&id=${idObservacion}`, '_blank');
        };

        try {
            const res = await fetch(`${API_URL}?action=obtener_observacion&id=${idObservacion}`);
            const json = await res.json();
            if (!json.success) throw new Error(json.error || 'No se pudo cargar la observación.');

            const obs = json.observacion;
            const filas = json.filas;

            document.getElementById('glosa-detalle-titulo').textContent = obs.titulo;
            document.getElementById('glosa-detalle-sub').textContent = `Periodo ${obs.periodo} · Creada ${obs.fecha_creacion}`;

            const badgeEstado = `<span class="glosa-estado-badge ${claseEstadoObs(obs.estado)}">${esc(obs.estado)}</span>`;

            const tablaFilas = filas.length ? `
                <table class="glosa-tabla">
                    <thead><tr><th>Identificación</th><th>Código Materia</th><th>Grupo</th><th>Alerta Original</th></tr></thead>
                    <tbody>
                        ${filas.map(f => `<tr>
                            <td>${esc(f.identificacion)}</td>
                            <td>${esc(f.codigo_materia)}</td>
                            <td>${esc(f.grupo)}</td>
                            <td>${esc(etiquetaTipoAlerta(f.estado_alerta_original))}</td>
                        </tr>`).join('')}
                    </tbody>
                </table>` : '<p style="font-size:12px;color:#94a3b8;">Esta observación no tiene filas asociadas.</p>';

            body.innerHTML = `
                <div class="glosa-kv">
                    <span>Estado: ${badgeEstado}</span>
                    <span><b>${filas.length}</b> fila(s) vinculada(s)</span>
                </div>
                ${obs.descripcion ? `<p style="font-size:12.5px;color:#475569;">${esc(obs.descripcion)}</p>` : ''}
                ${tablaFilas}
            `;
        } catch (err) {
            body.innerHTML = `<div class="glosa-error">${esc(err.message)}</div>`;
        }
    }

    /* ------------------------------------------------------------- */
    /* 5. EXPOSICIÓN PÚBLICA                                          */
    /* ------------------------------------------------------------- */
    window.abrirModalCrearObservacion = abrirModalCrearObservacion;
    window.abrirModalDetalleObservacion = abrirModalDetalleObservacion;
})();
