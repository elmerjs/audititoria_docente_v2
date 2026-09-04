<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Auditoría Docente - Universidad del Cauca</title>
<!-- Tailwind CSS por CDN -->
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    /* -----------------------------------------------------------------
       CONTROL DE DESBORDAMIENTO EN LA TABLA PRINCIPAL
       La tabla usa table-fixed con anchos proporcionales (ver <colgroup>).
       Los campos con texto potencialmente largo (Facultad/Depto, Programa,
       Materia) truncan con elipsis y exponen el texto completo mediante
       title= (tooltip nativo).
       ----------------------------------------------------------------- */
    .cell-clip {
        display: block;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .cell-clip-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        max-width: 100%;
        line-height: 1.3;
    }
    #tbl-body td {
        vertical-align: top;
    }
    /* Botones de acción compactos, en una sola fila, sin envolver */
    .btn-accion-compacta {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        white-space: nowrap;
        padding: 4px 7px;
        font-size: 10px;
        line-height: 1.2;
        font-weight: 700;
        border-radius: 5px;
        color: #ffffff;
        box-shadow: 0 1px 2px rgba(0,0,0,.15);
        transition: filter .12s ease;
    }
    .btn-accion-compacta:hover {
        filter: brightness(1.08);
    }
    /* Badge PE Cero - estilo suave */
    .badge-pe-cero {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        padding: 1px 7px;
        border-radius: 999px;
        background: #fef2f2;
        border: 1px solid #fca5a5;
        color: #b91c1c;
        font-size: 9px;
        font-weight: 700;
        white-space: nowrap;
        cursor: help;
        letter-spacing: 0.02em;
    }
    .badge-pe-cero i {
        font-size: 8px;
    }
    /* Animaciones del modal SIMCA */
    #modal-simca.opacity-100 { opacity: 1; }
    #modal-simca-content.scale-100 { transform: scale(1); }

    /* Dropzone drag over */
    #simca-dropzone.dragover {
        border-color: #2563eb !important;
        background-color: #eff6ff !important;
    }
    #simca-dropzone.dragover i {
        color: #2563eb !important;
    }
    .alertas-stack {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 5px;
    width: 100%;
    max-width: 190px;
    margin: 0 auto;
}

.badge-alerta {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 4px 9px;
    border-radius: 999px;
    font-size: 9.5px;
    font-weight: 700;
    letter-spacing: .01em;
    white-space: nowrap;
    line-height: 1.25;
    border: 1px solid transparent;
    box-sizing: border-box;
    width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* El badge principal (estado del cruce Labor/Oferta) es clicable */
.badge-alerta-principal {
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(0,0,0,.06);
    transition: filter .12s ease, transform .12s ease, box-shadow .12s ease;
}
.badge-alerta-principal:hover {
    filter: brightness(.97);
    transform: translateY(-1px);
    box-shadow: 0 3px 6px rgba(0,0,0,.10);
}

/* Badges secundarios (duplicidad, PE 0, etc.) no son clicables por
   sí mismos: comparten el contexto del badge principal. */
.badge-alerta-secundario { cursor: help; }

/* Ensancha la celda de Alertas y evita que el resto del layout
   de la tabla se apriete o se salga de la tarjeta blanca. */
#tbl-body td.celda-alertas {
    vertical-align: middle;
    padding-top: 8px;
    padding-bottom: 8px;
}

/* Asegura que el contenedor blanco de la tabla nunca permita que
   su contenido interno rompa el border-radius/scroll. */
.tabla-scroll-wrap {
    overflow-x: auto;
    overflow-y: visible;
}
    
#tbl-body td, thead th {
    box-sizing: border-box;
}

thead th {
    white-space: normal;
    line-height: 1.2;
    vertical-align: middle;
    word-break: break-word;
}

/* Botones de acción: ahora ICON-ONLY (sin texto) para que quepan
   siempre, sin importar el ancho de columna. El texto se conserva
   como tooltip nativo (title). */
.btn-accion-icono {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 7px;
    color: #fff;
    font-size: 12px;
    box-shadow: 0 1px 2px rgba(0,0,0,.15);
    transition: filter .12s ease, transform .12s ease;
    flex-shrink: 0;
}
.btn-accion-icono:hover {
    filter: brightness(1.1);
    transform: translateY(-1px);
}

/* Contenedor de la columna Acciones: dos botones icono uno al lado
   del otro, siempre alineados y sin envolver. */
.acciones-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

/* Botón "Crear Observación": contador como badge circular en vez
   de paréntesis pegados al texto (se ve más limpio / profesional). */
.btn-crear-obs {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-crear-obs .contador-obs {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    border-radius: 999px;
    background: rgba(255,255,255,.25);
    font-size: 10.5px;
    font-weight: 800;
}
    .tabla-scroll-wrap {
    overflow-x: auto;
    overflow-y: visible;
}
.tabla-scroll-wrap table {
    min-width: 0 !important; /* anula cualquier min-width heredado */
}

/* Columna de Alertas: al ser más angosta (20% en vez de 210px fijos),
   los badges se acortan con elipsis en vez de forzar ancho. */
.alertas-stack {
    max-width: 100%;
}
.badge-alerta {
    font-size: 9px;
    padding: 3px 6px;
}

/* Encabezados de columnas angostas (Observación / Acciones / Horas)
   se ven mejor centrados y en 1-2 líneas cortas, no una sola palabra
   larga que se desborda. */
thead th {
    font-size: 10px;
    padding-left: 6px;
    padding-right: 6px;
}

/* Reduce un poco el padding general de las celdas para ganar aire
   interno sin necesitar más ancho de columna. */
#tbl-body td {
    padding-left: 10px;
    padding-right: 10px;
}
</style>
</head>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<body class="bg-gray-100 text-gray-800 font-sans">

<!-- Encabezado -->
<!-- Encabezado Principal -->
<header class="bg-gradient-to-r from-slate-900 via-blue-950 to-slate-900 text-white border-b border-slate-800/80 shadow-md sticky top-0 z-30">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3.5 flex flex-wrap justify-between items-center gap-4">
        
        <!-- Identificador / Título -->
        <div class="flex items-center space-x-3">
            <div class="p-2 bg-blue-600/20 border border-blue-500/30 rounded-lg text-blue-400 flex items-center justify-center">
                <i class="fa-solid fa-chart-line text-lg"></i>
            </div>
            <div>
                <h1 class="text-lg font-bold tracking-tight text-slate-100 flex items-center gap-2">
                    Dashboard Auditoría 
                    <span class="text-xs font-normal px-2 py-0.5 rounded bg-blue-500/10 text-blue-300 border border-blue-500/20">
                        Labor vs Oferta
                    </span>
                </h1>
                <p class="text-xs text-slate-400 hidden sm:block">Consolidación y verificación de asignación académica</p>
            </div>
        </div>

        <!-- Acciones y Estado -->
        <div class="flex items-center gap-3 ms-auto sm:ms-0">
            
            <!-- Badge Período Activo -->
            <div class="inline-flex items-center gap-2 bg-slate-800/80 border border-slate-700/60 text-slate-300 text-xs font-medium px-3 py-1.5 rounded-lg shadow-inner">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
                <span id="lbl-periodo-activo" class="tracking-wide">Periodo: Cargando...</span>
            </div>

            <!-- Botón Importar SIMCA -->
            <button onclick="abrirModalSimca()" 
                type="button"
                class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-500 active:bg-blue-700 text-white text-xs font-semibold px-3.5 py-1.5 rounded-lg shadow-sm hover:shadow-blue-500/20 transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 focus:ring-offset-slate-900 cursor-pointer">
                <i class="fa-solid fa-cloud-arrow-up text-xs"></i>
                <span>Importar SIMCA</span>
            </button>

        </div>

    </div>
</header>
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

    <!-- Panel de Filtros Superiores -->
    <div class="bg-white p-5 rounded-lg shadow-md mb-6 border-l-4 border-blue-600">
        <h2 class="text-md font-semibold text-gray-700 mb-3"><i class="fa-solid fa-filter mr-1"></i> Filtros de Selección</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">Periodo</label>
                <select id="flt-periodo" onchange="filasSeleccionadas.clear(); actualizarBotonCrearObservacion(); cargarAlertas(1)" class="w-full text-xs border border-gray-300 rounded p-2 focus:ring focus:ring-blue-300"></select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">Facultad</label>
                <select id="flt-facultad" onchange="actualizarDepartamentosPorFacultad(); cargarAlertas(1);" class="w-full text-xs border border-gray-300 rounded p-2 focus:ring focus:ring-blue-300">
                    <option value="">-- Todas --</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">Departamento</label>
                <select id="flt-depto" onchange="actualizarFacultadPorDepartamento(); cargarAlertas(1);" class="w-full text-xs border border-gray-300 rounded p-2 focus:ring focus:ring-blue-300">
                    <option value="">-- Todos --</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">Programa</label>
                <select id="flt-programa" onchange="cargarAlertas(1)" class="w-full text-xs border border-gray-300 rounded p-2 focus:ring focus:ring-blue-300">
                    <option value="">-- Todos --</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">Estado Alerta</label>
                <select id="flt-estado" onchange="cargarAlertas(1)" class="w-full text-xs border border-gray-300 rounded p-2 focus:ring focus:ring-blue-300">
                    <option value="">-- Todos los estados --</option>
                    <option value="OK">OK / Consistentes</option>
                    <option value="OFERTA_INEXISTENTE">Oferta Inexistente</option>
                    <option value="FALTA_GRUPO_EN_OFERTA">Grupo sin oferta</option>
                    <option value="REVISAR_PROGRAMA_DIFERENTE">Programa Diferente / Similar</option>
                    <option value="LABOR_INEXISTENTE">No existe en Labor</option>
                    <option value="REVISAR_CODIGO_MATERIA_DIFERENTE">Revisar código de materia</option>
                    <option value="PE_CERO">PE = 0</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">Buscar (Docente/Materia)</label>
                <input type="text" id="flt-busqueda" onkeyup="debounceAlertas()" placeholder="Nombre, cédula, materia..." class="w-full text-xs border border-gray-300 rounded p-2 focus:ring focus:ring-blue-300">
            </div>
        </div>
        <p class="text-10px text-gray-400 mt-2">
            <i class="fa-solid fa-circle-info mr-1"></i>
            Nota: al filtrar por Facultad, Departamento o Vinculación, no se muestran registros que existen solo en Oferta (esos atributos no existen en Oferta).
        </p>
    </div>

    <!-- Tarjetas KPI Top -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
        <div class="bg-white p-4 rounded-lg shadow border-b-4 border-gray-500">
            <p class="text-xs font-bold text-gray-500 uppercase">Total Registros</p>
            <p class="text-2xl font-bold text-gray-800" id="kpi-total">0</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow border-b-4 border-green-500">
            <p class="text-xs font-bold text-green-600 uppercase">Consistentes OK</p>
            <p class="text-2xl font-bold text-green-700" id="kpi-ok">0</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow border-b-4 border-red-500">
            <p class="text-xs font-bold text-red-600 uppercase">Oferta Inexistente</p>
            <p class="text-2xl font-bold text-red-700" id="kpi-critico">0</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow border-b-4 border-amber-500">
            <p class="text-xs font-bold text-amber-600 uppercase">Prestación de Servicio</p>
            <p class="text-2xl font-bold text-amber-700" id="kpi-servicio">0</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow border-b-4 border-pink-500">
            <p class="text-xs font-bold text-pink-600 uppercase">Labor Inexistente</p>
            <p class="text-2xl font-bold text-pink-700" id="kpi-labor-inexistente">0</p>
        </div>
        <div class="bg-white p-4 rounded-lg shadow border-b-4 border-indigo-500">
            <p class="text-xs font-bold text-indigo-600 uppercase">Revisar Código Materia</p>
            <p class="text-2xl font-bold text-indigo-700" id="kpi-codigo-materia">0</p>
        </div>
    </div>

    <!-- Tabla Dinámica con Paginación -->
    <div class="bg-white rounded-lg shadow overflow-hidden mb-8">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
            <div class="flex items-center space-x-2">
                <span class="text-xs font-bold text-gray-600">Mostrar:</span>
                <select id="flt-registros-pagina" onchange="cargarAlertas(1)" class="text-xs border border-gray-300 rounded p-1">
                    <option value="10">10 por página</option>
                    <option value="25" selected>25 por página</option>
                    <option value="50">50 por página</option>
                    <option value="100">100 por página</option>
                </select>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-600 font-semibold" id="lbl-count-resultados">Mostrando 0 resultados</span>
                
                
                <button id="btn-crear-observacion" onclick="abrirModalGlosa()" disabled
                    class="btn-crear-obs bg-indigo-700 hover:bg-indigo-800 disabled:opacity-40 disabled:cursor-not-allowed text-white text-xs font-bold py-1.5 px-3 rounded shadow">
                    <i class="fa-solid fa-paperclip"></i>
                    <span>Crear Observación</span>
                    <span class="contador-obs" id="lbl-count-seleccionadas">0</span>
                </button>
                <button onclick="exportarExcel()" title="Descargar en Excel todos los registros del periodo con los filtros actuales, incluyendo Estado Alerta y Alerta Histórica" class="bg-green-700 hover:bg-green-800 text-white text-xs font-bold py-1.5 px-3 rounded shadow inline-flex items-center gap-1">
                    <i class="fa-solid fa-file-excel"></i> Exportar Excel
                </button>
            </div>
        </div>
       
<div class="overflow-x-auto tabla-scroll-wrap">
    <table class="w-full table-fixed text-left text-xs text-gray-600">
        <colgroup>
            <col style="width: 3%">   <!-- checkbox -->
            <col style="width: 12%">  <!-- docente -->
            <col style="width: 10%">  <!-- facultad/depto -->
            <col style="width: 12%">  <!-- programa -->
            <col style="width: 14%">  <!-- materia/código/grupo -->
            <col style="width: 4%">   <!-- horas -->
            <col style="width: 20%">  <!-- alertas -->
            <col style="width: 8%">   <!-- alerta histórica -->
            <col style="width: 7%">   <!-- observación -->
            <col style="width: 10%">  <!-- acciones (2 iconos de 28px caben sobrados) -->
        </colgroup>
<thead class="bg-gray-800 text-white uppercase text-[11px] tracking-wider">
    <tr>
        <th class="py-3 px-2 text-center">
            <input type="checkbox" id="chk-marcar-todos" onchange="toggleMarcarTodos(this)" class="cursor-pointer">
        </th>
        <th data-col="docente" onclick="ordenarPor('docente')" class="py-3 px-4 cursor-pointer select-none hover:bg-gray-700">Docente / Cédula <span class="sort-arrow text-blue-300"></span></th>
        <th data-col="facultad" onclick="ordenarPor('facultad')" class="py-3 px-4 cursor-pointer select-none hover:bg-gray-700">Facultad / Depto <span class="sort-arrow text-blue-300"></span></th>
        <th data-col="programa" onclick="ordenarPor('programa')" class="py-3 px-4 cursor-pointer select-none hover:bg-gray-700">Programa (Labor) <span class="sort-arrow text-blue-300"></span></th>
        <th data-col="materia" onclick="ordenarPor('materia')" class="py-3 px-4 cursor-pointer select-none hover:bg-gray-700">Materia / Cód / Grupo <span class="sort-arrow text-blue-300"></span></th>
        <th data-col="horas" onclick="ordenarPor('horas')" class="py-3 px-4 text-center cursor-pointer select-none hover:bg-gray-700">Horas <span class="sort-arrow text-blue-300"></span></th>
        <th data-col="alertas" onclick="ordenarPor('alertas')" class="py-3 px-4 text-center cursor-pointer select-none hover:bg-gray-700" title="Resultado del cruce Labor vs Oferta y validaciones internas de Labor.">Alertas <span class="sort-arrow text-blue-300"></span></th>
        <th data-col="historica" onclick="ordenarPor('historica')" class="py-3 px-4 text-center cursor-pointer select-none hover:bg-gray-700">Alerta Histórica <span class="sort-arrow text-blue-300"></span></th>
        <th class="py-3 px-4 text-center">Observación</th>
        <th class="py-3 px-4 text-center">Acciones</th>
    </tr>
</thead>
<tbody id="tbl-body" class="divide-y divide-gray-200">
    <!-- Se llena dinámicamente vía JavaScript -->
</tbody>
            </table>
        </div>
        <!-- Barra de Paginación -->
        <div class="px-6 py-3 bg-gray-50 border-t border-gray-200 flex justify-between items-center" id="panel-paginacion">
            <button id="btn-prev" onclick="cambiarPagina(-1)" class="px-3 py-1 bg-gray-200 hover:bg-gray-300 text-gray-700 text-xs rounded font-bold disabled:opacity-50">&laquo; Anterior</button>
            <span id="lbl-pagina-info" class="text-xs text-gray-600 font-semibold">Página 1 de 1</span>
            <button id="btn-next" onclick="cambiarPagina(1)" class="px-3 py-1 bg-gray-200 hover:bg-gray-300 text-gray-700 text-xs rounded font-bold disabled:opacity-50">Siguiente &raquo;</button>
        </div>
    </div>

    <!-- Modal Radiografía Docente -->
    <div id="modal-radiografia" class="fixed inset-0 bg-black bg-opacity-50 hidden flex justify-center items-center p-4 z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto p-6">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h3 class="text-lg font-bold text-gray-800" id="modal-docente-nombre">Radiografía del Docente</h3>
                <button onclick="cerrarModal()" class="text-gray-500 hover:text-red-500 font-bold text-xl">&times;</button>
            </div>
            <div id="modal-contenido">
                <p class="text-xs text-gray-500">Cargando datos del docente...</p>
            </div>
        </div>
    </div>
</div>
<!-- Modal Carga SIMCA -->
<div id="modal-simca" class="fixed inset-0 bg-black/60 hidden justify-center items-center p-4 z-50 backdrop-blur-sm transition-opacity duration-300 opacity-0">

    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden transform scale-95 transition-transform duration-300" id="modal-simca-content">
        
        <!-- Header -->
        <div class="bg-blue-900 px-6 py-4 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center">
                    <i class="fa-solid fa-file-import text-white text-sm"></i>
                </div>
                <div>
                    <h3 class="text-white font-bold text-sm">Cargar Periodo SIMCA</h3>
                    <p class="text-blue-200 text-[10px]">Importación de Labor y Oferta</p>
                </div>
            </div>
            <button onclick="cerrarModalSimca()" class="text-blue-200 hover:text-white transition-colors w-8 h-8 flex items-center justify-center rounded-lg hover:bg-white/10">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="p-6 space-y-5">
            
            <!-- Periodo -->
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1.5">
                    <i class="fa-regular fa-calendar mr-1 text-blue-600"></i> Período Académico
                </label>
                <input type="text" id="simca-periodo" value="2026.2" 
                    class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all font-mono tracking-wide"
                    placeholder="Ej: 2026.2" maxlength="6">
                <p class="text-[10px] text-gray-400 mt-1">Formato esperado: AAAA.N (ej. 2026.2)</p>
            </div>

            <!-- Dropzone -->
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-1.5">
                    <i class="fa-regular fa-file-excel mr-1 text-green-600"></i> Archivo Excel Unificado
                </label>
                
                <div id="simca-dropzone" 
                    class="relative border-2 border-dashed border-gray-300 rounded-xl bg-gray-50 hover:bg-blue-50/50 hover:border-blue-400 transition-all duration-200 cursor-pointer group"
                    onclick="document.getElementById('simca-archivo').click()">
                    
                    <input type="file" id="simca-archivo" accept=".xlsx,.xls" class="hidden" onchange="manejarArchivoSimca(this)">
                    
                    <div class="py-8 px-4 text-center" id="simca-dropzone-default">
                        <div class="w-12 h-12 rounded-full bg-gray-100 group-hover:bg-blue-100 flex items-center justify-center mx-auto mb-3 transition-colors">
                            <i class="fa-solid fa-cloud-arrow-up text-gray-400 group-hover:text-blue-600 text-xl transition-colors"></i>
                        </div>
                        <p class="text-sm font-semibold text-gray-600 group-hover:text-blue-700 transition-colors">
                            Arrastra el archivo aquí o haz clic para seleccionar
                        </p>
                        <p class="text-[10px] text-gray-400 mt-1">Solo archivos .xlsx o .xls</p>
                    </div>

                    <!-- Estado archivo seleccionado -->
                    <div id="simca-dropzone-activo" class="hidden py-6 px-4 text-center">
                        <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-3">
                            <i class="fa-solid fa-file-excel text-green-600 text-xl"></i>
                        </div>
                        <p class="text-sm font-bold text-gray-800" id="simca-nombre-archivo">archivo.xlsx</p>
                        <p class="text-[10px] text-gray-500 mt-1" id="simca-tamano-archivo">0 KB</p>
                        <button type="button" onclick="event.stopPropagation(); limpiarArchivoSimca()" 
                            class="mt-2 text-[10px] text-red-500 hover:text-red-700 font-semibold underline">
                            Quitar archivo
                        </button>
                    </div>
                </div>
            </div>

            <!-- Info Box -->
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 flex gap-2.5 items-start">
                <i class="fa-solid fa-circle-info text-amber-500 mt-0.5 text-xs"></i>
                <div>
                    <p class="text-[11px] font-bold text-amber-800">Formato esperado</p>
                    <p class="text-[10px] text-amber-700 leading-relaxed">
                        El archivo debe ser el reporte descargado de SIMCA en Consultas Académicas: <strong>"Reporte Labor vs Oferta"</strong>.
                    </p>
                </div>
            </div>

            <!-- Barra de progreso (oculta inicialmente) -->
            <div id="simca-progreso" class="hidden">
                <div class="flex justify-between text-[10px] font-bold text-gray-600 mb-1">
                    <span id="simca-progreso-texto">Procesando registros...</span>
                    <span id="simca-progreso-porcentaje">0%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                    <div id="simca-progreso-barra" class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>
            </div>

            <!-- Mensaje de resultado -->
            <div id="simca-resultado" class="hidden rounded-lg p-3 text-center text-xs font-bold"></div>
        </div>

        <!-- Footer -->
        <div class="bg-gray-50 px-6 py-4 flex justify-end gap-2 border-t border-gray-100">
            <button onclick="cerrarModalSimca()" id="simca-btn-cancelar"
                class="px-4 py-2 rounded-lg text-xs font-bold text-gray-600 hover:bg-gray-200 transition-colors">
                Cancelar
            </button>
            <button onclick="procesarCargaSimca()" id="simca-btn-procesar"
                class="px-4 py-2 rounded-lg text-xs font-bold bg-blue-900 hover:bg-blue-800 text-white shadow-md hover:shadow-lg transition-all inline-flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="fa-solid fa-gear fa-spin hidden" id="simca-icono-carga"></i>
                <span id="simca-texto-procesar">Procesar y Distribuir</span>
            </button>
        </div>
    </div>
</div>
<script>
const API_URL = '../api/api.php';
let debounceTimer;
let paginaActual = 1;
let totalPaginas = 1;
let datosCargados = [];
let filasSeleccionadas = new Map(); // clave -> {identificacion, periodo, codigo_materia, grupo, estado_alerta_original}

function claveFilaObs(row) {
    return `${row.identificacion}|${row.periodo}|${row.codigo_materia}|${row.grupo}`;
}

function estadoOriginalDeFila(row) {
    // Prioriza la alerta interna de duplicidad, igual que el resto del dashboard.
    if (row.alerta_labor === 'DUPLICIDAD_GRUPO_EXCESO_PE') return 'DUPLICIDAD_GRUPO_EXCESO_PE';
    return row.estado_alerta;
}
// Variables para relaciones entre facultad y departamento (desde labor)
let relacionDepartamentoFacultad = {};
let departamentosPorFacultad = {};
let todasLasFacultades = [];
let todosLosDepartamentos = [];

document.addEventListener('DOMContentLoaded', cargarFiltros);

async function cargarFiltros() {
    try {
        const res = await fetch(`${API_URL}?action=filtros`);
        const json = await res.json();
        if (json.success) {
            const data = json.data;

            // Guardar relaciones para uso en cascada
            relacionDepartamentoFacultad = data.relacion_departamento_facultad || {};
            departamentosPorFacultad = data.departamentos_por_facultad || {};
            todasLasFacultades = data.facultades || [];
            todosLosDepartamentos = data.departamentos || [];

            const selP = document.getElementById('flt-periodo');
            selP.innerHTML = data.periodos.map(p => `<option value="${p}">${p}</option>`).join('');
            if (data.periodo_actual) {
                selP.value = data.periodo_actual;
                document.getElementById('lbl-periodo-activo').innerText = `Periodo: ${selP.value}`;
            }

            llenarSelect('flt-facultad', todasLasFacultades);
            llenarSelect('flt-depto', todosLosDepartamentos);
            llenarSelect('flt-programa', data.programas);

            // Inicializar relaciones en cascada según valores iniciales
            const facultadInicial = document.getElementById('flt-facultad').value;
            const deptoInicial = document.getElementById('flt-depto').value;
            
            if (facultadInicial && facultadInicial !== '') {
                actualizarDepartamentosPorFacultad();
            }
            if (deptoInicial && deptoInicial !== '') {
                actualizarFacultadPorDepartamento();
            }

            cargarAlertas(1);
        }
    } catch (err) {
        console.error('Error al cargar filtros:', err);
    }
}

function llenarSelect(id, items) {
    const sel = document.getElementById(id);
    sel.innerHTML = '<option value="">-- Todos --</option>';
    items.forEach(item => {
        sel.innerHTML += `<option value="${item}">${item}</option>`;
    });
}

function debounceAlertas() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => cargarAlertas(1), 350);
}

/**
 * Actualiza el select de departamentos basado en la facultad seleccionada
 * (filtro en cascada)
 */
function actualizarDepartamentosPorFacultad() {
    const facultadSeleccionada = document.getElementById('flt-facultad').value;
    const selectDepto = document.getElementById('flt-depto');
    const currentValue = selectDepto.value;
    
    // Limpiar select
    selectDepto.innerHTML = '<option value="">-- Todos --</option>';
    
    if (facultadSeleccionada && facultadSeleccionada !== '' && departamentosPorFacultad[facultadSeleccionada]) {
        // Mostrar solo departamentos de esa facultad
        const deptos = departamentosPorFacultad[facultadSeleccionada];
        deptos.forEach(depto => {
            selectDepto.innerHTML += `<option value="${depto}">${depto}</option>`;
        });
        // Intentar mantener el valor seleccionado si sigue siendo válido
        if (currentValue && deptos.includes(currentValue)) {
            selectDepto.value = currentValue;
        }
    } else {
        // Si no hay facultad seleccionada, mostrar todos los departamentos
        todosLosDepartamentos.sort().forEach(depto => {
            selectDepto.innerHTML += `<option value="${depto}">${depto}</option>`;
        });
        if (currentValue && todosLosDepartamentos.includes(currentValue)) {
            selectDepto.value = currentValue;
        }
    }
}

/**
 * Actualiza el select de facultad basado en el departamento seleccionado
 * (filtro en cascada inversa)
 */
function actualizarFacultadPorDepartamento() {
    const deptoSeleccionado = document.getElementById('flt-depto').value;
    const selectFacultad = document.getElementById('flt-facultad');
    const currentValue = selectFacultad.value;
    
    if (deptoSeleccionado && deptoSeleccionado !== '' && relacionDepartamentoFacultad[deptoSeleccionado]) {
        const facultad = relacionDepartamentoFacultad[deptoSeleccionado];
        // Seleccionar la facultad correspondiente
        selectFacultad.value = facultad;
        // También filtrar departamentos para esa facultad (para mantener consistencia)
        actualizarDepartamentosPorFacultad();
    } else if (!deptoSeleccionado || deptoSeleccionado === '') {
        // Si no hay departamento seleccionado, restaurar todas las facultades
        selectFacultad.innerHTML = '<option value="">-- Todas --</option>';
        todasLasFacultades.sort().forEach(fac => {
            selectFacultad.innerHTML += `<option value="${fac}">${fac}</option>`;
        });
        if (currentValue && todasLasFacultades.includes(currentValue)) {
            selectFacultad.value = currentValue;
        }
        // También restaurar todos los departamentos (por si acaso)
        actualizarDepartamentosPorFacultad();
    }
}

async function cargarAlertas(pagina = 1) {
    paginaActual = pagina;

    const periodo = document.getElementById('flt-periodo').value;
    const facultad = document.getElementById('flt-facultad').value;
    const depto = document.getElementById('flt-depto').value;
    const programa = document.getElementById('flt-programa').value;
    let estado = document.getElementById('flt-estado').value;
    const busqueda = document.getElementById('flt-busqueda').value;

    document.getElementById('lbl-periodo-activo').innerText = `Periodo: ${periodo}`;

    // Si el filtro es PE_CERO, lo convertimos a un parámetro especial
    const esFiltroPECero = estado === 'PE_CERO';
    const estadoBackend = esFiltroPECero ? '' : estado;

    const url = `${API_URL}?action=alertas&periodo=${periodo}&facultad=${encodeURIComponent(facultad)}&departamento=${encodeURIComponent(depto)}&programa=${encodeURIComponent(programa)}&estado=${encodeURIComponent(estadoBackend)}&search=${encodeURIComponent(busqueda)}`;

    try {
        const res = await fetch(url);
        const json = await res.json();
        if (json.success) {
            // Aplicar filtro PE_CERO en frontend si es necesario
            let data = json.data;
            if (esFiltroPECero) {
                data = data.filter(row => esPECero(row.pe));
            }

            document.getElementById('kpi-total').innerText = json.kpis.total_registros || 0;
            document.getElementById('kpi-ok').innerText = json.kpis.ok || 0;
            document.getElementById('kpi-critico').innerText = json.kpis.oferta_inexistente || 0;
            document.getElementById('kpi-servicio').innerText = json.kpis.prestacion_servicio || 0;
            document.getElementById('kpi-labor-inexistente').innerText = json.kpis.labor_inexistente || 0;
            document.getElementById('kpi-codigo-materia').innerText = json.kpis.revisar_codigo_materia || 0;

            datosCargados = data;
            renderizarTabla();
        }
    } catch (err) {
        console.error('Error al cargar alertas:', err);
    }
}

/* ----------------------------------------------------------------
   ORDENAMIENTO CON PRIORIDAD PARA LA COLUMNA "Alertas"
   ---------------------------------------------------------------- */
const PRIORIDAD_ALERTA = {
    'OFERTA_INEXISTENTE|DUPLICIDAD_GRUPO_EXCESO_PE': 0,
    'OFERTA_INEXISTENTE|': 1,
    'LABOR_INEXISTENTE|': 2,
    'REVISAR_CODIGO_MATERIA_DIFERENTE|': 3,
    'FALTA_GRUPO_EN_OFERTA|DUPLICIDAD_GRUPO_EXCESO_PE': 4,
    'FALTA_GRUPO_EN_OFERTA|': 5,
    'REVISAR_PROGRAMA_DIFERENTE|DUPLICIDAD_GRUPO_EXCESO_PE': 6,
    'REVISAR_PROGRAMA_DIFERENTE|': 7,
    'OK|DUPLICIDAD_GRUPO_EXCESO_PE': 8,
    'OK|': 9,
};

function getPrioridadAlerta(row) {
    const estado = row.estado_alerta;
    const labor = row.alerta_labor;
    const clave = `${estado}|${labor || ''}`;
    return PRIORIDAD_ALERTA[clave] ?? 10;
}

let ordenActual = { key: null, dir: 1 };

const sortKeyFns = {
    docente: r => r.docente || '',
    facultad: r => r.facultad_labor || '',
    programa: r => r.programa_labor || r.programa_oferta || '',
    materia: r => r.materia_labor || r.materia_oferta || '',
    horas: r => Number(r.horas_teoricas ?? 0),
    alertas: r => getPrioridadAlerta(r),
    historica: r => {
        if (!r.alerta_historica) return 9;
        return r.alerta_historica === 'SALTO_FUERTE' ? 0 : 1;
    },
};

function ordenarPor(key) {
    if (ordenActual.key === key) {
        ordenActual.dir *= -1;
    } else {
        ordenActual = { key, dir: 1 };
    }
    document.querySelectorAll('th[data-col] .sort-arrow').forEach(span => {
        const col = span.closest('th').dataset.col;
        span.textContent = (col === ordenActual.key) ? (ordenActual.dir === 1 ? '▲' : '▼') : '';
    });
    renderizarTabla();
}

/* ----------------------------------------------------------------
   RENDERIZADO DE LA TABLA
   ---------------------------------------------------------------- */
function renderizarTabla() {
    const porPagina = parseInt(document.getElementById('flt-registros-pagina').value);
    const totalRegistros = datosCargados.length;

    if (ordenActual.key) {
        const dir = ordenActual.dir;
        const getVal = sortKeyFns[ordenActual.key];
        datosCargados.sort((a, b) => {
            const va = getVal(a), vb = getVal(b);
            if (typeof va === 'number') return (va - vb) * dir;
            return String(va).localeCompare(String(vb), 'es', { sensitivity: 'base' }) * dir;
        });
    }

    totalPaginas = Math.ceil(totalRegistros / porPagina) || 1;
    if (paginaActual > totalPaginas) paginaActual = totalPaginas;

    const inicio = (paginaActual - 1) * porPagina;
    const fin = inicio + porPagina;
    const paginaDatos = datosCargados.slice(inicio, fin);

    document.getElementById('lbl-count-resultados').innerText = `Mostrando ${paginaDatos.length} de ${totalRegistros} registros`;
    document.getElementById('lbl-pagina-info').innerText = `Página ${paginaActual} de ${totalPaginas}`;
    document.getElementById('btn-prev').disabled = paginaActual <= 1;
    document.getElementById('btn-next').disabled = paginaActual >= totalPaginas;

    const tbody = document.getElementById('tbl-body');
    if (paginaDatos.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-6 text-gray-400">No se encontraron hallazgos con los filtros seleccionados.</td></tr>`;
        return;
    }

    tbody.innerHTML = paginaDatos.map(row => {
    const alertasHtml = renderAlertasCombinadas(row);
    const docente = valorLaborOferta(row, 'docente', 'docente', '—');
    const programaTxt = row.programa_labor || row.programa_oferta || '—';
    const notaSoloOferta = (!row.programa_labor && row.programa_oferta) ? '<div class="text-[9px] text-pink-500 font-semibold">Solo Oferta</div>' : '';
    const materiaTxt = row.materia_labor || row.materia_oferta || '—';
    const codigoTxt = row.codigo_materia || '—';
    const grupoTxt = row.grupo || '—';
    const horasTxt = (row.horas_teoricas ?? null) !== null ? row.horas_teoricas : '—';
    const facultadTxt = row.facultad_labor || '—';
    const deptoTxt = row.departamento_labor || '—';
    const codigoContexto = row.codigo_materia;
    const grupoContexto = row.grupo;
    const tieneIdentificacion = !!row.identificacion && String(row.identificacion).trim() !== '';

    const claveFila = claveFilaObs(row);
    const puedeSeleccionarse = tieneIdentificacion && !!row.codigo_materia && !!row.grupo;
    const marcado = filasSeleccionadas.has(claveFila);

    const checkboxHtml = puedeSeleccionarse
        ? `<input type="checkbox" class="js-obs-check cursor-pointer" data-clave="${esc(claveFila)}"
             data-identificacion="${esc(row.identificacion)}" data-periodo="${esc(row.periodo)}"
             data-codigo-materia="${esc(row.codigo_materia)}" data-grupo="${esc(row.grupo)}"
             data-estado-original="${esc(estadoOriginalDeFila(row))}" ${marcado ? 'checked' : ''}>`
        : '';

    return `<tr class="hover:bg-gray-50">
        <td class="py-3 px-2 text-center">${checkboxHtml}</td>
        <td class="py-3 px-4">
            <div class="font-bold text-gray-800 cell-clip" title="${esc(docente)}">${esc(docente)}</div>
            <div class="text-[10px] text-gray-400">ID: ${esc(row.identificacion)}</div>
        </td>
        <td class="py-3 px-4">
            <div class="text-gray-700 font-semibold cell-clip-2" title="${esc(facultadTxt)}">${esc(facultadTxt)}</div>
            <div class="text-[10px] text-gray-500 cell-clip" title="${esc(deptoTxt)}">${esc(deptoTxt)}</div>
        </td>
        <td class="py-3 px-4">
            <div class="cell-clip-2" title="${esc(programaTxt)}">${esc(programaTxt)}</div>${notaSoloOferta}
        </td>
        <td class="py-3 px-4">
            <div class="font-semibold text-gray-700 cell-clip-2" title="${esc(materiaTxt)}">${esc(materiaTxt)}</div>
            <div class="text-[10px] text-gray-500 cell-clip">Cód: ${esc(codigoTxt)} · Gr: ${esc(grupoTxt)}</div>
        </td>
        <td class="py-3 px-4 text-center font-bold">${esc(horasTxt)}</td>
        <td class="py-3 px-4 text-center celda-alertas">${alertasHtml}</td>
        <td class="py-3 px-4 text-center">${badgeHistorico(row)}</td>
        <td class="py-3 px-4 text-center">${renderBadgeObservacion(row)}</td>
        <td class="py-3 px-2 text-center">
            <div class="acciones-wrap">
                ${tieneIdentificacion ? `<button onclick="verRadiografia('${esc(row.identificacion)}')" title="Radiografía del docente" class="btn-accion-icono bg-blue-600 hover:bg-blue-700"><i class="fa-solid fa-eye"></i></button>` : ''}
                <button onclick="abrirModalMateria('${esc(codigoContexto)}')" title="Evolución de la materia por periodo" class="btn-accion-icono bg-purple-600 hover:bg-purple-700"><i class="fa-solid fa-chart-column"></i></button>
            </div>
        </td>
    </tr>`;
}).join('');


}

/* ----------------------------------------------------------------
   Helper de fallback Labor -> Oferta para renderizado seguro
   ---------------------------------------------------------------- */
function valorLaborOferta(row, campoLabor, campoOferta, valorPorDefecto = '—') {
    const claveLabor = `${campoLabor}`;
    const claveOferta = `${campoOferta}_oferta`;
    const valor = row[claveLabor] ?? row[claveOferta] ?? row['docente'];
    return (valor === null || valor === undefined || valor === '') ? valorPorDefecto : valor;
}

/* ----------------------------------------------------------------
   Helper para escapar HTML
   ---------------------------------------------------------------- */
function esc(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, function (m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        if (m === '"') return '&quot;';
        return '&#39;';
    });
}

/**
 * Determina si el PE (punto de equilibrio) es igual a 0
 * El PE puede venir como número o string
 */
function esPECero(pe) {
    if (pe === null || pe === undefined || pe === '') return false;
    const valor = Number(pe);
    return valor === 0;
}

/**
 * Genera el badge visual para PE = 0
 */
function badgePECero(pe) {
    if (!esPECero(pe)) return '';
    return `<span class="badge-pe-cero" title="El PE de esta asignación es 0. Este valor requiere revisión."><i class="fa-solid fa-triangle-exclamation"></i> PE 0</span>`;
}

/* ----------------------------------------------------------------
   RENDER DE ALERTAS COMBINADAS (con badge PE 0 incluido)
   ---------------------------------------------------------------- */
function renderAlertasCombinadas(row) {
    const estado = row.estado_alerta;
    const labor = row.alerta_labor;
    const pe = row.pe;
    const tieneDuplicidad = labor === 'DUPLICIDAD_GRUPO_EXCESO_PE';
    const tienePECero = esPECero(pe);

    // -----------------------------------------------------------
    // 1. Determinar el badge PRINCIPAL (color/texto/tooltip) según
    //    el estado_alerta. Ya no se hace "return" aquí: solo se
    //    calculan los datos, para poder seguir agregando badges
    //    secundarios después sin perderlos.
    // -----------------------------------------------------------
    let claseEstado = 'bg-gray-100 text-gray-800 border-gray-300';
    let textoEstado = estado || 'SIN ESTADO';
    let tooltipPrincipal = 'Clic para consultar el detalle de alertas por docente y por materia/grupo.';
    let programaContexto = row.programa_labor || row.programa_oferta || '';

    if (estado === 'OK') {
        claseEstado = 'bg-green-100 text-green-800 border-green-300';
        textoEstado = 'OK';
    } else if (estado === 'OFERTA_INEXISTENTE') {
        claseEstado = 'bg-red-100 text-red-800 border-red-300';
        textoEstado = 'OFERTA INEXISTENTE';
    } else if (estado === 'FALTA_GRUPO_EN_OFERTA') {
        claseEstado = 'bg-orange-100 text-orange-800 border-orange-300';
        textoEstado = 'GRUPO SIN OFERTA';
    } else if (estado === 'REVISAR_PROGRAMA_DIFERENTE') {
        const coincidencia = row.coincidencia_programa;
        if (coincidencia === 'ALTA') {
            claseEstado = 'bg-amber-100 text-amber-800 border-amber-300';
            textoEstado = 'PROGRAMA SIMILAR';
        } else if (coincidencia === 'MEDIA') {
            claseEstado = 'bg-amber-100 text-amber-800 border-amber-300';
            textoEstado = 'PROGRAMA SIMILAR (REVISAR)';
        } else {
            claseEstado = 'bg-red-100 text-red-800 border-red-300';
            textoEstado = 'PROGRAMA DIFERENTE';
        }
        const pct = (row.similitud_programa !== null && row.similitud_programa !== undefined)
            ? Math.round(row.similitud_programa * 100) : 'N/D';
        tooltipPrincipal = `Programa Labor: ${row.programa_labor || '—'} | Programa Oferta: ${row.programa_oferta || '—'} | Similitud: ${pct}% | Motivo: ${row.motivo_programa || ''}`;
    } else if (estado === 'LABOR_INEXISTENTE') {
        claseEstado = 'bg-pink-100 text-pink-800 border-pink-300';
        textoEstado = 'NO EXISTE EN LABOR';
        tooltipPrincipal = 'El registro existe en Oferta, pero no se encontró una asignación correspondiente en Labor.';
        programaContexto = row.programa_oferta || '';
    } else if (estado === 'REVISAR_CODIGO_MATERIA_DIFERENTE') {
        claseEstado = 'bg-indigo-100 text-indigo-800 border-indigo-300';
        textoEstado = 'REVISAR CÓDIGO MATERIA';
        const dc = row.detalle_codigo_posible_error || {};
        const pct = (dc.similitud_programa !== null && dc.similitud_programa !== undefined)
            ? Math.round(dc.similitud_programa * 100) : 'N/D';
        tooltipPrincipal = `Código en Labor: ${dc.codigo_materia_labor || row.codigo_materia} | Código en Oferta: ${dc.codigo_materia_oferta || '—'} | Programa Oferta: ${dc.programa_oferta || '—'} | Similitud de programa: ${pct}% | ${dc.motivo || ''}`;
        programaContexto = row.programa_labor || '';
    }

    // -----------------------------------------------------------
    // 2. data-attrs para el badge principal (mismo contexto que
    //    usaba cada rama antes de la reescritura).
    // -----------------------------------------------------------
    const dataAttrs = `data-identificacion="${esc(row.identificacion)}" data-periodo="${esc(row.periodo)}" `
        + `data-codigo-materia="${esc(row.codigo_materia)}" data-programa="${esc(programaContexto)}" `
        + `data-grupo="${esc(row.grupo)}" data-estado-alerta="${esc(estado)}" `
        + `data-alerta-labor="${esc(labor)}" data-tiene-duplicidad="${tieneDuplicidad ? 1 : 0}"`;

    const badgePrincipalHtml = `<button type="button" class="js-auditoria-fila badge-alerta badge-alerta-principal ${claseEstado}"
        ${dataAttrs} title="${esc(tooltipPrincipal)}">${esc(textoEstado)}</button>`;

    // -----------------------------------------------------------
    // 3. Badges SECUNDARIOS: ahora SIEMPRE se evalúan, sin importar
    //    cuál haya sido el estado principal. Esto es lo que antes
    //    se perdía para LABOR_INEXISTENTE / REVISAR_CODIGO_MATERIA_DIFERENTE.
    // -----------------------------------------------------------
    const badgesSecundarios = [];

    if (tieneDuplicidad) {
        const detalleDup = row.detalle_alerta_labor || {};
        const tooltipDup = detalleDup.motivo
            ? `Duplicidad de grupo: ${detalleDup.docentes || ''} · Horas totales: ${detalleDup.horas_totales ?? '—'} · PE: ${detalleDup.pe_unico ?? '—'} · Exceso: ${detalleDup.exceso_horas ?? '—'}`
            : 'Este grupo tiene varios docentes asignados y la suma de horas supera el PE.';
        badgesSecundarios.push(
            `<span class="badge-alerta badge-alerta-secundario bg-fuchsia-100 text-fuchsia-800 border-fuchsia-300" title="${esc(tooltipDup)}">DUPLICIDAD DE GRUPO</span>`
        );
    }

    if (tienePECero) {
        badgesSecundarios.push(
            `<span class="badge-alerta badge-alerta-secundario bg-red-50 text-red-700 border-red-300" title="El PE de esta asignación es 0. Este valor requiere revisión."><i class="fa-solid fa-triangle-exclamation"></i>&nbsp;PE 0</span>`
        );
    }

    // -----------------------------------------------------------
    // 4. Ensamblar: badge principal arriba, secundarios debajo,
    //    todos apilados en columna (ver .alertas-stack en el CSS).
    // -----------------------------------------------------------
    return `<div class="alertas-stack">${badgePrincipalHtml}${badgesSecundarios.join('')}</div>`;
}

/* ----------------------------------------------------------------
   Badge Histórico (sin cambios)
   ---------------------------------------------------------------- */
function badgeHistorico(row) {
    if (!row.alerta_historica) return `<span class="text-gray-300 text-10px">—</span>`;

    const d = row.salto_detalle || {};
    const salto = (d.grupos_act ?? 0) - (d.grupos_prev ?? 0);
    const baseEspecial = (d.base_es_inmediato === false) && d.periodo_anterior;
    const notaBase = baseEspecial ? `(ojo: no existió en ${d.periodo_inmediato}; se comparó con ${d.periodo_anterior})` : '';
    const tooltip = `Clic para ver la evolución histórica. ${d.grupos_prev}->${d.grupos_act} grupos, matriculados ${d.mat_prev}->${d.mat_act}, respecto a ${d.periodo_anterior}. ${notaBase} ${d.motivo || ''}`;

    const notaVisible = baseEspecial ? `<div class="text-9px text-purple-600 font-semibold leading-tight mt-0.5">vs ${d.periodo_anterior}</div>` : '';
    const borde = baseEspecial ? 'border border-dashed' : 'border border-transparent';

    let boton;
    if (row.alerta_historica === 'SALTO_FUERTE') {
        boton = `<button type="button" onclick="abrirModalMateria('${esc(row.codigo_materia)}')" title="${esc(tooltip)}" class="inline-block whitespace-nowrap bg-red-100 text-red-800 font-bold px-2 py-1 rounded-full text-10px cursor-pointer hover:bg-red-200 transition-colors ${borde} border-red-400">Salto +${salto}</button>`;
    } else {
        boton = `<button type="button" onclick="abrirModalMateria('${esc(row.codigo_materia)}')" title="${esc(tooltip)}" class="inline-block whitespace-nowrap bg-emerald-50 text-emerald-700 font-bold px-2 py-1 rounded-full text-10px cursor-pointer hover:bg-emerald-100 transition-colors ${borde} border-emerald-400">+${salto} (salto justificado)</button>`;
    }
    return `<div class="inline-flex flex-col items-center">${boton}${notaVisible}</div>`;
}

/* ----------------------------------------------------------------
   Paginación y navegación
   ---------------------------------------------------------------- */
function cambiarPagina(delta) {
    const nuevaPagina = paginaActual + delta;
    if (nuevaPagina < 1 || nuevaPagina > totalPaginas) return;
    paginaActual = nuevaPagina;
    renderizarTabla();
}

/* ----------------------------------------------------------------
   Radiografía Docente
   ---------------------------------------------------------------- */
async function verRadiografia(identificacion) {
    document.getElementById('modal-radiografia').classList.remove('hidden');
    const cont = document.getElementById('modal-contenido');
    cont.innerHTML = `<p class="text-xs text-gray-500">Cargando histórico...</p>`;
    try {
        const res = await fetch(`${API_URL}?action=docente_radiografia&identificacion=${identificacion}`);
        const json = await res.json();
        if (json.success) {
            document.getElementById('modal-docente-nombre').innerText = `Radiografía: ${json.docente} (${json.identificacion})`;
            cont.innerHTML = `
                <h4 class="font-bold text-sm text-gray-700 mb-2">Histórico Multiperiodo (Labor)</h4>
                <table class="w-full text-xs text-left mb-4 border">
                    <thead class="bg-gray-100 font-bold">
                        <tr>
                            <th class="p-2 border">Periodo</th>
                            <th class="p-2 border">Programa</th>
                            <th class="p-2 border">Materia</th>
                            <th class="p-2 border">Grupo</th>
                            <th class="p-2 border">Horas</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${json.historico_labor.map(h => `
                        <tr>
                            <td class="p-2 border font-bold">${esc(h.periodo)}</td>
                            <td class="p-2 border">${esc(h.programa)}</td>
                            <td class="p-2 border">${esc(h.materia)}</td>
                            <td class="p-2 border">${esc(h.grupo)}</td>
                            <td class="p-2 border font-bold">${esc(h.horas_teoricas)}</td>
                        </tr>`).join('')}
                    </tbody>
                </table>`;
        }
    } catch (err) {
        cont.innerHTML = `<p class="text-xs text-red-500">Error al cargar la radiografía.</p>`;
    }
}

function cerrarModal() {
    document.getElementById('modal-radiografia').classList.add('hidden');
}

/* ----------------------------------------------------------------
   Exportar Excel
   ---------------------------------------------------------------- */
function exportarExcel() {
    const params = new URLSearchParams({
        action: 'exportar_excel',
        periodo: document.getElementById('flt-periodo').value,
        facultad: document.getElementById('flt-facultad').value,
        departamento: document.getElementById('flt-depto').value,
        programa: document.getElementById('flt-programa').value,
        estado: document.getElementById('flt-estado').value === 'PE_CERO' ? '' : document.getElementById('flt-estado').value,
        search: document.getElementById('flt-busqueda').value,
    });
    // Para PE_CERO, añadimos un parámetro adicional que el backend no usa pero mantenemos por si acaso
    if (document.getElementById('flt-estado').value === 'PE_CERO') {
        params.append('pe_cero', '1');
    }
    window.open(`${API_URL}?${params.toString()}`, '_blank');
}

/* ----------------------------------------------------------------
   Delegación de eventos para el modal unificado (fila de alertas)
   ---------------------------------------------------------------- */
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.js-auditoria-fila');
    if (!btn) return;
    e.preventDefault();

    const contexto = {
        identificacion: btn.dataset.identificacion,
        periodo: btn.dataset.periodo,
        codigoMateria: btn.dataset.codigoMateria,
        programa: btn.dataset.programa,
        grupo: btn.dataset.grupo,
        estadoAlerta: btn.dataset.estadoAlerta,
        alertaLabor: btn.dataset.alertaLabor,
    };

    if (!contexto.identificacion || !contexto.periodo) {
        console.warn('Fila sin identificación/periodo: solo se puede consultar Materia.');
        return;
    }

    if (typeof window.abrirModalAuditoriaFila === 'function') {
        window.abrirModalAuditoriaFila(contexto);
    } else {
        console.error('La función abrirModalAuditoriaFila no está definida. Asegúrate de que modal_docente_periodo.js está cargado.');
    }
});

/* ----------------------------------------------------------------
   Función auxiliar para el botón "Materia" (usa fallback de contexto)
   ---------------------------------------------------------------- */
function abrirModalMateria(codigoMateria) {
    if (typeof window.abrirModalMateriaHistorico === 'function') {
        window.abrirModalMateriaHistorico(codigoMateria);
    }
}
    
    /* ================================================================
   MODAL SIMCA - Carga de Periodos
   ================================================================ */

const modalSimca = document.getElementById('modal-simca');
const modalSimcaContent = document.getElementById('modal-simca-content');
const inputArchivo = document.getElementById('simca-archivo');
const dropzone = document.getElementById('simca-dropzone');
let archivoSimcaSeleccionado = null;

function abrirModalSimca() {
    modalSimca.classList.remove('hidden');
    modalSimca.classList.add('flex', 'opacity-100');   // añade flex + opacity
    modalSimcaContent.classList.add('scale-100');
    
    limpiarArchivoSimca();
    document.getElementById('simca-periodo').value = '2026.2';
    document.getElementById('simca-resultado').classList.add('hidden');
    document.getElementById('simca-progreso').classList.add('hidden');
    habilitarFormularioSimca(true);
}

function cerrarModalSimca() {
    modalSimca.classList.remove('opacity-100');
    modalSimcaContent.classList.remove('scale-100');
    
    setTimeout(() => {
        modalSimca.classList.remove('flex');           // quita flex
        modalSimca.classList.add('hidden');            // añade hidden
    }, 300);
}

// Cerrar al hacer clic fuera
modalSimca.addEventListener('click', (e) => {
    if (e.target === modalSimca) cerrarModalSimca();
});

// Drag & Drop
['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    dropzone.addEventListener(eventName, prevenirDefault, false);
});

function prevenirDefault(e) {
    e.preventDefault();
    e.stopPropagation();
}

['dragenter', 'dragover'].forEach(eventName => {
    dropzone.addEventListener(eventName, () => dropzone.classList.add('dragover'), false);
});

['dragleave', 'drop'].forEach(eventName => {
    dropzone.addEventListener(eventName, () => dropzone.classList.remove('dragover'), false);
});

dropzone.addEventListener('drop', (e) => {
    const archivos = e.dataTransfer.files;
    if (archivos.length) {
        inputArchivo.files = archivos;
        manejarArchivoSimca(inputArchivo);
    }
});

function manejarArchivoSimca(input) {
    const archivo = input.files[0];
    if (!archivo) return;

    // Validar extensión
    const ext = archivo.name.split('.').pop().toLowerCase();
    if (!['xlsx', 'xls'].includes(ext)) {
        mostrarResultadoSimca('error', 'Solo se permiten archivos Excel (.xlsx, .xls)');
        input.value = '';
        return;
    }

    // Validar tamaño (máx 20MB)
    if (archivo.size > 20 * 1024 * 1024) {
        mostrarResultadoSimca('error', 'El archivo excede el límite de 20MB');
        input.value = '';
        return;
    }

    archivoSimcaSeleccionado = archivo;
    document.getElementById('simca-dropzone-default').classList.add('hidden');
    document.getElementById('simca-dropzone-activo').classList.remove('hidden');
    document.getElementById('simca-nombre-archivo').textContent = archivo.name;
    document.getElementById('simca-tamano-archivo').textContent = formatearBytes(archivo.size);
    document.getElementById('simca-resultado').classList.add('hidden');
}

function limpiarArchivoSimca() {
    archivoSimcaSeleccionado = null;
    inputArchivo.value = '';
    document.getElementById('simca-dropzone-default').classList.remove('hidden');
    document.getElementById('simca-dropzone-activo').classList.add('hidden');
}

function formatearBytes(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function mostrarResultadoSimca(tipo, mensaje) {
    const div = document.getElementById('simca-resultado');
    div.classList.remove('hidden', 'bg-green-100', 'text-green-800', 'bg-red-100', 'text-red-800', 'bg-blue-100', 'text-blue-800');
    
    if (tipo === 'exito') {
        div.classList.add('bg-green-100', 'text-green-800');
        div.innerHTML = `<i class="fa-solid fa-check-circle mr-1"></i> ${mensaje}`;
    } else if (tipo === 'error') {
        div.classList.add('bg-red-100', 'text-red-800');
        div.innerHTML = `<i class="fa-solid fa-circle-xmark mr-1"></i> ${mensaje}`;
    } else {
        div.classList.add('bg-blue-100', 'text-blue-800');
        div.innerHTML = `<i class="fa-solid fa-circle-info mr-1"></i> ${mensaje}`;
    }
}

function habilitarFormularioSimca(habilitar) {
    document.getElementById('simca-periodo').disabled = !habilitar;
    document.getElementById('simca-btn-cancelar').disabled = !habilitar;
    document.getElementById('simca-btn-procesar').disabled = !habilitar;
    if (!habilitar) {
        document.getElementById('simca-icono-carga').classList.remove('hidden');
        document.getElementById('simca-texto-procesar').textContent = 'Procesando...';
    } else {
        document.getElementById('simca-icono-carga').classList.add('hidden');
        document.getElementById('simca-texto-procesar').textContent = 'Procesar y Distribuir';
    }
}

function actualizarProgresoSimca(porcentaje, texto) {
    document.getElementById('simca-progreso-barra').style.width = porcentaje + '%';
    document.getElementById('simca-progreso-porcentaje').textContent = porcentaje + '%';
    if (texto) document.getElementById('simca-progreso-texto').textContent = texto;
}

async function procesarCargaSimca() {
    const periodo = document.getElementById('simca-periodo').value.trim();
    
    // Validaciones
    if (!periodo) {
        mostrarResultadoSimca('error', 'Debes indicar el período académico');
        return;
    }
    if (!/^\d{4}\.\d$/.test(periodo)) {
        mostrarResultadoSimca('error', 'Formato de período inválido. Use AAAA.N (ej: 2026.2)');
        return;
    }
    if (!archivoSimcaSeleccionado) {
        mostrarResultadoSimca('error', 'Selecciona un archivo Excel para continuar');
        return;
    }

    // Preparar envío
    const formData = new FormData();
    formData.append('periodo', periodo);
    formData.append('archivo_excel', archivoSimcaSeleccionado);

    // UI de carga
    habilitarFormularioSimca(false);
    document.getElementById('simca-progreso').classList.remove('hidden');
    document.getElementById('simca-resultado').classList.add('hidden');
    
    // Simulación de progreso (la barra avanza hasta que el servidor responde)
    let progreso = 0;
    const intervalo = setInterval(() => {
        if (progreso < 85) {
            progreso += Math.random() * 15;
            actualizarProgresoSimca(Math.min(Math.round(progreso), 85), 'Distribuyendo registros en tablas...');
        }
    }, 600);

    try {
        const res = await fetch('../cargar_excel.php', {
            method: 'POST',
            body: formData
        });
        
        clearInterval(intervalo);
        
        if (!res.ok) throw new Error(`Error HTTP: ${res.status}`);
        
        const json = await res.json().catch(() => null);
        
        if (json && json.success) {
            actualizarProgresoSimca(100, 'Completado');
            mostrarResultadoSimca('exito', 
                `Periodo ${periodo} cargado exitosamente. ` +
                `${json.insertados_labor || 0} registros Labor, ${json.insertados_oferta || 0} registros Oferta.`
            );
            // Refrescar filtros del dashboard si el periodo cargado es el actual
            setTimeout(() => {
                if (document.getElementById('flt-periodo').value === periodo) {
                    cargarAlertas(1);
                }
            }, 1500);
        } else {
            throw new Error(json?.message || 'Error desconocido en el servidor');
        }
        
    } catch (err) {
        clearInterval(intervalo);
        actualizarProgresoSimca(0, 'Error');
        mostrarResultadoSimca('error', err.message || 'No se pudo completar la carga. Verifica tu conexión.');
        habilitarFormularioSimca(true);
    }
}

// Escape para cerrar modal
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !modalSimca.classList.contains('hidden')) {
        cerrarModalSimca();
    }
});
                            
                            // Badge visual de observación asociada a una fila.
function renderBadgeObservacion(row) {
    if (!row.id_observacion_asociada) {
        return '<span class="text-gray-300 text-[10px]">—</span>';
    }
    const esAbierta = row.estado_observacion_asociada === 'ABIERTA';
    const icono = esAbierta ? '📎' : '✅';
    const clase = esAbierta
        ? 'bg-amber-100 text-amber-800 border-amber-300 hover:bg-amber-200'
        : 'bg-emerald-100 text-emerald-800 border-emerald-300 hover:bg-emerald-200';
    const titulo = esAbierta ? 'Observación abierta. Clic para ver detalle y descargar Word.' : 'Observación subsanada. Clic para ver detalle.';
    return `<button type="button" onclick="verDetalleObservacion(${row.id_observacion_asociada})"
        title="${titulo}" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold border ${clase}">
        ${icono} #${row.id_observacion_asociada}
    </button>`;
}

// Marca / desmarca la casilla "Seleccionar todos" de la página actual.
function toggleMarcarTodos(chkMaestro) {
    document.querySelectorAll('.js-obs-check').forEach(chk => {
        chk.checked = chkMaestro.checked;
        chk.dispatchEvent(new Event('change'));
    });
}

// Actualiza el contador y habilita/deshabilita el botón "Crear Observación".
function actualizarBotonCrearObservacion() {
    const total = filasSeleccionadas.size;
    document.getElementById('lbl-count-seleccionadas').innerText = total;
    document.getElementById('btn-crear-observacion').disabled = total === 0;
}

// Delegación de eventos para los checkboxes de fila (se agrega UNA sola vez).
document.addEventListener('change', function (e) {
    const chk = e.target.closest('.js-obs-check');
    if (!chk) return;
    const clave = chk.dataset.clave;
    if (chk.checked) {
        filasSeleccionadas.set(clave, {
            identificacion: chk.dataset.identificacion,
            periodo: chk.dataset.periodo,
            codigo_materia: chk.dataset.codigoMateria,
            grupo: chk.dataset.grupo,
            estado_alerta_original: chk.dataset.estadoOriginal,
        });
    } else {
        filasSeleccionadas.delete(clave);
    }
    actualizarBotonCrearObservacion();
});

// Abre el modal de creación de observación (definido en modal_glosa.js).
function abrirModalGlosa() {
    if (filasSeleccionadas.size === 0) return;
    const periodo = document.getElementById('flt-periodo').value;
    if (typeof window.abrirModalCrearObservacion === 'function') {
        window.abrirModalCrearObservacion(Array.from(filasSeleccionadas.values()), periodo);
    } else {
        console.error('modal_glosa.js no está cargado.');
    }
}

// Abre el modal de detalle de una observación (definido en modal_glosa.js).
function verDetalleObservacion(idObservacion) {
    if (typeof window.abrirModalDetalleObservacion === 'function') {
        window.abrirModalDetalleObservacion(idObservacion);
    } else {
        console.error('modal_glosa.js no está cargado.');
    }
}

// Callback que modal_glosa.js invoca tras guardar exitosamente una observación.
function onObservacionGuardada() {
    filasSeleccionadas.clear();
    actualizarBotonCrearObservacion();
    cargarAlertas(paginaActual);
}

</script>

<!-- Cargar los modales (el orden es importante) -->
<script src="modal_docente_periodo.js"></script>
<script src="modal_materia_historico.js"></script>
<script src="modal_glosa.js"></script>

</body>
</html>