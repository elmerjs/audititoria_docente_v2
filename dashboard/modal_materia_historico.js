/* =====================================================================
 * modal_materia_historico.js
 * Paleta de alto contraste para análisis histórico académico.
 *
 * Colores:
 * - Labor:        azul #12436D
 * - Oferta:       naranja #F46A25
 * - Matriculados: rosa oscuro #801650
 * - Cupos:        turquesa #28A197 (línea punteada)
 *
 * La selección separa las cuatro métricas por tono y luminosidad,
 * evitando las barras pastel y los pares rojo/verde confusos.
 * Requiere Chart.js 4 cargado antes de este archivo.
 * ===================================================================== */
(function () {
  'use strict';

  const API_URL = '../api/api.php';
  let overlay = null;
  let graficaMateria = null;

  const COLORES = {
    labor: '#12436D',
    laborBorde: '#0B2E4B',
    oferta: '#F46A25',
    ofertaBorde: '#C94F12',
    matriculados: '#801650',
    cupos: '#28A197',
    fondoLabor: 'rgba(18, 67, 109, .14)',
    fondoOferta: 'rgba(244, 106, 37, .16)',
    fondoMatriculados: 'rgba(128, 22, 80, .14)',
    fondoCupos: 'rgba(40, 161, 151, .16)'
  };

  const CSS = `
    .mmh-overlay{position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;padding:16px;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);opacity:0;transition:opacity .18s ease}
    .mmh-overlay.mmh-visible{opacity:1}
    .mmh-modal{display:flex;flex-direction:column;width:100%;max-width:1320px;max-height:94vh;overflow:hidden;border-radius:16px;background:#fff;box-shadow:0 25px 60px rgba(0,0,0,.35);transform:translateY(14px) scale(.98);transition:transform .18s ease}
    .mmh-overlay.mmh-visible .mmh-modal{transform:none}
    .mmh-header{display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:18px 22px;color:#fff;background:linear-gradient(135deg,#5b21b6,#8b5cf6)}
    .mmh-icon{display:flex;align-items:center;justify-content:center;flex:0 0 auto;width:52px;height:52px;border-radius:14px;background:rgba(255,255,255,.2);font-size:24px}
    .mmh-header-info{flex:1;min-width:200px}.mmh-header-info h2{margin:0;font-size:18px;line-height:1.25}.mmh-header-info small{display:block;margin-top:3px;opacity:.86;font-size:12.5px}
    .mmh-close{width:36px;height:36px;margin-left:auto;border:0;border-radius:50%;background:rgba(255,255,255,.18);color:#fff;font-size:22px;line-height:1;cursor:pointer}.mmh-close:hover{background:rgba(255,255,255,.32)}
    .mmh-body{flex:1;overflow-y:auto;padding:18px 22px}
    .mmh-chart-card{margin-bottom:16px;padding:14px 16px;border:1px solid #e2e8f0;border-radius:12px;background:#fff}.mmh-chart-title{margin:0 0 4px;color:#334155;font-size:13px;font-weight:700}.mmh-chart-subtitle{margin:0 0 10px;color:#64748b;font-size:11px}.mmh-chart-wrap{position:relative;width:100%;height:350px}.mmh-chart-wrap canvas{width:100%!important;height:100%!important}
    .mmh-periodo-seleccionado{margin-bottom:12px;padding:8px 12px;border:1px solid #c4b5fd;border-radius:7px;background:#ede9fe;color:#6d28d9;font-size:12px;font-weight:700}
    .mmh-insight{display:flex;align-items:flex-start;gap:10px;margin-bottom:16px;padding:12px 16px;border-radius:10px;font-size:13.5px;line-height:1.5}.mmh-insight-alerta{border:1px solid #fcd34d;background:#fef3c7;color:#92400e}.mmh-insight-ok{border:1px solid #a7f3d0;background:#ecfdf5;color:#065f46}.mmh-insight-info{border:1px solid #bfdbfe;background:#eff6ff;color:#1e40af}
    .mmh-legend{display:flex;flex-wrap:wrap;gap:16px;margin:4px 0 12px;color:#64748b;font-size:11px}.mmh-legend span{display:inline-flex;align-items:center;gap:5px}.mmh-sq{display:inline-block;width:10px;height:10px;border-radius:3px}
    .mmh-detail-card{margin-top:18px;overflow:hidden;border:1px solid #e2e8f0;border-radius:14px;background:#fff;box-shadow:0 2px 8px rgba(15,23,42,.04)}
    .mmh-detail-header{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px;border-bottom:1px solid #e2e8f0;background:linear-gradient(135deg,#f8fafc,#f5f3ff)}.mmh-detail-header h3{margin:0;color:#334155;font-size:14px;font-weight:800}.mmh-detail-header p{margin:3px 0 0;color:#64748b;font-size:11px}
    .mmh-parallel{display:grid;grid-template-columns:minmax(0,1fr) 30px minmax(0,1fr);gap:10px;align-items:start;padding:12px}.mmh-parallel-panel{min-width:0}.mmh-parallel-title{display:flex;align-items:center;gap:7px;margin:0 0 7px;color:#475569;font-size:11px;font-weight:800;letter-spacing:.07em;text-transform:uppercase}.mmh-count{padding:2px 7px;border-radius:999px;background:#e2e8f0;color:#475569;font-size:10px;letter-spacing:0}.mmh-parallel-arrow{display:flex;align-items:center;justify-content:center;min-height:38px;color:#94a3b8;font-size:19px}
    .mmh-detail-table-wrap{overflow:auto;max-height:440px;border:1px solid #e2e8f0;border-radius:8px;background:#fff}.mmh-detail-table{width:100%;min-width:790px;border-collapse:collapse;table-layout:fixed;font-size:10.5px}.mmh-detail-table th{position:sticky;top:0;z-index:1;padding:7px;border-bottom:2px solid #e2e8f0;background:#f8fafc;color:#475569;font-size:9px;font-weight:800;letter-spacing:.04em;line-height:1.15;text-align:left;text-transform:uppercase;white-space:nowrap}.mmh-labor-panel .mmh-detail-table th{border-top:3px solid ${COLORES.labor}}.mmh-oferta-panel .mmh-detail-table th{border-top:3px solid ${COLORES.oferta}}
    .mmh-detail-table td{height:28px;max-width:0;padding:4px 7px;border-bottom:1px solid #f1f5f9;color:#334155;line-height:1.2;vertical-align:middle;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.mmh-detail-table tr:last-child td{border-bottom:0}.mmh-detail-table tr:hover td{background:#fafafa}
    .mmh-truncate{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.mmh-profesor-doc{display:block;overflow:hidden;color:#64748b;font-size:9px;line-height:1.1;text-overflow:ellipsis;white-space:nowrap}.mmh-profesor-nombre{display:block;overflow:hidden;color:#334155;font-weight:700;line-height:1.1;text-overflow:ellipsis;white-space:nowrap}.mmh-materia-codigo{display:block;overflow:hidden;color:#64748b;font-size:9px;line-height:1.1;text-overflow:ellipsis;white-space:nowrap}.mmh-materia-nombre{display:block;overflow:hidden;color:#334155;font-weight:700;line-height:1.1;text-overflow:ellipsis;white-space:nowrap}
    .mmh-num-cell,.mmh-hour-cell,.mmh-pe-cell,.mmh-matriculados-cell,.mmh-cupo-cell{text-align:center;white-space:nowrap}.mmh-hour-cell{color:${COLORES.labor}!important;font-weight:800}.mmh-pe-cell{color:#047857!important;font-weight:900}.mmh-cupo-cell{color:${COLORES.cupos}!important;font-weight:800}.mmh-matriculados-cell{color:${COLORES.matriculados}!important;font-weight:800}
    .mmh-empty-detail{padding:22px 10px;color:#94a3b8;font-size:12px;text-align:center}.mmh-chip{display:inline-block;padding:2px 8px;border-radius:999px;font-size:10px;font-weight:700;white-space:nowrap}.mmh-up-big{background:#fee2e2;color:#b91c1c}.mmh-up{background:#fef3c7;color:#b45309}.mmh-down{background:#e0e7ff;color:#4338ca}.mmh-same{background:#f1f5f9;color:#64748b}.mmh-new{background:#fce7f3;color:#be185d}
    .mmh-summary-table{width:100%;border-collapse:collapse;font-size:12px}.mmh-summary-table th{padding:8px 10px;background:#f1f5f9;color:#475569;font-size:10px;text-align:left;text-transform:uppercase}.mmh-summary-table td{padding:8px 10px;border-bottom:1px solid #f1f5f9;color:#334155}
    .mmh-loading{padding:40px 0;color:#64748b;text-align:center}.mmh-spinner{width:34px;height:34px;margin:0 auto 10px;border:3px solid #e2e8f0;border-top-color:#8b5cf6;border-radius:50%;animation:mmh-spin .8s linear infinite}.mmh-error{padding:14px;border:1px solid #fecaca;border-radius:10px;background:#fef2f2;color:#b91c1c;font-size:13.5px;word-break:break-word}.mmh-empty{padding:30px 0;color:#94a3b8;font-size:14px;text-align:center}@keyframes mmh-spin{to{transform:rotate(360deg)}}
    @media(max-width:900px){.mmh-parallel{grid-template-columns:1fr;gap:8px}.mmh-parallel-arrow{min-height:18px;transform:rotate(90deg)}}@media(max-width:760px){.mmh-body{padding:12px}.mmh-chart-wrap{min-width:700px;height:300px}.mmh-chart-card{overflow-x:auto}.mmh-detail-table{min-width:780px}.mmh-detail-table-wrap{max-height:360px}.mmh-detail-header{align-items:flex-start;flex-direction:column}}
  `;

  const styleEl = document.createElement('style');
  styleEl.textContent = CSS;
  document.head.appendChild(styleEl);

  const esc = (valor) => String(valor ?? '').replace(/[&<>"']/g, (caracter) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
  }[caracter]));

  const numero = (valor, defecto = 0) => {
    const n = Number(valor);
    return Number.isFinite(n) ? n : defecto;
  };

  function valorFila(fila, ...nombres) {
    for (const nombre of nombres) {
      if (fila && fila[nombre] !== undefined && fila[nombre] !== null) return fila[nombre];
    }
    return '';
  }

  function textoOrden(valor) {
    return String(valor ?? '').trim().toLocaleUpperCase('es');
  }

  function ordenarDetalle(detalle) {
    return [...detalle].sort((a, b) => {
      const periodoA = textoOrden(valorFila(a, 'periodo'));
      const periodoB = textoOrden(valorFila(b, 'periodo'));
      if (periodoA !== periodoB) return periodoB.localeCompare(periodoA, 'es', { numeric: true, sensitivity: 'base' });
      const profesorA = textoOrden(valorFila(a, 'docente', 'nombre'));
      const profesorB = textoOrden(valorFila(b, 'docente', 'nombre'));
      if (profesorA !== profesorB) return profesorA.localeCompare(profesorB, 'es', { sensitivity: 'base' });
      return textoOrden(valorFila(a, 'programa')).localeCompare(textoOrden(valorFila(b, 'programa')), 'es', { sensitivity: 'base' });
    });
  }

  function truncado(valor) {
    const texto = String(valor ?? '');
    return `<span class="mmh-truncate" title="${esc(texto)}">${esc(texto || '—')}</span>`;
  }

  function dobleLinea(documento, nombre) {
    const doc = String(documento ?? '');
    const nom = String(nombre ?? '');
    const titulo = [doc, nom].filter(Boolean).join(' · ');
    return `<span class="mmh-profesor-doc" title="${esc(doc)}">${esc(doc || '—')}</span><span class="mmh-profesor-nombre" title="${esc(titulo)}">${esc(nom || '—')}</span>`;
  }

  function dobleLineaMateria(materia, codigo) {
    const mat = String(materia ?? '');
    const cod = String(codigo ?? '');
    const titulo = [mat, cod].filter(Boolean).join(' · ');
    return `<span class="mmh-materia-nombre" title="${esc(titulo)}">${esc(mat || '—')}</span><span class="mmh-materia-codigo" title="${esc(cod)}">${esc(cod || '—')}</span>`;
  }

  function chipVariacion(actual, anterior) {
    const a = numero(actual);
    if (anterior === null || anterior === undefined) return '<span class="mmh-chip mmh-same">1er dato</span>';
    const b = numero(anterior);
    if (b === 0 && a > 0) return `<span class="mmh-chip mmh-new">✚ aparece (+${esc(a)})</span>`;
    const diferencia = a - b;
    if (diferencia === 0) return '<span class="mmh-chip mmh-same">= sin cambio</span>';
    if (diferencia > 0) {
      const porcentaje = b > 0 ? Math.round((diferencia / b) * 100) : 100;
      return `<span class="mmh-chip ${diferencia >= 2 || porcentaje >= 50 ? 'mmh-up-big' : 'mmh-up'}">▲ +${esc(diferencia)} (${esc(porcentaje)}%)</span>`;
    }
    return `<span class="mmh-chip mmh-down">▼ ${esc(diferencia)}</span>`;
  }

  function insightPrincipal(historia, periodoSeleccionado) {
    const actual = historia.find((fila) => fila.periodo === periodoSeleccionado) || historia[historia.length - 1];
    if (!actual) return { tipo: 'info', texto: '<b>ℹ Sin datos:</b> no hay información para analizar.' };
    const indice = historia.indexOf(actual);
    let base = null;
    for (let i = indice - 1; i >= 0; i--) if (numero(historia[i].grupos_oferta) > 0) { base = historia[i]; break; }
    if (!base) return { tipo: 'info', texto: `<b>ℹ Sin base histórica:</b> ${esc(actual.periodo)} es el primer período con Oferta registrada para esta materia.` };
    const ga = numero(actual.grupos_oferta), gb = numero(base.grupos_oferta), ma = numero(actual.matriculados_oferta), mb = numero(base.matriculados_oferta), ca = numero(actual.cupos_oferta), d = ga - gb;
    const nota = indice - historia.indexOf(base) > 1 ? ` <b>Comparación especial:</b> no existía Oferta en los períodos intermedios; se comparó con ${esc(base.periodo)}.` : '';
    if (d <= 0) return { tipo: 'ok', texto: `<b>✔ Sin aumento de Oferta:</b> en ${esc(actual.periodo)} hay ${esc(ga)} grupos frente a ${esc(gb)} en ${esc(base.periodo)}.${nota}` };
    const salto = d >= 2 || (gb > 0 && ga / gb >= 1.5);
    if (!salto) return { tipo: 'ok', texto: `<b>✔ Variación moderada:</b> en ${esc(actual.periodo)} los grupos pasaron de ${esc(gb)} a ${esc(ga)}.${nota}` };
    const acompana = ma > 0 && (ga > 0 ? ma / ga : 0) >= (gb > 0 ? mb / gb : 0) * .9;
    const exigeCupo = ca > 0 && gb > 0 && ma > gb * ca;
    if (acompana || exigeCupo) return { tipo: 'ok', texto: `<b>✔ Salto justificado:</b> en ${esc(actual.periodo)} los grupos pasaron de ${esc(gb)} a ${esc(ga)} (+${esc(d)}) respecto a ${esc(base.periodo)}. Matriculados: ${esc(mb)} → ${esc(ma)}; cupos actuales: ${esc(ca)}. ${exigeCupo ? 'La demanda supera la capacidad de los grupos anteriores.' : 'La matrícula creció proporcionalmente.'}${nota}` };
    return { tipo: 'alerta', texto: `<b>⚠ Salto de Oferta detectado:</b> en ${esc(actual.periodo)} los grupos pasaron de ${esc(gb)} a ${esc(ga)} (+${esc(d)}) respecto a ${esc(base.periodo)}. Matriculados: ${esc(mb)} → ${esc(ma)}. La matrícula no justifica completamente el aumento.${nota}` };
  }

  const etiquetasPlugin = {
    id: 'mmhEtiquetas',
    afterDatasetsDraw(chart) {
      const ctx = chart.ctx;
      chart.data.datasets.forEach((dataset, datasetIndex) => {
        const meta = chart.getDatasetMeta(datasetIndex);
        if (meta.hidden) return;
        meta.data.forEach((elemento, indice) => {
          const valor = dataset.data[indice];
          if (valor === null || valor === undefined) return;
          const posicion = elemento.tooltipPosition();
          const esLinea = dataset.type === 'line';
          const texto = String(valor);
          const fondo = dataset.label === 'Labor — grupos' ? COLORES.fondoLabor : dataset.label === 'Oferta — grupos' ? COLORES.fondoOferta : dataset.label === 'Matriculados' ? COLORES.fondoMatriculados : COLORES.fondoCupos;
          ctx.save();
          ctx.font = '800 9px Arial';
          ctx.textAlign = 'center';
          ctx.textBaseline = 'middle';
          const ancho = ctx.measureText(texto).width + 8;
          const alto = 15;
          const desplazamiento = esLinea ? (dataset.label === 'Cupos' ? 13 : -12) : -8;
          const x = posicion.x, y = posicion.y + desplazamiento;
          ctx.fillStyle = fondo;
          ctx.beginPath();
          ctx.roundRect(x - ancho / 2, y - alto / 2, ancho, alto, 4);
          ctx.fill();
          ctx.fillStyle = dataset.borderColor || '#334155';
          ctx.fillText(texto, x, y + .5);
          ctx.restore();
        });
      });
    }
  };

  function destruirGrafica() { if (graficaMateria) { graficaMateria.destroy(); graficaMateria = null; } }

  function crearGrafica(canvas, historia) {
    if (!canvas || typeof Chart === 'undefined') return;
    destruirGrafica();
    graficaMateria = new Chart(canvas, {
      data: {
        labels: historia.map((fila) => fila.periodo),
        datasets: [
          { type: 'bar', label: 'Labor — grupos', data: historia.map((fila) => numero(fila.grupos_labor)), backgroundColor: COLORES.labor, borderColor: COLORES.laborBorde, borderWidth: 1, borderRadius: 4, yAxisID: 'y', order: 2 },
          { type: 'bar', label: 'Oferta — grupos', data: historia.map((fila) => numero(fila.grupos_oferta)), backgroundColor: COLORES.oferta, borderColor: COLORES.ofertaBorde, borderWidth: 1, borderRadius: 4, yAxisID: 'y', order: 2 },
          { type: 'line', label: 'Matriculados', data: historia.map((fila) => numero(fila.matriculados_oferta)), borderColor: COLORES.matriculados, backgroundColor: COLORES.matriculados, pointBackgroundColor: COLORES.matriculados, pointBorderColor: '#ffffff', pointBorderWidth: 1.5, pointRadius: 4, pointHoverRadius: 6, tension: .25, yAxisID: 'y1', order: 1 },
          { type: 'line', label: 'Cupos', data: historia.map((fila) => numero(fila.cupos_oferta)), borderColor: COLORES.cupos, backgroundColor: COLORES.cupos, pointBackgroundColor: COLORES.cupos, pointBorderColor: '#ffffff', pointBorderWidth: 1.5, pointRadius: 4, pointHoverRadius: 6, borderDash: [6, 4], tension: .25, yAxisID: 'y1', order: 1 }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 10, font: { size: 11 } } }, tooltip: { callbacks: { label(contexto) { return `${contexto.dataset.label}: ${contexto.parsed.y}`; } } } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Cantidad de grupos' } }, y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Matriculados / cupos' } } }
      },
      plugins: [etiquetasPlugin]
    });
  }

  function renderTablaResumen(historia) {
    let anterior = null;
    return `<div class="mmh-detail-table-wrap"><table class="mmh-summary-table"><thead><tr><th>Período</th><th>Grupos Labor</th><th>Docentes</th><th>Grupos Oferta</th><th>Matriculados</th><th>Cupos</th><th>Variación Oferta</th></tr></thead><tbody>${historia.map((fila) => { const variacion = chipVariacion(fila.grupos_oferta, anterior); anterior = numero(fila.grupos_oferta); return `<tr><td><b>${esc(fila.periodo)}</b></td><td>${esc(fila.grupos_labor)}</td><td>${esc(fila.docentes_labor)}</td><td>${esc(fila.grupos_oferta)}</td><td>${esc(fila.matriculados_oferta)}</td><td>${esc(fila.cupos_oferta)}</td><td>${variacion}</td></tr>`; }).join('')}</tbody></table></div>`;
  }

  function renderTablaLabor(detalle) {
    if (!detalle.length) return '<div class="mmh-empty-detail">No hay registros de Labor para esta materia.</div>';
    return `<div class="mmh-detail-table-wrap mmh-labor-panel"><table class="mmh-detail-table" style="min-width:960px"><colgroup><col style="width:64px"><col style="width:78px"><col style="width:100px"><col style="width:105px"><col style="width:145px"><col style="width:82px"><col style="width:145px"><col style="width:48px"><col style="width:64px"><col style="width:58px"></colgroup><thead><tr><th>Período</th><th>Facultad</th><th>Departamento</th><th>Programa</th><th>Profesor</th><th>Vinculación</th><th>Materia / Código</th><th>Grupo</th><th>Horas teóricas</th><th>PE</th></tr></thead><tbody>${detalle.map((fila) => { const periodo=valorFila(fila,'periodo'), facultad=valorFila(fila,'facultad'), departamento=valorFila(fila,'departamento'), programa=valorFila(fila,'programa'), identificacion=valorFila(fila,'identificacion','documento'), docente=valorFila(fila,'docente','nombre'), vinculacion=valorFila(fila,'tipo_contrato','tipocontrato','vinculacion'), materia=valorFila(fila,'materia','materia_labor','materialabor'), codigo=valorFila(fila,'codigo_materia','codigomateria'), grupo=valorFila(fila,'grupo'), horas=valorFila(fila,'horas_teoricas','horasteoricas'), pe=valorFila(fila,'pe'); return `<tr><td>${truncado(periodo)}</td><td>${truncado(facultad)}</td><td>${truncado(departamento)}</td><td>${truncado(programa)}</td><td>${dobleLinea(identificacion,docente)}</td><td>${truncado(vinculacion)}</td><td>${dobleLineaMateria(materia,codigo)}</td><td class="mmh-num-cell" title="${esc(grupo)}">${esc(grupo||'—')}</td><td class="mmh-hour-cell" title="${esc(horas)}">${esc(horas||'—')}</td><td class="mmh-pe-cell" title="${esc(pe)}">${esc(pe||'—')}</td></tr>`; }).join('')}</tbody></table></div>`;
  }

  function renderTablaOferta(detalle) {
    if (!detalle.length) return '<div class="mmh-empty-detail">No hay registros de Oferta para esta materia.</div>';
    return `<div class="mmh-detail-table-wrap mmh-oferta-panel"><table class="mmh-detail-table"><colgroup><col style="width:68px"><col style="width:145px"><col style="width:125px"><col style="width:155px"><col style="width:58px"><col style="width:72px"><col style="width:55px"></colgroup><thead><tr><th>Período</th><th>Profesor</th><th>Programa</th><th>Materia / Código</th><th>Grupo</th><th>Matric.</th><th>Cupo</th></tr></thead><tbody>${detalle.map((fila) => { const periodo=valorFila(fila,'periodo'), identificacion=valorFila(fila,'identificacion','documento'), docente=valorFila(fila,'docente','nombre'), programa=valorFila(fila,'programa'), materia=valorFila(fila,'materia','materia_oferta'), codigo=valorFila(fila,'codigo_materia','codigomateria'), grupo=valorFila(fila,'grupo'), matriculados=valorFila(fila,'matriculados'), cupo=fila.cupo===null||fila.cupo===undefined?'—':fila.cupo; return `<tr><td>${truncado(periodo)}</td><td>${dobleLinea(identificacion,docente)}</td><td>${truncado(programa)}</td><td>${dobleLineaMateria(materia,codigo)}</td><td class="mmh-num-cell" title="${esc(grupo)}">${esc(grupo||'—')}</td><td class="mmh-matriculados-cell" title="${esc(matriculados)}">${esc(matriculados||'0')}</td><td class="mmh-cupo-cell" title="${esc(cupo)}">${esc(cupo)}</td></tr>`; }).join('')}</tbody></table></div>`;
  }

  function unirDetalles(historia, propiedad) { return historia.flatMap((filaHistoria) => { const detalle=Array.isArray(filaHistoria[propiedad])?filaHistoria[propiedad]:[]; return detalle.map((fila)=>({...fila,periodo:fila.periodo??filaHistoria.periodo})); }); }

  function renderDetallesParalelos(historia) {
    const tieneDetalle = historia.some((fila) => Array.isArray(fila.detalle_labor) || Array.isArray(fila.detalle_oferta));
    if (!tieneDetalle) return `<section class="mmh-detail-card"><header class="mmh-detail-header"><div><h3>Detalle histórico</h3><p>El API todavía no devuelve el detalle fila a fila; se muestra el resumen disponible.</p></div></header><div style="padding:12px">${renderTablaResumen(historia)}</div></section>`;
    const detalleLabor = ordenarDetalle(unirDetalles(historia, 'detalle_labor'));
    const detalleOferta = ordenarDetalle(unirDetalles(historia, 'detalle_oferta'));
    return `<section class="mmh-detail-card"><header class="mmh-detail-header"><div><h3>Detalle de Labor y Oferta</h3><p>Orden: período descendente, profesor y programa. Los textos extensos se consultan al pasar el cursor.</p></div></header><div class="mmh-parallel"><div class="mmh-parallel-panel"><p class="mmh-parallel-title"><span>📋 Labor</span><span class="mmh-count">${esc(detalleLabor.length)}</span></p>${renderTablaLabor(detalleLabor)}</div><div class="mmh-parallel-arrow" aria-hidden="true">⇄</div><div class="mmh-parallel-panel"><p class="mmh-parallel-title"><span>🏫 Oferta</span><span class="mmh-count">${esc(detalleOferta.length)}</span></p>${renderTablaOferta(detalleOferta)}</div></div></section>`;
  }

  function construirOverlay() {
    overlay = document.createElement('div');
    overlay.className = 'mmh-overlay';
    overlay.innerHTML = `<div class="mmh-modal" role="dialog" aria-modal="true" aria-label="Histórico de materia"><div class="mmh-header"><div class="mmh-icon" aria-hidden="true">📊</div><div class="mmh-header-info"><h2 id="mmh-nombre">Cargando...</h2><small id="mmh-sub"></small></div><button type="button" class="mmh-close" id="mmh-close" title="Cerrar" aria-label="Cerrar">×</button></div><div class="mmh-body" id="mmh-body"><div class="mmh-loading"><div class="mmh-spinner"></div>Cargando histórico...</div></div></div>`;
    document.body.appendChild(overlay);
    overlay.addEventListener('click', (evento) => { if (evento.target === overlay) cerrar(); });
    overlay.querySelector('#mmh-close').addEventListener('click', cerrar);
  }

  function cerrar() { if (!overlay) return; destruirGrafica(); overlay.classList.remove('mmh-visible'); window.setTimeout(() => { if (overlay) overlay.style.display = 'none'; }, 180); }
  function abrirOverlay() { if (!overlay) construirOverlay(); overlay.style.display = 'flex'; window.requestAnimationFrame(() => overlay.classList.add('mmh-visible')); }
  document.addEventListener('keydown', (evento) => { if (evento.key === 'Escape' && overlay && overlay.classList.contains('mmh-visible')) cerrar(); });

  async function abrirMateriaHistorico(codigoMateria, periodoSeleccionado = '') {
    const codigo = String(codigoMateria ?? '').trim();
    if (!codigo) return;
    abrirOverlay();
    const body = document.getElementById('mmh-body');
    body.innerHTML = '<div class="mmh-loading"><div class="mmh-spinner"></div>Cargando histórico de la materia...</div>';
    try {
      const respuesta = await fetch(`${API_URL}?action=materia_historico&codigo_materia=${encodeURIComponent(codigo)}`);
      const texto = await respuesta.text();
      let json;
      try { json = JSON.parse(texto); } catch (error) { throw new Error(`El servidor no devolvió JSON válido. HTTP ${respuesta.status}. Inicio: ${texto.slice(0,180)}`); }
      if (!json.success) throw new Error(json.error || 'No fue posible cargar el histórico de la materia.');
      const historia = Array.isArray(json.historia) ? json.historia : [];
      if (!historia.length) { body.innerHTML = '<div class="mmh-empty">No hay información histórica para esta materia.</div>'; return; }
      document.getElementById('mmh-nombre').textContent = json.materia || 'Materia';
      document.getElementById('mmh-sub').textContent = `Código ${json.codigo_materia || codigo} · ${json.total_periodos || historia.length} período(s)`;
      const periodoActual = periodoSeleccionado || historia[historia.length - 1].periodo;
      const insight = insightPrincipal(historia, periodoActual);
      body.innerHTML = `<div class="mmh-periodo-seleccionado">📌 Período analizado: ${esc(periodoActual)}</div><div class="mmh-insight mmh-insight-${esc(insight.tipo)}"><span aria-hidden="true">${insight.tipo==='alerta'?'⚠':insight.tipo==='ok'?'✔':'ℹ'}</span><div>${insight.texto}</div></div><section class="mmh-chart-card"><h3 class="mmh-chart-title">Evolución histórica</h3><p class="mmh-chart-subtitle">Barras: grupos · Línea rosa: matriculados · Línea turquesa punteada: cupos.</p><div class="mmh-chart-wrap"><canvas id="mmh-chart"></canvas></div></section><div class="mmh-legend"><span><i class="mmh-sq" style="background:${COLORES.labor}"></i>Grupos Labor</span><span><i class="mmh-sq" style="background:${COLORES.oferta}"></i>Grupos Oferta</span><span><i class="mmh-sq" style="background:${COLORES.matriculados}"></i>Matriculados</span><span><i class="mmh-sq" style="background:${COLORES.cupos}"></i>Cupos</span></div>${renderDetallesParalelos(historia)}`;
      crearGrafica(document.getElementById('mmh-chart'), historia);
    } catch (error) {
      body.innerHTML = `<div class="mmh-error"><b>Error:</b> ${esc(error.message)}</div>`;
    }
  }

  window.abrirModalMateriaHistorico = abrirMateriaHistorico;
  window.abrirModalMateria = abrirMateriaHistorico;
  window.cerrarModalMateriaHistorico = cerrar;
})();
