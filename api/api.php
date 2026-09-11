<?php
// auditoria_docente/api/api.php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/conexion.php'; // Carga $pdo

$action = $_GET['action'] ?? '';

/* =========================================================================
   A. HELPERS REUTILIZABLES (fuera del switch)
   ========================================================================= */

/**
 * Normaliza texto para comparación: minúsculas UTF-8, sin tildes,
 * sin signos de puntuación, espacios colapsados y trim.
 */
function normalizarTextoComparacion($texto)
{
    $texto = (string)($texto ?? '');
    $texto = mb_strtolower($texto, 'UTF-8');

    $transliterado = @iconv('UTF-8', 'ASCII//TRANSLIT', $texto);
    if ($transliterado !== false) {
        $texto = $transliterado;
    }

    $texto = preg_replace('/[^a-z0-9\s]/u', ' ', $texto);
    $texto = preg_replace('/\s+/', ' ', $texto);

    return trim($texto);
}

/** Normaliza código de materia: trim + mayúsculas. */
function normalizarCodigoMateria($codigo)
{
    return mb_strtoupper(trim((string)($codigo ?? '')), 'UTF-8');
}

/** Normaliza grupo: trim + mayúsculas. */
function normalizarGrupo($grupo)
{
    return mb_strtoupper(trim((string)($grupo ?? '')), 'UTF-8');
}

/**
 * Tokeniza un nombre de programa ya normalizado, removiendo conectores
 * y prefijos/sufijos institucionales ignorables para el cálculo de similitud.
 */
function tokensPrograma($texto)
{
    $normalizado = normalizarTextoComparacion($texto);
    if ($normalizado === '') {
        return [];
    }

    $tokensCrudos = explode(' ', $normalizado);

    $conectores = ['de', 'del', 'la', 'las', 'el', 'los', 'y', 'en', 'para', 'con'];
    $institucionales = ['aip', 'enfasis', 'profundizacion'];

    $tokens = [];
    foreach ($tokensCrudos as $tok) {
        $tok = trim($tok);
        if ($tok === '') {
            continue;
        }
        if (in_array($tok, $conectores, true)) {
            continue;
        }
        if (in_array($tok, $institucionales, true)) {
            continue;
        }
        $tokens[] = $tok;
    }

    return $tokens;
}

/**
 * Similitud genérica de texto (0..1) usando el mayor valor entre similitud
 * de tokens (ignorando conectores y prefijos institucionales) y similar_text().
 * Se usa tanto para comparar programas como para comparar materias.
 */
function similitudTexto($textoA, $textoB)
{
    $textoA = (string)($textoA ?? '');
    $textoB = (string)($textoB ?? '');

    $normA = normalizarTextoComparacion($textoA);
    $normB = normalizarTextoComparacion($textoB);

    if ($normA === '' && $normB === '') {
        return 0.0;
    }
    if ($normA === $normB) {
        return 1.0;
    }

    $tokensA = tokensPrograma($textoA);
    $tokensB = tokensPrograma($textoB);
    $maxTokens = max(count($tokensA), count($tokensB), 1);
    $tokensComunes = count(array_intersect($tokensA, $tokensB));
    $similitudTokens = $tokensComunes / $maxTokens;

    $porcentaje = 0.0;
    similar_text($normA, $normB, $porcentaje);
    $similitudCadena = $porcentaje / 100;

    return max($similitudTokens, $similitudCadena);
}

/**
 * Compara dos nombres de programa (Labor vs Oferta) y devuelve el nivel
 * de coincidencia, similitud numérica y un motivo explicativo.
 *
 * Retorna:
 * [
 *   'tipo' => 'EXACTA|ALTA|MEDIA|BAJA|NO_APLICA',
 *   'similitud' => 0.0 a 1.0,
 *   'programa_labor' => valor original,
 *   'programa_oferta' => valor original,
 *   'motivo' => texto explicativo
 * ]
 */
function compararProgramas($programaLabor, $programaOferta)
{
    $programaLabor = (string)($programaLabor ?? '');
    $programaOferta = (string)($programaOferta ?? '');

    if (trim($programaOferta) === '') {
        return [
            'tipo' => 'NO_APLICA',
            'similitud' => null,
            'programa_labor' => $programaLabor,
            'programa_oferta' => $programaOferta,
            'motivo' => 'No hay programa de Oferta disponible para comparar.',
        ];
    }

    $normLabor = normalizarTextoComparacion($programaLabor);
    $normOferta = normalizarTextoComparacion($programaOferta);

    if ($normLabor !== '' && $normLabor === $normOferta) {
        return [
            'tipo' => 'EXACTA',
            'similitud' => 1.00,
            'programa_labor' => $programaLabor,
            'programa_oferta' => $programaOferta,
            'motivo' => 'El nombre del programa coincide exactamente entre Labor y Oferta.',
        ];
    }

    $tokensLabor = tokensPrograma($programaLabor);
    $tokensOferta = tokensPrograma($programaOferta);

    $totalLabor = count($tokensLabor);
    $totalOferta = count($tokensOferta);
    $maxTokens = max($totalLabor, $totalOferta, 1);

    $tokensComunes = count(array_intersect($tokensLabor, $tokensOferta));
    $similitudTokens = $tokensComunes / $maxTokens;

    $porcentajeSimilarText = 0.0;
    if ($normLabor !== '' || $normOferta !== '') {
        similar_text($normLabor, $normOferta, $porcentajeSimilarText);
    }
    $similitudCadena = $porcentajeSimilarText / 100;

    $similitud = max($similitudTokens, $similitudCadena);

    $masCorto = $totalLabor <= $totalOferta ? $tokensLabor : $tokensOferta;
    $masLargo = $totalLabor <= $totalOferta ? $tokensOferta : $tokensLabor;

    $contenido = !empty($masCorto) && count(array_diff($masCorto, $masLargo)) === 0;

    if ($contenido) {
        $similitud = max($similitud, 0.90);
        return [
            'tipo' => 'ALTA',
            'similitud' => round($similitud, 2),
            'programa_labor' => $programaLabor,
            'programa_oferta' => $programaOferta,
            'motivo' => 'Coincidencia alta: el nombre de Oferta contiene el núcleo del programa de Labor '
                . '(la diferencia corresponde a un prefijo o sufijo institucional como AIP, ÉNFASIS o PROFUNDIZACIÓN).',
        ];
    }

    if ($similitud >= 0.85) {
        $tipo = 'ALTA';
        $motivo = 'Coincidencia alta: los nombres de programa comparten la mayoría de sus palabras clave.';
    } elseif ($similitud >= 0.70) {
        $tipo = 'MEDIA';
        $motivo = 'Coincidencia media: los nombres de programa comparten una parte relevante de sus palabras clave.';
    } else {
        $tipo = 'BAJA';
        $motivo = 'Coincidencia baja: los nombres de programa difieren en su mayoría; revisar manualmente.';
    }

    return [
        'tipo' => $tipo,
        'similitud' => round($similitud, 2),
        'programa_labor' => $programaLabor,
        'programa_oferta' => $programaOferta,
        'motivo' => $motivo,
    ];
}

/** Construye la llave base de cruce: periodo + identificacion + codigo_materia normalizado. */
function claveBaseCruce($periodo, $identificacion, $codigoMateria)
{
    return trim((string)$periodo) . '|' . trim((string)$identificacion) . '|' . normalizarCodigoMateria($codigoMateria);
}

/** Construye la llave de docente/período (sin código de materia), usada para el diagnóstico de código posiblemente erróneo. */
function claveDocentePeriodo($periodo, $identificacion)
{
    return trim((string)$periodo) . '|' . trim((string)$identificacion);
}

/**
 * Construye una fila de dashboard a partir de un registro de Oferta que no
 * fue consumido por ningún cruce de Labor (LABOR_INEXISTENTE).
 */
function filaOfertaSinLabor($oferta)
{
    return [
        'id' => 'oferta_' . ($oferta['id'] ?? uniqid()),
        'fuente_principal' => 'OFERTA',
        'periodo' => $oferta['periodo'] ?? null,

        'identificacion' => $oferta['identificacion'] ?? null,
        'docente' => $oferta['docente'] ?? null,
        'programa_oferta' => $oferta['programa'] ?? null,
        'materia_oferta' => $oferta['materia'] ?? null,
        'codigo_materia' => $oferta['codigo_materia'] ?? null,
        'grupo' => $oferta['grupo'] ?? null,

        'matriculados' => $oferta['matriculados'] ?? null,
        'cupo' => $oferta['cupo'] ?? null,
        'matriculados_oferta' => $oferta['matriculados'] ?? null,
        'cupo_oferta' => $oferta['cupo'] ?? null,

        // Compatibilidad con frontend
        'matriculadosoferta' => $oferta['matriculados'] ?? null,
        'cupooferta' => $oferta['cupo'] ?? null,
        'programaoferta' => $oferta['programa'] ?? null,
        'materiaoferta' => $oferta['materia'] ?? null,
        'codigomateriaoferta' => $oferta['codigo_materia'] ?? null,
        'grupooferta' => $oferta['grupo'] ?? null,

        'facultad_labor' => null,
        'departamento_labor' => null,
        'programa_labor' => null,
        'materia_labor' => null,
        'tipo_contrato' => null,
        'horas_teoricas' => null,
        'pe' => null,

        'estado_alerta' => 'LABOR_INEXISTENTE',
        'es_prestacion_servicio' => 0,
        'alerta_labor' => null,
        'detalle_alerta_labor' => null,
        'detalle_cobertura' => null,
        'alerta_historica' => null,
        'salto_detalle' => null,
        'detalle_codigo_posible_error' => null,

        'coincidencia_programa' => 'NO_APLICA',
        'similitud_programa' => null,
        'motivo_programa' => 'El registro existe en Oferta, pero no se encontró una asignación correspondiente en Labor.',
        
        // Trazabilidad
        'oferta_id_asociada' => $oferta['id'] ?? null,
    ];
}
   
function filtrarPorBusqueda(array $filas, $busqueda)
{
    $busqueda = trim((string)$busqueda);
    if ($busqueda === '') {
        return $filas;
    }

    $busquedaNorm = normalizarTextoComparacion($busqueda);
    if ($busquedaNorm === '') {
        return $filas;
    }

    $campos = ['docente', 'identificacion', 'materia_labor', 'materia_oferta', 'codigo_materia', 'programa_labor', 'programa_oferta'];

    return array_values(array_filter($filas, function ($row) use ($campos, $busquedaNorm) {
        foreach ($campos as $campo) {
            $valor = $row[$campo] ?? '';
            if ($valor === null) {
                continue;
            }
            $valorNorm = normalizarTextoComparacion((string)$valor);
            if ($valorNorm !== '' && str_contains($valorNorm, $busquedaNorm)) {
                return true;
            }
        }
        return false;
    }));
}

/**
 * Diagnóstico de "posible código de materia equivocado": cuando una fila de
 * Labor no tiene oferta candidata bajo su propio código, busca en TODA la
 * Oferta del mismo periodo+identificacion (sin restringir código) la fila
 * cuyo NOMBRE DE MATERIA es más parecido al de Labor (criterio principal,
 * porque el nombre de la materia es la señal real de identidad del
 * contenido académico; el programa se usa solo para desempatar cuando hay
 * varios candidatos con similitud de materia igualmente alta, p. ej. cuando
 * el mismo código de Oferta tiene varias filas para distintos programas).
 *
 * Retorna null si no hay indicio suficiente, o un arreglo con el detalle.
 */
function extraerNivelesMateria($texto)
{
    $normalizado = normalizarTextoComparacion($texto);
    if ($normalizado === '') {
        return [];
    }

    $tokens = explode(' ', $normalizado);
    $romanos = ['i', 'ii', 'iii', 'iv', 'v', 'vi', 'vii', 'viii', 'ix', 'x'];
    $niveles = [];

    foreach ($tokens as $tok) {
        $tok = trim($tok);
        if ($tok === '') {
            continue;
        }
        if (in_array($tok, $romanos, true)) {
            $niveles[] = $tok;
        }
        if (preg_match('/^[ab][12]$/', $tok)) {
            $niveles[] = $tok;
        }
    }

    return array_unique($niveles);
}

/**
 * Determina si dos nombres de materia declaran niveles distintos y por lo
 * tanto son probablemente cursos diferentes (ej. "Inglés IV" vs "Inglés V").
 * Si alguno de los dos no declara ningún nivel, no se puede afirmar
 * conflicto (se retorna false) para no bloquear casos legítimos sin
 * numeración.
 */
function hayConflictoNivelMateria($materiaA, $materiaB)
{
    $nivelesA = extraerNivelesMateria($materiaA);
    $nivelesB = extraerNivelesMateria($materiaB);

    if (empty($nivelesA) || empty($nivelesB)) {
        return false;
    }

    return count(array_intersect($nivelesA, $nivelesB)) === 0;
}

/**
 * Diagnóstico de "posible código de materia equivocado": cuando una fila de
 * Labor no tiene oferta candidata bajo su propio código, busca en TODA la
 * Oferta del mismo periodo+identificacion (sin restringir código) la fila
 * cuyo NOMBRE DE MATERIA es más parecido al de Labor (criterio principal).
 * El programa se usa solo para desempatar. Se descarta cualquier candidato
 * cuyo nivel de curso (I..X, A1..C2) contradiga el de Labor, para evitar
 * falsos positivos entre materias de niveles consecutivos.
 *
 * Retorna null si no hay indicio suficiente, o un arreglo con el detalle.
 * CORREGIDO: Ahora prioriza ofertas con matrícula positiva para desempates.
 */
function detectarPosibleCodigoErroneo($filaLabor, array $ofertaMismoDocente, $umbralMateria = 0.70, $margenEmpate = 0.05)
{
    $materiaLabor = $filaLabor['materia_labor'] ?? $filaLabor['materia'] ?? '';
    $programaLabor = $filaLabor['programa_labor'] ?? $filaLabor['programa'] ?? '';
    $codigoLabor = normalizarCodigoMateria($filaLabor['codigo_materia'] ?? '');

    $candidatos = [];
    foreach ($ofertaMismoDocente as $o) {
        $codigoOferta = normalizarCodigoMateria($o['codigo_materia']);
        if ($codigoOferta === $codigoLabor) {
            continue;
        }

        // Descartar candidatos cuyo nivel de curso contradiga el de Labor
        if (hayConflictoNivelMateria($materiaLabor, $o['materia'])) {
            continue;
        }

        $simMateria = similitudTexto($materiaLabor, $o['materia']);
        if ($simMateria < $umbralMateria) {
            continue;
        }

        $cmpPrograma = compararProgramas($programaLabor, $o['programa']);
        $candidatos[] = [
            'sim_materia' => $simMateria,
            'sim_programa' => $cmpPrograma['similitud'] ?? 0,
            'oferta' => $o,
        ];
    }

    if (empty($candidatos)) {
        return null;
    }

    // Filtrar candidatos con la máxima similitud de materia (o dentro del margen de empate)
    $maxSimMateria = max(array_column($candidatos, 'sim_materia'));
    $empatados = array_values(array_filter($candidatos, function ($c) use ($maxSimMateria, $margenEmpate) {
        return $c['sim_materia'] >= ($maxSimMateria - $margenEmpate);
    }));

    // Ordenar con criterios de desempate: programa > matrícula > cupo > grupo > id
    usort($empatados, function (array $a, array $b): int {
        // 1. Mayor similitud de programa primero
        $cmpPrograma = ($b['sim_programa'] ?? 0) <=> ($a['sim_programa'] ?? 0);
        if ($cmpPrograma !== 0) {
            return $cmpPrograma;
        }

        $ofertaA = $a['oferta'] ?? [];
        $ofertaB = $b['oferta'] ?? [];

        // Obtener matriculados, tratando null como -1 (menor prioridad)
        $matA = isset($ofertaA['matriculados']) && is_numeric($ofertaA['matriculados'])
            ? (float)$ofertaA['matriculados']
            : -1;

        $matB = isset($ofertaB['matriculados']) && is_numeric($ofertaB['matriculados'])
            ? (float)$ofertaB['matriculados']
            : -1;

        // 2. Priorizar oferta con matriculados > 0 sobre matriculados = 0 o null
        $tieneMatriculaA = $matA > 0 ? 1 : 0;
        $tieneMatriculaB = $matB > 0 ? 1 : 0;

        $cmpTieneMatricula = $tieneMatriculaB <=> $tieneMatriculaA;
        if ($cmpTieneMatricula !== 0) {
            return $cmpTieneMatricula;
        }

        // 3. Mayor número de matriculados (si ambos tienen > 0 o ambos tienen 0)
        $cmpMatriculados = $matB <=> $matA;
        if ($cmpMatriculados !== 0) {
            return $cmpMatriculados;
        }

        // Obtener cupo, tratando null como -1
        $cupoA = isset($ofertaA['cupo']) && is_numeric($ofertaA['cupo'])
            ? (float)$ofertaA['cupo']
            : -1;

        $cupoB = isset($ofertaB['cupo']) && is_numeric($ofertaB['cupo'])
            ? (float)$ofertaB['cupo']
            : -1;

        // 4. Mayor cupo
        $cmpCupo = $cupoB <=> $cupoA;
        if ($cmpCupo !== 0) {
            return $cmpCupo;
        }

        // 5. Grupo ascendente (orden natural)
        $grupoA = (string)($ofertaA['grupo'] ?? '');
        $grupoB = (string)($ofertaB['grupo'] ?? '');
        $cmpGrupo = strnatcasecmp($grupoA, $grupoB);
        if ($cmpGrupo !== 0) {
            return $cmpGrupo;
        }

        // 6. ID ascendente como último desempate técnico
        return ((int)($ofertaA['id'] ?? PHP_INT_MAX))
            <=> ((int)($ofertaB['id'] ?? PHP_INT_MAX));
    });

    $mejor = $empatados[0];
    $mejorOferta = $mejor['oferta'];

    // Retornar todos los datos relevantes de la oferta seleccionada
    return [
        'oferta_id' => $mejorOferta['id'],
        'codigo_materia_labor' => $filaLabor['codigo_materia'] ?? null,
        'codigo_materia_oferta' => $mejorOferta['codigo_materia'],
        'materia_labor' => $materiaLabor,
        'materia_oferta' => $mejorOferta['materia'],
        'programa_labor' => $programaLabor,
        'programa_oferta' => $mejorOferta['programa'],
        'grupo_oferta' => $mejorOferta['grupo'],
        'matriculados_oferta' => $mejorOferta['matriculados'] ?? null,
        'cupo_oferta' => $mejorOferta['cupo'] ?? null,
        // Compatibilidad con frontend (sin guion bajo)
        'grupooferta' => $mejorOferta['grupo'],
        'matriculadosoferta' => $mejorOferta['matriculados'] ?? null,
        'cupooferta' => $mejorOferta['cupo'] ?? null,
        'programaoferta' => $mejorOferta['programa'],
        'materiaoferta' => $mejorOferta['materia'],
        'codigomateriaoferta' => $mejorOferta['codigo_materia'],
        'similitud_materia' => round($mejor['sim_materia'], 2),
        'similitud_programa' => round($mejor['sim_programa'], 2),
        'motivo' => 'Existe una oferta del mismo docente y período con un código de materia diferente '
            . "('" . $mejorOferta['codigo_materia'] . "' en Oferta vs '" . ($filaLabor['codigo_materia'] ?? '') . "' en Labor) "
            . 'cuyo nombre de materia es muy similar al de Labor y cuyo nivel de curso no contradice al de Labor. '
            . 'Verifique si el código de materia fue digitado incorrectamente en Labor o en Oferta.',
    ];
}


/**
 * Construye la clave de negocio normalizada usada para vincular una fila del
 * dashboard con observaciones_filas. NUNCA usar el id de labor/oferta porque
 * esas tablas se borran y recrean en cada carga SIMCA.
 */
function claveObsNormalizada($identificacion, $codigoMateria, $grupo) {
    return trim((string)$identificacion) . '|' .
           normalizarCodigoMateria($codigoMateria) . '|' .
           normalizarGrupo($grupo);
}

/**
 * Reconciliación automática de "subsanación":
 * - Recorre las observaciones ABIERTAS del periodo y las evalúa para SUBSANAR.
 * - Recorre las observaciones SUBSANADAS del periodo y las evalúa para REABRIR
 *   si el error persiste.
 * 
 * Retorna un mapa [claveNormalizada => ['id_observacion' => int, 'estado' => string]]
 * para que el llamador pueda inyectar el badge en cada fila del dashboard.
 */
function reconciliarObservaciones(PDO $pdo, string $periodo, array $alertasActuales): array {
    // 1. Construir índice de estado ACTUAL para FILAS INDIVIDUALES
    $indiceActual = [];
    foreach ($alertasActuales as $row) {
        if (empty($row['identificacion']) || empty($row['codigo_materia']) || empty($row['grupo'])) {
            continue;
        }
        $clave = claveObsNormalizada($row['identificacion'], $row['codigo_materia'], $row['grupo']);
        $indiceActual[$clave] = [
            'estado_alerta' => $row['estado_alerta'] ?? null,
            'alerta_labor'  => $row['alerta_labor'] ?? null,
            'alerta_duplicado_exacto' => $row['alerta_duplicado_exacto'] ?? null,
        ];
    }

    // 2. Obtener observaciones ABIERTAS y SUBSANADAS del periodo
    $stmtObs = $pdo->prepare("SELECT id, estado FROM observaciones WHERE periodo = :periodo AND estado IN ('ABIERTA', 'SUBSANADA')");
    $stmtObs->execute(['periodo' => $periodo]);
    $observaciones = $stmtObs->fetchAll(PDO::FETCH_ASSOC);

    $mapaBadge = [];

    foreach ($observaciones as $obs) {
        $idObs = $obs['id'];
        $estadoActualBD = $obs['estado'];

        $stmtFilas = $pdo->prepare("SELECT identificacion, codigo_materia, grupo, estado_alerta_original
                                     FROM observaciones_filas WHERE id_observacion = :id");
        $stmtFilas->execute(['id' => $idObs]);
        $filas = $stmtFilas->fetchAll(PDO::FETCH_ASSOC);

        $todasSubsanadas = true;
        $todasConError = false;
        $razones = [];

        foreach ($filas as $f) {
            $clave = claveObsNormalizada($f['identificacion'], $f['codigo_materia'], $f['grupo']);
            $actual = $indiceActual[$clave] ?? null;

            $sigueConError = false;

            if ($actual === null) {
                // La fila ya no existe en el dashboard actual
                // Se considera subsanada
                $razones[] = "Fila {$f['identificacion']}|{$f['codigo_materia']}|{$f['grupo']} ya no existe";
                continue;
            }

            $estadoOriginal = $f['estado_alerta_original'] ?? '';

            // --- CASO ESPECIAL: DUPLICIDAD_GRUPO_EXCESO_PE ---
            if ($estadoOriginal === 'DUPLICIDAD_GRUPO_EXCESO_PE') {
                $sigueConError = !empty($actual['alerta_labor']);
                if ($sigueConError) {
                    $razones[] = "Fila {$f['identificacion']}|{$f['codigo_materia']}|{$f['grupo']} aún tiene duplicidad (alerta_labor: {$actual['alerta_labor']})";
                } else {
                    $razones[] = "Fila {$f['identificacion']}|{$f['codigo_materia']}|{$f['grupo']} ya no tiene duplicidad";
                }
            } else {
                // --- CASO GENERAL ---
                $esOk = ($actual['estado_alerta'] ?? null) === 'OK';
                $esCeroMatriculados = ($actual['estado_alerta'] ?? null) === 'CERO_MATRICULADOS';
                $tieneAlertaLabor = !empty($actual['alerta_labor']);
                $tieneDuplicadoExacto = !empty($actual['alerta_duplicado_exacto']);
                $sigueConError = $esCeroMatriculados || !$esOk || $tieneAlertaLabor || $tieneDuplicadoExacto;
                if ($sigueConError) {
                    $razones[] = "Fila {$f['identificacion']}|{$f['codigo_materia']}|{$f['grupo']} aún tiene error: estado={$actual['estado_alerta']}, alerta_labor={$actual['alerta_labor']}, duplicado_exacto={$actual['alerta_duplicado_exacto']}";
                } else {
                    $razones[] = "Fila {$f['identificacion']}|{$f['codigo_materia']}|{$f['grupo']} ya está OK";
                }
            }

            if ($sigueConError) {
                $todasSubsanadas = false;
                $todasConError = true;
            }
        }

        // --- DECISIÓN FINAL ---
        $nuevoEstado = null;

        // Si estaba ABIERTA y todas están subsanadas -> SUBSANADA
        if ($estadoActualBD === 'ABIERTA' && $todasSubsanadas) {
            $nuevoEstado = 'SUBSANADA';
        }
        
        // Si estaba SUBSANADA y alguna tiene error -> REABRIR (volver a ABIERTA)
        if ($estadoActualBD === 'SUBSANADA' && !$todasSubsanadas) {
            $nuevoEstado = 'ABIERTA';
        }

        if ($nuevoEstado !== null) {
            $pdo->prepare("UPDATE observaciones SET estado = :estado WHERE id = :id")
                ->execute(['estado' => $nuevoEstado, 'id' => $idObs]);
            
            // Registrar en log para depuración
            error_log("Observación $idObs: $estadoActualBD -> $nuevoEstado. Razones: " . implode('; ', $razones));
        }

        // Construir el mapa de badge
        $estadoFinal = $todasSubsanadas ? 'SUBSANADA' : 'ABIERTA';
        foreach ($filas as $f) {
            $clave = claveObsNormalizada($f['identificacion'], $f['codigo_materia'], $f['grupo']);
            $mapaBadge[$clave] = ['id_observacion' => (int)$idObs, 'estado' => $estadoFinal];
        }
    }

    // 3. Observaciones CERRADAS (no se reabren)
    $stmtCerradas = $pdo->prepare("SELECT ofi.identificacion, ofi.codigo_materia, ofi.grupo, o.id, o.estado
                                    FROM observaciones_filas ofi
                                    INNER JOIN observaciones o ON o.id = ofi.id_observacion
                                    WHERE o.periodo = :periodo AND o.estado = 'CERRADA'");
    $stmtCerradas->execute(['periodo' => $periodo]);
    foreach ($stmtCerradas->fetchAll(PDO::FETCH_ASSOC) as $f) {
        $clave = claveObsNormalizada($f['identificacion'], $f['codigo_materia'], $f['grupo']);
        if (!isset($mapaBadge[$clave])) {
            $mapaBadge[$clave] = ['id_observacion' => (int)$f['id'], 'estado' => 'CERRADA'];
        }
    }

    return $mapaBadge;
}

function construirAuditoria(PDO $pdo, array $filtros)
{
    $periodo = trim((string)($filtros['periodo'] ?? ''));
    $facultad = trim((string)($filtros['facultad'] ?? ''));
    $depto = trim((string)($filtros['departamento'] ?? ''));
    $programa = trim((string)($filtros['programa'] ?? ''));
    $vinculacion = trim((string)($filtros['tipo_contrato'] ?? ''));
    $estado = trim((string)($filtros['estado'] ?? ''));
    $busqueda = trim((string)($filtros['search'] ?? ''));

    if (!$periodo) {
        throw new Exception('Se requiere el periodo.');
    }

    /* -----------------------------------------------------------------
       1. Cargar filas de Labor del período con filtros exclusivos de Labor
       ----------------------------------------------------------------- */
    $where = ['l.periodo = :periodo'];
    $params = ['periodo' => $periodo];

    if ($facultad !== '') {
        $where[] = 'l.facultad = :facultad';
        $params['facultad'] = $facultad;
    }
    if ($depto !== '') {
        $where[] = 'l.departamento = :departamento';
        $params['departamento'] = $depto;
    }
    if ($vinculacion !== '') {
        $where[] = 'l.tipo_contrato = :tipo_contrato';
        $params['tipo_contrato'] = $vinculacion;
    }

    $sqlWhere = implode(' AND ', $where);

    $sqlLabor = "SELECT l.id, l.periodo, l.facultad AS facultad_labor, l.departamento AS departamento_labor,
                        l.identificacion, l.docente, l.tipo_contrato, l.programa AS programa_labor,
                        l.materia AS materia_labor, l.codigo_materia, l.grupo, l.horas_teoricas, l.pe
                 FROM labor l
                 WHERE $sqlWhere
                 ORDER BY l.docente, l.codigo_materia, l.grupo";
    $stmtLabor = $pdo->prepare($sqlLabor);
    $stmtLabor->execute($params);
    $filasLabor = $stmtLabor->fetchAll(PDO::FETCH_ASSOC);

    /* -----------------------------------------------------------------
       2. Cargar filas de Oferta del período
       ----------------------------------------------------------------- */
    $stmtOferta = $pdo->prepare("SELECT id, periodo, identificacion, docente, programa, materia, codigo_materia, grupo, matriculados, cupo
                                  FROM oferta
                                  WHERE periodo = :periodo
                                  ORDER BY id");
    $stmtOferta->execute(['periodo' => $periodo]);
    $filasOferta = $stmtOferta->fetchAll(PDO::FETCH_ASSOC);

    /* -----------------------------------------------------------------
       3. Organizar Oferta por llave base ESTRICTA:
          periodo + identificacion + codigo_materia normalizado.
       ----------------------------------------------------------------- */
    $ofertaPorClave = [];
    foreach ($filasOferta as $idx => $o) {
        $clave = claveBaseCruce($o['periodo'], $o['identificacion'], $o['codigo_materia']);
        $ofertaPorClave[$clave][] = $idx;
    }

    // Índice auxiliar por docente/período (sin código) para el diagnóstico
    // de "posible código de materia equivocado".
    $ofertaPorDocentePeriodo = [];
    foreach ($filasOferta as $idx => $o) {
        $claveDoc = claveDocentePeriodo($o['periodo'], $o['identificacion']);
        $ofertaPorDocentePeriodo[$claveDoc][] = $idx;
    }

    $ofertaUsada = array_fill(0, count($filasOferta), false);

    /* -----------------------------------------------------------------
       4. ALGORITMO DE TRES PASADAS
       ----------------------------------------------------------------- */
    $alertas = [];

    // -----------------------------------------------------------------
    // PASADA 1: Coincidencia exacta por código + programa
    // -----------------------------------------------------------------
    $laborPendiente1 = []; // filas que no encontraron programa exacto

    foreach ($filasLabor as $l) {
        $clave = claveBaseCruce($l['periodo'], $l['identificacion'], $l['codigo_materia']);
        $candidatosIdx = $ofertaPorClave[$clave] ?? [];
        $libresIdx = array_values(array_filter($candidatosIdx, function ($idx) use ($ofertaUsada) {
            return !$ofertaUsada[$idx];
        }));

        // Buscar oferta con el mismo programa exacto
        $programaLaborNormalizado = normalizarTextoComparacion($l['programa_labor'] ?? '');
        $elegidoIdx = null;
        $mejorComparacion = null;

        if ($programaLaborNormalizado !== '') {
            foreach ($libresIdx as $idx) {
                $ofertaCandidata = $filasOferta[$idx];
                $programaOfertaNormalizado = normalizarTextoComparacion($ofertaCandidata['programa'] ?? '');
                if ($programaOfertaNormalizado === $programaLaborNormalizado) {
                    $elegidoIdx = $idx;
                    break;
                }
            }
        }

        if ($elegidoIdx !== null) {
            // Asignar esta oferta
            $ofertaUsada[$elegidoIdx] = true;
            $o = $filasOferta[$elegidoIdx];
            $comparacion = compararProgramas($l['programa_labor'], $o['programa']);
            $estadoAlerta = 'OK';

            // Preparar datos de oferta asociada
            $ofertaData = [
                'oferta_id_asociada' => $o['id'],
                'programa_oferta' => $o['programa'],
                'materia_oferta' => $o['materia'],
                'codigo_materia_oferta' => $o['codigo_materia'],
                'grupo_oferta' => $o['grupo'],
                'matriculados_oferta' => $o['matriculados'] ?? null,
                'cupo_oferta' => $o['cupo'] ?? null,
                // Compatibilidad frontend
                'programaoferta' => $o['programa'],
                'materiaoferta' => $o['materia'],
                'codigomateriaoferta' => $o['codigo_materia'],
                'grupooferta' => $o['grupo'],
                'matriculadosoferta' => $o['matriculados'] ?? null,
                'cupooferta' => $o['cupo'] ?? null,
            ];

            $alertas[] = array_merge($l, $ofertaData, [
                'fuente_principal' => 'LABOR',
                'estado_alerta' => $estadoAlerta,
                'es_prestacion_servicio' => 0,

                'coincidencia_programa' => 'EXACTA',
                'similitud_programa' => 1.00,
                'motivo_programa' => 'Coincidencia exacta de programa y código de materia.',
                'detalle_codigo_posible_error' => null,
            ]);
        } else {
            // No hay oferta con programa exacto: pasar a la siguiente pasada
            $laborPendiente1[] = $l;
        }
    }

    // -----------------------------------------------------------------
    // PASADA 2: Coincidencia por código (programa diferente)
    // -----------------------------------------------------------------
    $laborPendiente2 = [];

    foreach ($laborPendiente1 as $l) {
        $clave = claveBaseCruce($l['periodo'], $l['identificacion'], $l['codigo_materia']);
        $candidatosIdx = $ofertaPorClave[$clave] ?? [];
        $libresIdx = array_values(array_filter($candidatosIdx, function ($idx) use ($ofertaUsada) {
            return !$ofertaUsada[$idx];
        }));

        if (!empty($libresIdx)) {
            // Elegir la mejor oferta entre las que tienen el mismo código (priorizando similitud de programa)
            $mejorIdx = null;
            $mejorSimilitud = -1;
            $mejorComparacion = null;

            foreach ($libresIdx as $idx) {
                $ofertaCandidata = $filasOferta[$idx];
                $comparacion = compararProgramas($l['programa_labor'], $ofertaCandidata['programa']);
                $similitud = $comparacion['similitud'] ?? -1;
                if ($similitud > $mejorSimilitud) {
                    $mejorSimilitud = $similitud;
                    $mejorIdx = $idx;
                    $mejorComparacion = $comparacion;
                }
            }

            if ($mejorIdx !== null) {
                $ofertaUsada[$mejorIdx] = true;
                $o = $filasOferta[$mejorIdx];
                $estadoAlerta = 'REVISAR_PROGRAMA_DIFERENTE';

                $ofertaData = [
                    'oferta_id_asociada' => $o['id'],
                    'programa_oferta' => $o['programa'],
                    'materia_oferta' => $o['materia'],
                    'codigo_materia_oferta' => $o['codigo_materia'],
                    'grupo_oferta' => $o['grupo'],
                    'matriculados_oferta' => $o['matriculados'] ?? null,
                    'cupo_oferta' => $o['cupo'] ?? null,
                    // Compatibilidad frontend
                    'programaoferta' => $o['programa'],
                    'materiaoferta' => $o['materia'],
                    'codigomateriaoferta' => $o['codigo_materia'],
                    'grupooferta' => $o['grupo'],
                    'matriculadosoferta' => $o['matriculados'] ?? null,
                    'cupooferta' => $o['cupo'] ?? null,
                ];

                $alertas[] = array_merge($l, $ofertaData, [
                    'fuente_principal' => 'LABOR',
                    'estado_alerta' => $estadoAlerta,
                    'es_prestacion_servicio' => 0,

                    'coincidencia_programa' => $mejorComparacion['tipo'],
                    'similitud_programa' => $mejorComparacion['similitud'],
                    'motivo_programa' => $mejorComparacion['motivo'],
                    'detalle_codigo_posible_error' => null,
                ]);
                continue;
            }
        }

        // No hay oferta con el mismo código: pasar a la tercera pasada
        $laborPendiente2[] = $l;
    }

    // -----------------------------------------------------------------
    // PASADA 3: Diagnóstico de código diferente (detectarPosibleCodigoErroneo)
    // CORREGIDO: ahora toma los datos de la oferta del diagnóstico y los asigna correctamente.
    // -----------------------------------------------------------------
    foreach ($laborPendiente2 as $l) {
        $claveDoc = claveDocentePeriodo($l['periodo'], $l['identificacion']);
        $candidatosDocIdx = $ofertaPorDocentePeriodo[$claveDoc] ?? [];
        $ofertaMismoDocenteLibre = [];
        foreach ($candidatosDocIdx as $idx) {
            if (!$ofertaUsada[$idx]) {
                $ofertaMismoDocenteLibre[] = ['__idx' => $idx] + $filasOferta[$idx];
            }
        }

        $diagnosticoCodigo = detectarPosibleCodigoErroneo($l, $ofertaMismoDocenteLibre);

        // Preparar datos básicos de comparación de programa
        $coincidencia = 'NO_APLICA';
        $similitud = null;
        $motivoPrograma = 'Sin oferta disponible para comparar programa.';

        if ($diagnosticoCodigo !== null) {
            // Usar los datos del diagnóstico para la comparación de programa
            $comparacionConOferta = compararProgramas($l['programa_labor'], $diagnosticoCodigo['programa_oferta']);
            $coincidencia = $comparacionConOferta['tipo'];
            $similitud = $comparacionConOferta['similitud'];
            $motivoPrograma = $comparacionConOferta['motivo'];
            
            // Marcar la oferta como usada y preparar sus datos
            $ofertaId = $diagnosticoCodigo['oferta_id'];
            foreach ($ofertaMismoDocenteLibre as $cand) {
                if ((int)$cand['id'] === (int)$ofertaId) {
                    $ofertaUsada[$cand['__idx']] = true;
                    break;
                }
            }
            
            $estadoFinal = 'REVISAR_CODIGO_MATERIA_DIFERENTE';
            $esPrestacion = 0;
            
            // Datos de la oferta desde el diagnóstico
            $ofertaData = [
                'oferta_id_asociada' => $diagnosticoCodigo['oferta_id'],
                'programa_oferta' => $diagnosticoCodigo['programa_oferta'],
                'materia_oferta' => $diagnosticoCodigo['materia_oferta'],
                'codigo_materia_oferta' => $diagnosticoCodigo['codigo_materia_oferta'],
                'grupo_oferta' => $diagnosticoCodigo['grupo_oferta'],
                'matriculados_oferta' => $diagnosticoCodigo['matriculados_oferta'],
                'cupo_oferta' => $diagnosticoCodigo['cupo_oferta'],
                // Compatibilidad frontend
                'programaoferta' => $diagnosticoCodigo['programaoferta'] ?? $diagnosticoCodigo['programa_oferta'],
                'materiaoferta' => $diagnosticoCodigo['materiaoferta'] ?? $diagnosticoCodigo['materia_oferta'],
                'codigomateriaoferta' => $diagnosticoCodigo['codigomateriaoferta'] ?? $diagnosticoCodigo['codigo_materia_oferta'],
                'grupooferta' => $diagnosticoCodigo['grupooferta'] ?? $diagnosticoCodigo['grupo_oferta'],
                'matriculadosoferta' => $diagnosticoCodigo['matriculadosoferta'] ?? $diagnosticoCodigo['matriculados_oferta'],
                'cupooferta' => $diagnosticoCodigo['cupooferta'] ?? $diagnosticoCodigo['cupo_oferta'],
            ];
        } else {
            // No hay diagnóstico: OFERTA_INEXISTENTE
            $estadoFinal = 'OFERTA_INEXISTENTE';
            $esPrestacion = 1;
            $ofertaData = [
                'oferta_id_asociada' => null,
                'programa_oferta' => null,
                'materia_oferta' => null,
                'codigo_materia_oferta' => null,
                'grupo_oferta' => null,
                'matriculados_oferta' => null,
                'cupo_oferta' => null,
                'programaoferta' => null,
                'materiaoferta' => null,
                'codigomateriaoferta' => null,
                'grupooferta' => null,
                'matriculadosoferta' => null,
                'cupooferta' => null,
            ];
            
            // Comparación de programa sin oferta
            $comparacionSinOferta = compararProgramas($l['programa_labor'], null);
            $coincidencia = $comparacionSinOferta['tipo'];
            $similitud = $comparacionSinOferta['similitud'];
            $motivoPrograma = $comparacionSinOferta['motivo'];
        }

        $alertas[] = array_merge($l, $ofertaData, [
            'fuente_principal' => 'LABOR',
            'estado_alerta' => $estadoFinal,
            'es_prestacion_servicio' => $esPrestacion,

            'coincidencia_programa' => $coincidencia,
            'similitud_programa' => $similitud,
            'motivo_programa' => $motivoPrograma,
            'detalle_codigo_posible_error' => $diagnosticoCodigo,
        ]);
    }

    /* -----------------------------------------------------------------
       5. Oferta no usada -> LABOR_INEXISTENTE
       ----------------------------------------------------------------- */
    foreach ($filasOferta as $idx => $o) {
        if (!$ofertaUsada[$idx]) {
            $alertas[] = filaOfertaSinLabor($o);
        }
    }

    /* -----------------------------------------------------------------
       Filtro de Programa (aplica sobre programa_labor o programa_oferta)
       ----------------------------------------------------------------- */
    if ($programa !== '') {
        $alertas = array_values(array_filter($alertas, function ($row) use ($programa) {
            return (isset($row['programa_labor']) && $row['programa_labor'] === $programa)
                || (isset($row['programa_oferta']) && $row['programa_oferta'] === $programa);
        }));
    }

    /* -----------------------------------------------------------------
       Filtros exclusivos de Labor (Facultad, Departamento, Vinculación):
       excluir LABOR_INEXISTENTE porque esos atributos no existen en Oferta.
       ----------------------------------------------------------------- */
    if ($facultad !== '' || $depto !== '' || $vinculacion !== '') {
        $alertas = array_values(array_filter($alertas, function ($row) {
            return ($row['estado_alerta'] ?? '') !== 'LABOR_INEXISTENTE';
        }));
    }

    /* -----------------------------------------------------------------
       6. ALERTA INTERNA DUPLICIDAD_GRUPO_EXCESO_PE
       ----------------------------------------------------------------- */
    $sqlDuplicidad = "SELECT periodo, TRIM(codigo_materia) AS codigo_materia,
                              TRIM(LOWER(programa)) AS programa_normalizado, MAX(programa) AS programa,
                              TRIM(grupo) AS grupo, COUNT(*) AS cantidad_registros,
                              SUM(horas_teoricas) AS horas_totales, MAX(pe) AS pe_unico,
                              COUNT(DISTINCT identificacion) AS docentes_involucrados,
                              GROUP_CONCAT(DISTINCT CONCAT(docente, ' (', identificacion, ')') ORDER BY docente SEPARATOR '; ') AS docentes
                       FROM labor
                       WHERE periodo = :periodo
                       GROUP BY periodo, TRIM(codigo_materia), TRIM(LOWER(programa)), TRIM(grupo)
                       HAVING COUNT(*) >= 2 AND SUM(horas_teoricas) > MAX(pe)";
    $stmtDup = $pdo->prepare($sqlDuplicidad);
    $stmtDup->execute(['periodo' => $periodo]);
    $duplicados = $stmtDup->fetchAll(PDO::FETCH_ASSOC);

    $mapaDuplicidad = [];
    foreach ($duplicados as $d) {
        $clave = trim((string)$d['codigo_materia']) . '|' . mb_strtolower(trim((string)$d['programa_normalizado'])) . '|' . trim((string)$d['grupo']);
        $mapaDuplicidad[$clave] = $d;
    }

    /* -----------------------------------------------------------------
       7. NUEVA ALERTA: LABOR_DUPLICADO_EXACTO
       ----------------------------------------------------------------- */
    $sqlDuplicadoExacto = "SELECT periodo, identificacion, TRIM(codigo_materia) AS codigo_materia, TRIM(grupo) AS grupo,
                                  COUNT(*) AS cantidad_registros,
                                  GROUP_CONCAT(DISTINCT programa ORDER BY programa SEPARATOR ' | ') AS programas,
                                  GROUP_CONCAT(DISTINCT CONCAT(docente, ' (id ', id, ')') ORDER BY docente SEPARATOR '; ') AS registros
                           FROM labor
                           WHERE periodo = :periodo
                           GROUP BY periodo, identificacion, TRIM(codigo_materia), TRIM(grupo)
                           HAVING COUNT(*) >= 2";
    $stmtDupExacto = $pdo->prepare($sqlDuplicadoExacto);
    $stmtDupExacto->execute(['periodo' => $periodo]);
    $duplicadosExacto = $stmtDupExacto->fetchAll(PDO::FETCH_ASSOC);

    $mapaDuplicadoExacto = [];
    foreach ($duplicadosExacto as $d) {
        $clave = trim((string)$d['identificacion']) . '|' . trim((string)$d['codigo_materia']) . '|' . trim((string)$d['grupo']);
        $mapaDuplicadoExacto[$clave] = $d;
    }

    foreach ($alertas as &$row) {
        $row['alerta_labor'] = null;
        $row['detalle_alerta_labor'] = null;
        $row['alerta_duplicado_exacto'] = null;
        $row['detalle_alerta_duplicado'] = null;

        if (($row['fuente_principal'] ?? 'LABOR') !== 'LABOR') {
            continue;
        }

        // Duplicidad por grupo/PE
        $claveDup = trim((string)($row['codigo_materia'] ?? '')) . '|' . mb_strtolower(trim((string)($row['programa_labor'] ?? ''))) . '|' . trim((string)($row['grupo'] ?? ''));
        if (isset($mapaDuplicidad[$claveDup])) {
            $dup = $mapaDuplicidad[$claveDup];
            $row['alerta_labor'] = 'DUPLICIDAD_GRUPO_EXCESO_PE';
            $row['detalle_alerta_labor'] = [
                'periodo' => $dup['periodo'],
                'codigo_materia' => $dup['codigo_materia'],
                'programa' => $dup['programa'],
                'grupo' => $dup['grupo'],
                'cantidad_registros' => (int)$dup['cantidad_registros'],
                'docentes_involucrados' => (int)$dup['docentes_involucrados'],
                'docentes' => $dup['docentes'],
                'horas_totales' => (float)$dup['horas_totales'],
                'pe_unico' => (float)$dup['pe_unico'],
                'exceso_horas' => (float)$dup['horas_totales'] - (float)$dup['pe_unico'],
                'motivo' => 'La suma de horas teóricas de este programa, materia y grupo supera el PE.',
            ];
        }

        // Duplicado exacto
        $claveExacto = trim((string)($row['identificacion'] ?? '')) . '|' . trim((string)($row['codigo_materia'] ?? '')) . '|' . trim((string)($row['grupo'] ?? ''));
        if (isset($mapaDuplicadoExacto[$claveExacto])) {
            $dupExacto = $mapaDuplicadoExacto[$claveExacto];
            $row['alerta_duplicado_exacto'] = 'LABOR_DUPLICADO_EXACTO';
            $row['detalle_alerta_duplicado'] = [
                'periodo' => $dupExacto['periodo'],
                'identificacion' => $dupExacto['identificacion'],
                'codigo_materia' => $dupExacto['codigo_materia'],
                'grupo' => $dupExacto['grupo'],
                'cantidad_registros' => (int)$dupExacto['cantidad_registros'],
                'programas' => $dupExacto['programas'],
                'registros' => $dupExacto['registros'],
                'motivo' => 'La fuente Labor repite la misma materia y grupo para este docente y período, con o sin cambio de programa.',
            ];
        }
    }
    unset($row);

        /* -----------------------------------------------------------------
       8. Histórico por materia, independiente del docente
       ----------------------------------------------------------------- */
    $mapaOrden = $pdo->query("SELECT periodo, orden_cronologico FROM periodo_catalogo")->fetchAll(PDO::FETCH_KEY_PAIR);
    $prevPeriodos = [];
    $periodoInmediato = null;
    if (isset($mapaOrden[$periodo])) {
        $ordenActual = (int)$mapaOrden[$periodo];
        foreach ($mapaOrden as $p => $orden) {
            if ((int)$orden < $ordenActual) {
                $prevPeriodos[$p] = (int)$orden;
            }
        }
        arsort($prevPeriodos, SORT_NUMERIC);
        $periodoInmediato = !empty($prevPeriodos) ? array_key_first($prevPeriodos) : null;
    }

    $ofeHist = [];
    if (!empty($alertas)) {
        $rsHist = $pdo->query("SELECT periodo, TRIM(UPPER(codigo_materia)) AS codigo_materia, COUNT(*) AS grupos,
                                       COALESCE(SUM(matriculados), 0) AS matriculados, MAX(cupo) AS cupo
                                FROM oferta
                                GROUP BY periodo, TRIM(UPPER(codigo_materia))");
        foreach ($rsHist->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $ofeHist[$r['periodo']][$r['codigo_materia']] = [
                'grupos' => (int)$r['grupos'],
                'matriculados' => (int)$r['matriculados'],
                'cupo' => (int)($r['cupo'] ?? 0),
            ];
        }
    }

    $labHist = [];
    if (!empty($alertas)) {
        $rsHistLab = $pdo->query("SELECT periodo, TRIM(UPPER(codigo_materia)) AS codigo_materia, COUNT(*) AS grupos
                                   FROM labor
                                   GROUP BY periodo, TRIM(UPPER(codigo_materia))");
        foreach ($rsHistLab->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $labHist[$r['periodo']][$r['codigo_materia']] = (int)$r['grupos'];
        }
    }

    foreach ($alertas as &$row) {
        $row['alerta_historica'] = null;
        $row['salto_detalle'] = null;

        $codigo = trim((string)($row['codigo_materia'] ?? ''));
        if ($codigo === '') {
            continue;
        }
        $codigoNorm = mb_strtoupper($codigo, 'UTF-8');

        $actual = $ofeHist[$periodo][$codigoNorm] ?? null;
        if ($actual === null) {
            continue;
        }

        // ---- NUEVO: SIN_HISTORIAL (basado en labor, no en oferta) ----
        // Se considera "sin historial" si NO existe ningún registro de
        // labor con este mismo codigo_materia en NINGÚN período anterior
        // al actual, independientemente del docente/programa.
        $hayLaborPrevio = false;
        foreach ($prevPeriodos as $p => $orden) {
            if (isset($labHist[$p][$codigoNorm])) {
                $hayLaborPrevio = true;
                break;
            }
        }

        if (!$hayLaborPrevio) {
            $estadoActual = $row['estado_alerta'] ?? '';
            if (($row['fuente_principal'] ?? 'LABOR') === 'LABOR'
                && in_array($estadoActual, ['OK', 'CERO_MATRICULADOS'], true)
            ) {
                $row['alerta_historica'] = 'SIN_HISTORIAL';
                $row['salto_detalle'] = [
                    'periodo_anterior' => null,
                    'periodo_inmediato' => $periodoInmediato,
                    'base_es_inmediato' => false,
                    'grupos_prev' => 0,
                    'grupos_act' => 0,
                    'mat_prev' => 0,
                    'mat_act' => 0,
                    'cupo' => 0,
                    'motivo' => 'Esta materia aparece por primera vez en Labor; no existe registro en períodos anteriores.',
                ];
            }
            continue;
        }

        $periodoBase = null;
        $base = null;
        foreach ($prevPeriodos as $p => $orden) {
            if (isset($ofeHist[$p][$codigoNorm])) {
                $periodoBase = $p;
                $base = $ofeHist[$p][$codigoNorm];
                break;
            }
        }
        if ($periodoBase === null) {
            continue;
        }

        $gruposPrev = (int)$base['grupos'];
        $gruposAct = (int)$actual['grupos'];
        $matPrev = (int)$base['matriculados'];
        $matAct = (int)$actual['matriculados'];

        $diferencia = $gruposAct - $gruposPrev;
        if ($diferencia <= 0) {
            continue;
        }

        $esSalto = ($diferencia >= 2) || ($gruposPrev === 0 && $gruposAct >= 1) || ($gruposAct >= $gruposPrev * 1.5);
        if (!$esSalto) {
            continue;
        }

        $cupo = (int)($actual['cupo'] ?: $base['cupo']);
        $matPorGrupoPrev = $gruposPrev > 0 ? ($matPrev / $gruposPrev) : 0;
        $matPorGrupoAct = $gruposAct > 0 ? ($matAct / $gruposAct) : 0;
        $matriculaAcompana = ($matAct > 0) && ($matPorGrupoAct >= $matPorGrupoPrev * 0.90);
        $demandaExigeCupo = ($cupo > 0) && ($gruposPrev > 0) && (($matAct / $gruposPrev) > $cupo);
        $justificado = $matriculaAcompana || $demandaExigeCupo;

        $row['alerta_historica'] = $justificado ? 'SALTO_JUSTIFICADO' : 'SALTO_FUERTE';
        $row['salto_detalle'] = [
            'periodo_anterior' => $periodoBase,
            'periodo_inmediato' => $periodoInmediato,
            'base_es_inmediato' => ($periodoBase === $periodoInmediato),
            'grupos_prev' => $gruposPrev,
            'grupos_act' => $gruposAct,
            'mat_prev' => $matPrev,
            'mat_act' => $matAct,
            'cupo' => $cupo,
            'motivo' => $justificado
                ? ($demandaExigeCupo ? 'Demanda supera el cupo de los grupos anteriores.' : 'La matrícula creció proporcionalmente.')
                : 'La matrícula no justifica el aumento de grupos.',
        ];
    }
    unset($row);
    /* -----------------------------------------------------------------
       9. COBERTURA HORARIA POR MATERIA + PROGRAMA
       
       CORREGIDO: SOLO se evalúa sobre filas que NO tienen oferta asignada
       (es decir, estado OFERTA_INEXISTENTE). Las filas que ya tienen una
       oferta asignada (OK o REVISAR_PROGRAMA_DIFERENTE) no deben ser
       evaluadas por cobertura, ya que su cobertura ya está cubierta por
       la oferta asignada.
       ----------------------------------------------------------------- */
    $ofertaCobertura = [];
    $stmtOfertaCobertura = $pdo->prepare("SELECT TRIM(codigo_materia) AS codigo_materia, TRIM(LOWER(programa)) AS programa_normalizado, COUNT(*) AS ofertas_disponibles
                                           FROM oferta WHERE periodo = :periodo
                                           GROUP BY TRIM(codigo_materia), TRIM(LOWER(programa))");
    $stmtOfertaCobertura->execute(['periodo' => $periodo]);
    foreach ($stmtOfertaCobertura->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $clave = trim((string)$r['codigo_materia']) . '|' . trim((string)$r['programa_normalizado']);
        $ofertaCobertura[$clave] = (int)$r['ofertas_disponibles'];
    }

    // Solo contamos filas que NO tienen oferta asignada (estado OFERTA_INEXISTENTE)
    $laborCobertura = [];
    foreach ($alertas as $row) {
        if (($row['fuente_principal'] ?? 'LABOR') !== 'LABOR') {
            continue;
        }
        if ($row['estado_alerta'] !== 'OFERTA_INEXISTENTE') {
            continue;
        }
        $codigo = trim((string)($row['codigo_materia'] ?? ''));
        $progNorm = mb_strtolower(trim((string)($row['programa_labor'] ?? '')));
        $clave = $codigo . '|' . $progNorm;
        if (!isset($laborCobertura[$clave])) {
            $laborCobertura[$clave] = ['horas_totales' => 0.0, 'pe_valores' => [], 'filas' => [], 'codigo' => $codigo];
        }
        $laborCobertura[$clave]['horas_totales'] += (float)($row['horas_teoricas'] ?? 0);
        $pe = (float)($row['pe'] ?? 0);
        if ($pe > 0) {
            $laborCobertura[$clave]['pe_valores'][] = $pe;
        }
        $laborCobertura[$clave]['filas'][] = $row['id'];
    }

    foreach ($laborCobertura as $clave => &$info) {
        $peUnico = !empty($info['pe_valores']) ? max($info['pe_valores']) : 0;
        $ofertasDisponibles = $ofertaCobertura[$clave] ?? 0;
        $ofertasNecesarias = $peUnico > 0 ? (int)ceil($info['horas_totales'] / $peUnico) : count($info['filas']);
        $info['pe_unico'] = $peUnico;
        $info['ofertas_disponibles'] = $ofertasDisponibles;
        $info['ofertas_necesarias'] = $ofertasNecesarias;
        $info['hay_falta_cobertura'] = $ofertasDisponibles < $ofertasNecesarias;
    }
    unset($info);

    foreach ($alertas as &$row) {
        if (($row['fuente_principal'] ?? 'LABOR') !== 'LABOR') {
            continue;
        }
        if ($row['estado_alerta'] !== 'OFERTA_INEXISTENTE') {
            continue;
        }
        $codigo = trim((string)($row['codigo_materia'] ?? ''));
        $progNorm = mb_strtolower(trim((string)($row['programa_labor'] ?? '')));
        $clave = $codigo . '|' . $progNorm;
        $info = $laborCobertura[$clave] ?? null;
        if (!$info || !$info['hay_falta_cobertura']) {
            continue;
        }
        $row['estado_alerta'] = 'FALTA_GRUPO_EN_OFERTA';
        $row['detalle_cobertura'] = [
            'codigo_materia' => $codigo,
            'programa' => $row['programa_labor'] ?? null,
            'horas_labor' => $info['horas_totales'],
            'pe' => $info['pe_unico'],
            'ofertas_disponibles' => $info['ofertas_disponibles'],
            'ofertas_necesarias' => $info['ofertas_necesarias'],
            'motivo' => 'La carga horaria acumulada de Labor para este código de materia y este programa supera la cobertura disponible en Oferta para ese mismo programa.',
        ];
    }
    unset($row);

    /* -----------------------------------------------------------------
       9b. NUEVO ESTADO PRINCIPAL: CERO_MATRICULADOS

       Regla: una fila de fuente LABOR cuyo único estado principal sea OK,
       pero cuya oferta asociada (real) tenga matriculados = 0, pasa a
       CERO_MATRICULADOS. No aplica cuando ya existe un hallazgo de mayor
       prioridad (OFERTA_INEXISTENTE, FALTA_GRUPO_EN_OFERTA,
       REVISAR_PROGRAMA_DIFERENTE, REVISAR_CODIGO_MATERIA_DIFERENTE,
       LABOR_INEXISTENTE) porque esos conservan su estado principal y la
       matrícula cero se refleja como alerta complementaria / badge secundario.
       La validación de cero es estricta y NUNCA usa $fila['matriculados']
       ni un fallback 0 cuando no hay oferta.
       ----------------------------------------------------------------- */
    foreach ($alertas as &$row) {
        if (($row['fuente_principal'] ?? 'LABOR') !== 'LABOR') {
            continue;
        }
        if (($row['estado_alerta'] ?? '') !== 'OK') {
            continue;
        }
        if (empty($row['oferta_id_asociada'])) {
            continue;
        }
        $matriculadosCruce =
            $row['matriculados_oferta']
            ?? $row['matriculadosoferta']
            ?? null;
        $esCeroMatriculados =
            $matriculadosCruce !== null
            && $matriculadosCruce !== ''
            && is_numeric($matriculadosCruce)
            && (float)$matriculadosCruce === 0.0;
        if ($esCeroMatriculados) {
            $row['estado_alerta'] = 'CERO_MATRICULADOS';
        }
    }
    unset($row);
          /* -----------------------------------------------------------------
       9c. ALERTA SECUNDARIA: CUPO_SUBUTILIZADO  (CORREGIDO)

       Regla (Interpretación C — respeta la capacidad real de cada grupo):
       SOLO se consideran los grupos de Oferta con cruce GENUINO por
       código con Labor (mismo periodo + identificación + codigo_materia).
       - Las filas huérfanas (fuente_principal='OFERTA') nunca cuentan.
       - Las filas cuyo cruce se resolvió por REVISAR_CODIGO_MATERIA_
         DIFERENTE tampoco cuentan para la materia de la OFerta asociada:
         ese diagnóstico consume ofertas libres de OTRA materia del mismo
         docente (pasada 3), así que su oferta_id_asociada NO prueba que
         el grupo pertenezca realmente a esta materia. Contarlo inflaba
         el cálculo con grupos huérfanos (caso MUS0424 / 175297).
       - La agrupación es por codigo_materia REAL de Oferta.

       Para cada materia (codigo_materia + periodo):
       1. Se agrupan los grupos de Oferta únicos con cruce genuino.
       2. Se calcula cuántos grupos harían falta para alojar la matrícula
          total usando los cupos REALES (bin packing greedy).
       3. Si sobran grupos Y al quedarnos solo con los necesarios sigue
          habiendo al menos un 10% de holgura de cupo, se activa la alerta.
       ----------------------------------------------------------------- */
    $cupoSubutilizado = [];
    if (!empty($alertas)) {

        // ============================================================
        // DEBUG TEMPORAL — BORRAR DESPUÉS DE VERIFICAR LA CORRECCIÓN
        // Vuelca las filas MUS0424 relevantes y las claves finales del
        // mapa de grupos cruzados, para confirmar que 175297 ya no entra.
        // ============================================================
        if ($periodo === '2026.2') {
            $debugMUS = array_values(array_map(function ($r) {
                return [
                    'docente' => $r['docente'] ?? null,
                    'fuente_principal' => $r['fuente_principal'] ?? null,
                    'estado_alerta' => $r['estado_alerta'] ?? null,
                    'codigo_materia' => $r['codigo_materia'] ?? null,
                    'codigo_materia_oferta' => $r['codigo_materia_oferta'] ?? null,
                    'grupo_oferta' => $r['grupo_oferta'] ?? null,
                    'oferta_id_asociada' => $r['oferta_id_asociada'] ?? null,
                ];
            }, array_filter($alertas, function ($r) {
                return ($r['codigo_materia'] ?? '') === 'MUS0424'
                    || ($r['codigo_materia_oferta'] ?? '') === 'MUS0424';
            })));
            error_log('DEBUG 9c MUS0424 filas: ' . json_encode($debugMUS));
        }
        // ============ FIN DEBUG TEMPORAL ============

        // Construir el mapa de grupos de Oferta con cruce GENUINO.
        // Clave: codigo_materia_oferta_normalizado + '|' + grupo_oferta_normalizado
        // Valor: ['matriculados' => int, 'cupo' => int]
        $gruposOfertaCruzados = [];
        foreach ($alertas as $rowAux) {
            // Solo filas de docente real (cruce Labor↔Oferta). Las filas
            // huérfanas (fuente 'OFERTA') nunca cuentan.
            if (($rowAux['fuente_principal'] ?? 'LABOR') !== 'LABOR') {
                continue;
            }

            // CORRECCIÓN (anti-inflado): exigir cruce genuino por código.
            // oferta_id_asociada NO es suficiente: la pasada 3 (diagnóstico
            // de "posible código erróneo") puede asignar a una fila Labor
            // una oferta de OTRA materia del mismo docente. Esas filas
            // tienen codigo_materia (Labor) != codigo_materia_oferta, y su
            // grupo NO pertenece realmente a la materia de la oferta.
            $codLaborAux = normalizarCodigoMateria($rowAux['codigo_materia'] ?? '');
            $codOfertaAux = normalizarCodigoMateria(
                $rowAux['codigo_materia_oferta']
                ?? $rowAux['codigomateriaoferta']
                ?? ''
            );
            if ($codOfertaAux === '' || $codOfertaAux !== $codLaborAux) {
                continue;
            }

            $grupoOfAux = trim((string)($rowAux['grupo_oferta'] ?? $rowAux['grupooferta'] ?? ''));
            $ofertaIdAux = $rowAux['oferta_id_asociada'] ?? null;

            if ($grupoOfAux === '' || empty($ofertaIdAux)) {
                continue;
            }

            $grupoOfAuxNorm = mb_strtoupper($grupoOfAux, 'UTF-8');
            $claveAux = $codOfertaAux . '|' . $grupoOfAuxNorm;

            $matAux = $rowAux['matriculados_oferta']
                ?? $rowAux['matriculadosoferta']
                ?? null;
            $cupoAux = $rowAux['cupo_oferta']
                ?? $rowAux['cupooferta']
                ?? null;

            if (!isset($gruposOfertaCruzados[$claveAux])) {
                $gruposOfertaCruzados[$claveAux] = [
                    'matriculados' => is_numeric($matAux) ? (int)$matAux : 0,
                    'cupo'         => is_numeric($cupoAux) ? (int)$cupoAux : 0,
                ];
            } else {
                // Si hubiera repetidos por co-docencia, tomamos el máximo
                if (is_numeric($matAux) && (int)$matAux > $gruposOfertaCruzados[$claveAux]['matriculados']) {
                    $gruposOfertaCruzados[$claveAux]['matriculados'] = (int)$matAux;
                }
                if (is_numeric($cupoAux) && (int)$cupoAux > $gruposOfertaCruzados[$claveAux]['cupo']) {
                    $gruposOfertaCruzados[$claveAux]['cupo'] = (int)$cupoAux;
                }
            }
        }

        // ============================================================
        // DEBUG TEMPORAL — BORRAR DESPUÉS DE VERIFICAR LA CORRECCIÓN
        // ============================================================
        if ($periodo === '2026.2') {
            error_log('DEBUG 9c gruposCruzados MUS0424: '
                . json_encode(array_keys(array_filter(array_keys($gruposOfertaCruzados), function ($k) {
                    return strpos($k, 'MUS0424|') === 0;
                }))));
        }
        // ============ FIN DEBUG TEMPORAL ============

        // Agrupar los grupos cruzados por codigo_materia
        $gruposPorMateria = [];
        foreach ($gruposOfertaCruzados as $claveAux => $infoAux) {
            [$codAux, $grupoOfAux] = explode('|', $claveAux, 2);
            $gruposPorMateria[$codAux][] = [
                'matriculados' => $infoAux['matriculados'],
                'cupo'         => $infoAux['cupo'],
            ];
        }

        foreach ($gruposPorMateria as $codigoMateria => $grupos) {
            $gruposOferta = count($grupos);
            if ($gruposOferta < 2) {
                continue;
            }

            $totalMatriculados = 0;
            $totalCupo = 0;
            $cupoMax = 0;
            $cupos = [];
            foreach ($grupos as $g) {
                $totalMatriculados += $g['matriculados'];
                $totalCupo        += $g['cupo'];
                $cupos[]           = $g['cupo'];
                if ($g['cupo'] > $cupoMax) {
                    $cupoMax = $g['cupo'];
                }
            }

            if ($totalMatriculados <= 0) {
                continue;
            }

            // Bin packing greedy: ordenar cupos desc y "llenar" grupos
            rsort($cupos, SORT_NUMERIC);
            $restante = $totalMatriculados;
            $gruposNecesarios = 0;
            $cupoDeLosQueQuedan = 0;
            foreach ($cupos as $cupoGrupo) {
                if ($restante <= 0) {
                    break;
                }
                $restante -= $cupoGrupo;
                $cupoDeLosQueQuedan += $cupoGrupo;
                $gruposNecesarios++;
            }

            if ($restante > 0) {
                continue;
            }

            $gruposSobrantes = $gruposOferta - $gruposNecesarios;
            if ($gruposSobrantes < 1) {
                continue;
            }

            // ---- MARGEN DE HOLGURA (10%) ----
            $cupoNecesarioConHolgura = $totalMatriculados * 1.10;
            if ($cupoDeLosQueQuedan < $cupoNecesarioConHolgura) {
                continue;
            }

            // ---- % DE CUPO VACÍO ----
            $cupoLibre = $totalCupo - $totalMatriculados;
            $porcentajeCupoVacio = $totalCupo > 0
                ? (int)round(($cupoLibre / $totalCupo) * 100)
                : 0;

            $cupoSubutilizado[$codigoMateria] = [
                'grupos_oferta'         => $gruposOferta,
                'total_matriculados'    => $totalMatriculados,
                'total_cupo'            => $totalCupo,
                'cupo_max'              => $cupoMax,
                'grupos_necesarios'     => $gruposNecesarios,
                'grupos_sobrantes'      => $gruposSobrantes,
                'porcentaje_cupo_vacio' => $porcentajeCupoVacio,
            ];
        }
    }

    foreach ($alertas as &$row) {
        $row['alerta_cupo_subutilizado'] = null;
        $row['detalle_cupo_subutilizado'] = null;

        $codigo = trim((string)($row['codigo_materia'] ?? ''));
        if ($codigo === '') {
            continue;
        }
        $codigoNorm = mb_strtoupper($codigo, 'UTF-8');
        if (!isset($cupoSubutilizado[$codigoNorm])) {
            continue;
        }

        $info = $cupoSubutilizado[$codigoNorm];
        $row['alerta_cupo_subutilizado'] = 'CUPO_SUBUTILIZADO';
        $row['detalle_cupo_subutilizado'] = [
            'codigo_materia'         => $codigo,
            'grupos_oferta'          => $info['grupos_oferta'],
            'total_matriculados'     => $info['total_matriculados'],
            'total_cupo'             => $info['total_cupo'],
            'cupo_max'               => $info['cupo_max'],
            'grupos_necesarios'      => $info['grupos_necesarios'],
            'grupos_sobrantes'       => $info['grupos_sobrantes'],
            'porcentaje_cupo_vacio'  => $info['porcentaje_cupo_vacio'],
            'motivo'                 => 'La matrícula total de esta materia (' . $info['total_matriculados']
                                      . ') ocupa solo el ' . (100 - $info['porcentaje_cupo_vacio'])
                                      . '% del cupo ofertado (' . $info['total_cupo']
                                      . '). La matrícula cabe en ' . $info['grupos_necesarios']
                                      . ' de los ' . $info['grupos_oferta']
                                      . ' grupos de Oferta que cruzaron con Labor. '
                                      . 'Posible reducción de ' . $info['grupos_sobrantes'] . ' grupo(s).',
        ];
    }
    unset($row);
    /* -----------------------------------------------------------------
       Búsqueda final tolerante sobre la colección combinada
       ----------------------------------------------------------------- */
    if ($busqueda !== '') {
        $alertas = filtrarPorBusqueda($alertas, $busqueda);
    }

    /* -----------------------------------------------------------------
       Filtro de estado final
       ----------------------------------------------------------------- */
    if ($estado !== '') {
        $alertas = array_values(array_filter($alertas, function ($row) use ($estado) {
            return $row['estado_alerta'] === $estado;
        }));
    }

    /* -----------------------------------------------------------------
       KPIs
       ----------------------------------------------------------------- */
    $kpis = [
        'total_registros' => count($alertas),
        'ok' => 0,
        'oferta_inexistente' => 0,
        'falta_grupo_en_oferta' => 0,
        'programa_diferente' => 0,
        'prestacion_servicio' => 0,
        'duplicidad_grupo_pe' => 0,
        'labor_inexistente' => 0,
        'revisar_codigo_materia' => 0,
        'duplicado_exacto' => 0,
        'cero_matriculados' => 0,
        'cupo_subutilizado' => 0, 
       
    ];

    foreach ($alertas as $row) {
        switch ($row['estado_alerta']) {
            case 'OK':
                $kpis['ok']++;
                break;
            case 'OFERTA_INEXISTENTE':
                $kpis['oferta_inexistente']++;
                break;
            case 'FALTA_GRUPO_EN_OFERTA':
                $kpis['falta_grupo_en_oferta']++;
                break;
            case 'REVISAR_PROGRAMA_DIFERENTE':
                $kpis['programa_diferente']++;
                break;
            case 'LABOR_INEXISTENTE':
                $kpis['labor_inexistente']++;
                break;
            case 'REVISAR_CODIGO_MATERIA_DIFERENTE':
                $kpis['revisar_codigo_materia']++;
                break;
            case 'CERO_MATRICULADOS':
                $kpis['cero_matriculados']++;
                break;

        }
        if ((int)($row['es_prestacion_servicio'] ?? 0) === 1) {
            $kpis['prestacion_servicio']++;
        }
        if (($row['alerta_labor'] ?? null) === 'DUPLICIDAD_GRUPO_EXCESO_PE') {
            $kpis['duplicidad_grupo_pe']++;
        }
        if (($row['alerta_duplicado_exacto'] ?? null) === 'LABOR_DUPLICADO_EXACTO') {
            $kpis['duplicado_exacto']++;
        }
        // ---- NUEVO: conteo CUPO_SUBUTILIZADO ----
        if (($row['alerta_cupo_subutilizado'] ?? null) === 'CUPO_SUBUTILIZADO') {
            $kpis['cupo_subutilizado']++;
        }

    }

    return ['alertas' => $alertas, 'kpis' => $kpis];
}

try {
    switch ($action) {

        /* =====================================================================
           1. OBTENER FILTROS Y CATÁLOGOS
           ===================================================================== */
        case 'filtros':
            $stmtP = $pdo->query("SELECT DISTINCT periodo FROM periodo_catalogo ORDER BY orden_cronologico DESC");
            $periodos = $stmtP->fetchAll(PDO::FETCH_COLUMN);

            $stmtActual = $pdo->query("SELECT periodo FROM periodo_catalogo ORDER BY orden_cronologico DESC LIMIT 1");
            $periodoActual = $stmtActual->fetchColumn() ?: null;

            // Facultades y departamentos desde labor (fuente confiable)
            $facultades = $pdo->query("SELECT DISTINCT facultad FROM labor WHERE facultad IS NOT NULL AND TRIM(facultad) != '' ORDER BY facultad")->fetchAll(PDO::FETCH_COLUMN);
            $departamentos = $pdo->query("SELECT DISTINCT departamento FROM labor WHERE departamento IS NOT NULL AND TRIM(departamento) != '' ORDER BY departamento")->fetchAll(PDO::FETCH_COLUMN);

            // Programas desde labor
            $programas = $pdo->query("SELECT DISTINCT programa FROM labor WHERE programa IS NOT NULL AND TRIM(programa) != '' ORDER BY programa")->fetchAll(PDO::FETCH_COLUMN);

            // --- Relación departamento -> facultad (desde labor, que es confiable) ---
            $stmtRelacion = $pdo->query("SELECT DISTINCT departamento, facultad FROM labor WHERE departamento IS NOT NULL AND TRIM(departamento) != '' AND facultad IS NOT NULL AND TRIM(facultad) != '' ORDER BY departamento");
            $relacionDepartamentoFacultad = [];
            while ($row = $stmtRelacion->fetch(PDO::FETCH_ASSOC)) {
                $relacionDepartamentoFacultad[$row['departamento']] = $row['facultad'];
            }

            // --- Facultad -> lista de departamentos ---
            $departamentosPorFacultad = [];
            foreach ($relacionDepartamentoFacultad as $depto => $facultad) {
                if (!isset($departamentosPorFacultad[$facultad])) {
                    $departamentosPorFacultad[$facultad] = [];
                }
                $departamentosPorFacultad[$facultad][] = $depto;
            }
            // Ordenar cada lista de departamentos
            foreach ($departamentosPorFacultad as $facultad => &$deptos) {
                sort($deptos);
            }
            unset($deptos);

            $vinculaciones = $pdo->query("SELECT DISTINCT tipo_contrato FROM labor WHERE tipo_contrato IS NOT NULL AND TRIM(tipo_contrato) != '' ORDER BY tipo_contrato")->fetchAll(PDO::FETCH_COLUMN);

            echo json_encode([
                'success' => true,
                'data' => [
                    'periodo_actual' => $periodoActual,
                    'periodos' => $periodos,
                    'facultades' => $facultades,
                    'departamentos' => $departamentos,
                    'departamentos_por_facultad' => $departamentosPorFacultad,
                    'relacion_departamento_facultad' => $relacionDepartamentoFacultad,
                    'programas' => $programas,
                    'vinculaciones' => $vinculaciones,
                ],
            ]);
            break;

        case 'alertas':
            $filtros = [
                'periodo' => trim($_GET['periodo'] ?? ''),
                'facultad' => trim($_GET['facultad'] ?? ''),
                'departamento' => trim($_GET['departamento'] ?? ''),
                'programa' => trim($_GET['programa'] ?? ''),
                'tipo_contrato' => trim($_GET['tipo_contrato'] ?? ''),
                'estado' => trim($_GET['estado'] ?? ''),
                'search' => trim($_GET['search'] ?? ''),
            ];
            if (!$filtros['periodo']) {
                echo json_encode(['success' => false, 'error' => 'Se requiere el periodo.']);
                exit;
            }
            $resultado = construirAuditoria($pdo, $filtros);
            $alertas = $resultado['alertas'];

            // --- Reconciliación automática de observaciones + badge por fila -----
            $mapaObservaciones = reconciliarObservaciones($pdo, $filtros['periodo'], $alertas);
            foreach ($alertas as &$rowAlerta) {
                $rowAlerta['id_observacion_asociada'] = null;
                $rowAlerta['estado_observacion_asociada'] = null;
                if (empty($rowAlerta['identificacion']) || empty($rowAlerta['codigo_materia']) || empty($rowAlerta['grupo'])) {
                    continue;
                }
                $claveFila = claveObsNormalizada($rowAlerta['identificacion'], $rowAlerta['codigo_materia'], $rowAlerta['grupo']);
                if (isset($mapaObservaciones[$claveFila])) {
                    $rowAlerta['id_observacion_asociada'] = $mapaObservaciones[$claveFila]['id_observacion'];
                    $rowAlerta['estado_observacion_asociada'] = $mapaObservaciones[$claveFila]['estado'];
                }
            }
            unset($rowAlerta);

            echo json_encode([
                'success' => true,
                'kpis' => $resultado['kpis'],
                'data' => $alertas,
            ], JSON_UNESCAPED_UNICODE);
            break;

        /* ============================================================================
           BLOQUE 2.B — NUEVO case. Guarda una observación nueva.
           ========================================================================== */
        case 'guardar_observacion':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                echo json_encode(['success' => false, 'error' => 'Método no permitido. Use POST.']);
                exit;
            }
            $body = json_decode(file_get_contents('php://input'), true);
            if (!is_array($body)) {
                echo json_encode(['success' => false, 'error' => 'JSON inválido en el cuerpo de la petición.']);
                exit;
            }

            $periodo = trim((string)($body['periodo'] ?? ''));
            $titulo = trim((string)($body['titulo'] ?? ''));
            $descripcion = trim((string)($body['descripcion'] ?? ''));
            $filas = $body['filas'] ?? [];

            if ($periodo === '' || $titulo === '' || !is_array($filas) || count($filas) === 0) {
                echo json_encode(['success' => false, 'error' => 'Se requieren periodo, titulo y al menos una fila seleccionada.']);
                exit;
            }

            try {
                $pdo->beginTransaction();

                $stmtObs = $pdo->prepare("INSERT INTO observaciones (periodo, titulo, descripcion, estado)
                                           VALUES (:periodo, :titulo, :descripcion, 'ABIERTA')");
                $stmtObs->execute([
                    'periodo' => $periodo,
                    'titulo' => $titulo,
                    'descripcion' => $descripcion !== '' ? $descripcion : null,
                ]);
                $idObservacion = (int)$pdo->lastInsertId();

                $stmtFila = $pdo->prepare("INSERT INTO observaciones_filas
                                            (id_observacion, periodo, identificacion, codigo_materia, grupo, estado_alerta_original)
                                            VALUES (:id_observacion, :periodo, :identificacion, :codigo_materia, :grupo, :estado_alerta_original)
                                            ON DUPLICATE KEY UPDATE
                                                id_observacion = VALUES(id_observacion),
                                                estado_alerta_original = VALUES(estado_alerta_original)");

                $filasInsertadas = 0;
                foreach ($filas as $f) {
                    $identificacion = trim((string)($f['identificacion'] ?? ''));
                    $codigoMateria = trim((string)($f['codigo_materia'] ?? ''));
                    $grupo = trim((string)($f['grupo'] ?? ''));
                    $estadoOriginal = trim((string)($f['estado_alerta_original'] ?? ''));

                    if ($identificacion === '' || $codigoMateria === '' || $grupo === '') {
                        continue;
                    }

                    $stmtFila->execute([
                        'id_observacion' => $idObservacion,
                        'periodo' => $periodo,
                        'identificacion' => $identificacion,
                        'codigo_materia' => $codigoMateria,
                        'grupo' => $grupo,
                        'estado_alerta_original' => $estadoOriginal !== '' ? $estadoOriginal : null,
                    ]);
                    $filasInsertadas++;
                }

                if ($filasInsertadas === 0) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'error' => 'Ninguna de las filas enviadas tenía datos válidos (identificación/código/grupo).']);
                    exit;
                }

                $pdo->commit();
                echo json_encode([
                    'success' => true,
                    'id_observacion' => $idObservacion,
                    'filas_guardadas' => $filasInsertadas,
                ]);
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                echo json_encode(['success' => false, 'error' => 'Error al guardar la observación: ' . $e->getMessage()]);
            }
            break;

        /* ============================================================================
           BLOQUE 2.C — NUEVO case. Devuelve el detalle completo de una observación.
           ========================================================================== */
        case 'obtener_observacion':
            $idObservacion = (int)($_GET['id'] ?? 0);
            if ($idObservacion <= 0) {
                echo json_encode(['success' => false, 'error' => 'Se requiere el id de la observación.']);
                exit;
            }

            $stmtObs = $pdo->prepare("SELECT * FROM observaciones WHERE id = :id");
            $stmtObs->execute(['id' => $idObservacion]);
            $observacion = $stmtObs->fetch(PDO::FETCH_ASSOC);

            if (!$observacion) {
                echo json_encode(['success' => false, 'error' => 'Observación no encontrada.']);
                exit;
            }

            $stmtFilas = $pdo->prepare("SELECT * FROM observaciones_filas WHERE id_observacion = :id ORDER BY id");
            $stmtFilas->execute(['id' => $idObservacion]);
            $filas = $stmtFilas->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'observacion' => $observacion,
                'filas' => $filas,
            ], JSON_UNESCAPED_UNICODE);
            break;

        /* ============================================================================
           BLOQUE 2.D — NUEVO case. Lista las observaciones de un periodo.
           ========================================================================== */
        case 'listar_observaciones':
            $periodo = trim($_GET['periodo'] ?? '');
            if (!$periodo) {
                echo json_encode(['success' => false, 'error' => 'Se requiere el periodo.']);
                exit;
            }
            $stmt = $pdo->prepare("SELECT o.*, COUNT(f.id) AS total_filas
                                    FROM observaciones o
                                    LEFT JOIN observaciones_filas f ON f.id_observacion = o.id
                                    WHERE o.periodo = :periodo
                                    GROUP BY o.id
                                    ORDER BY o.fecha_creacion DESC");
            $stmt->execute(['periodo' => $periodo]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
            break;

        /* =====================================================================
           3. RADIOGRAFÍA / BÚSQUEDA INDIVIDUAL DE DOCENTE
           ===================================================================== */
        case 'docente_radiografia':
            $identificacion = $_GET['identificacion'] ?? '';
            if (empty($identificacion)) {
                echo json_encode(['success' => false, 'error' => 'Se requiere la identificación del docente.']);
                exit;
            }

            $stmtLab = $pdo->prepare("SELECT * FROM labor WHERE identificacion = ? ORDER BY periodo DESC, materia");
            $stmtLab->execute([$identificacion]);
            $historicoLabor = $stmtLab->fetchAll(PDO::FETCH_ASSOC);

            $stmtAlertas = $pdo->prepare("
                SELECT l.periodo, l.programa AS programa_labor, l.materia AS materia_labor, l.codigo_materia, l.grupo, l.horas_teoricas,
                       CASE
                           WHEN MAX(CASE WHEN TRIM(LOWER(l.programa)) = TRIM(LOWER(o.programa)) THEN 1 ELSE 0 END) = 1 THEN 'OK'
                           WHEN COUNT(o.id) > 0 THEN 'REVISAR_PROGRAMA_DIFERENTE'
                           ELSE 'OFERTA_INEXISTENTE'
                       END AS estado_alerta
                FROM labor l
                LEFT JOIN oferta o ON l.periodo = o.periodo AND l.identificacion = o.identificacion AND l.codigo_materia = o.codigo_materia
                WHERE l.identificacion = ?
                GROUP BY l.id, l.periodo, l.programa, l.materia, l.codigo_materia, l.grupo, l.horas_teoricas
                ORDER BY l.periodo DESC, l.materia
            ");
            $stmtAlertas->execute([$identificacion]);
            $alertasHistorico = $stmtAlertas->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'docente' => $historicoLabor[0]['docente'] ?? 'No encontrado',
                'identificacion' => $identificacion,
                'historico_labor' => $historicoLabor,
                'alertas_historico' => $alertasHistorico,
            ]);
            break;

        /* =====================================================================
           4. DOCENTE POR PERIODO (modal unificado) - SIN CAMBIOS
           ===================================================================== */
        case 'docente_periodo':
            $identificacion = trim($_GET['identificacion'] ?? '');
            $periodo = trim($_GET['periodo'] ?? '');
            if (!$identificacion || !$periodo) {
                echo json_encode(['success' => false, 'error' => 'Se requieren los parámetros identificacion y periodo.']);
                exit;
            }

            // Consulta sin alias para programa y materia (nombres originales de tabla)
            $stmtLab = $pdo->prepare("
                SELECT id, periodo, facultad, departamento, identificacion, docente, tipo_contrato,
                       programa, materia, codigo_materia, grupo, horas_teoricas, pe
                FROM labor
                WHERE identificacion = :id AND periodo = :periodo
                ORDER BY codigo_materia, grupo
            ");
            $stmtLab->execute(['id' => $identificacion, 'periodo' => $periodo]);
            $labor = $stmtLab->fetchAll(PDO::FETCH_ASSOC);

            $stmtOfe = $pdo->prepare("
                SELECT id, periodo, identificacion, docente, programa, materia, codigo_materia, grupo, matriculados, cupo
                FROM oferta
                WHERE identificacion = :id AND periodo = :periodo
                ORDER BY codigo_materia, grupo
            ");
            $stmtOfe->execute(['id' => $identificacion, 'periodo' => $periodo]);
            $oferta = $stmtOfe->fetchAll(PDO::FETCH_ASSOC);

            // Índice de ofertas por código normalizado
            $ofertaPorCodigo = [];
            foreach ($oferta as $idx => $o) {
                $cod = normalizarCodigoMateria($o['codigo_materia']);
                if ($cod !== '') {
                    $ofertaPorCodigo[$cod][] = $idx;
                }
            }
            $ofertaUsada = array_fill(0, count($oferta), false);

            $cruces = [];
            $resumen = [
                'total_labor' => count($labor),
                'total_oferta' => count($oferta),
                'ok' => 0,
                'programa_diferente' => 0,
                'oferta_inexistente' => 0,
                'oferta_sin_labor' => 0,
                'matriculados_cero' => 0,
                'revisar_codigo_materia' => 0,
            ];

            // PASADA 1: Asignaciones por código exacto
            $laborPendiente = [];
            foreach ($labor as $l) {
                $codLabor = normalizarCodigoMateria($l['codigo_materia']);
                $candidatosIdx = $codLabor !== '' ? ($ofertaPorCodigo[$codLabor] ?? []) : [];
                $libresIdx = array_values(array_filter($candidatosIdx, function ($idx) use ($ofertaUsada) {
                    return !$ofertaUsada[$idx];
                }));

                if (!empty($libresIdx)) {
                    $mejorMateriaExactaIdx = null;
                    $mejorProgramaExactoIdx = null;
                    $mejorSimilitudMateriaIdx = null;
                    $mejorSimilitudProgramaIdx = null;
                    $mejorSimilitudMateria = -1;
                    $mejorSimilitudPrograma = -1;
                    $comparacionesPorIdx = [];
                    $materiaLaborNormalizada = normalizarTextoComparacion($l['materia'] ?? '');
                    $programaLaborNormalizado = normalizarTextoComparacion($l['programa'] ?? '');

                    foreach ($libresIdx as $idx) {
                        $ofertaCandidata = $oferta[$idx];
                        $materiaOfertaNormalizada = normalizarTextoComparacion($ofertaCandidata['materia'] ?? '');
                        $programaOfertaNormalizado = normalizarTextoComparacion($ofertaCandidata['programa'] ?? '');
                        $comparacionPrograma = compararProgramas($l['programa'] ?? '', $ofertaCandidata['programa'] ?? '');
                        $similitudMateria = similitudTexto($l['materia'] ?? '', $ofertaCandidata['materia'] ?? '');
                        $comparacionesPorIdx[$idx] = [
                            'programa' => $comparacionPrograma,
                            'similitud_materia' => $similitudMateria,
                        ];
                        if ($materiaLaborNormalizada !== '' && $materiaLaborNormalizada === $materiaOfertaNormalizada && $mejorMateriaExactaIdx === null) {
                            $mejorMateriaExactaIdx = $idx;
                        }
                        if ($programaLaborNormalizado !== '' && $programaLaborNormalizado === $programaOfertaNormalizado && $mejorProgramaExactoIdx === null) {
                            $mejorProgramaExactoIdx = $idx;
                        }
                        if ($similitudMateria > $mejorSimilitudMateria) {
                            $mejorSimilitudMateria = $similitudMateria;
                            $mejorSimilitudMateriaIdx = $idx;
                        }
                        $similitudPrograma = $comparacionPrograma['similitud'] ?? -1;
                        if ($similitudPrograma > $mejorSimilitudPrograma) {
                            $mejorSimilitudPrograma = $similitudPrograma;
                            $mejorSimilitudProgramaIdx = $idx;
                        }
                    }

                    $elegidoIdx =
                        $mejorMateriaExactaIdx ??
                        $mejorProgramaExactoIdx ??
                        $mejorSimilitudMateriaIdx ??
                        $mejorSimilitudProgramaIdx;

                    if ($elegidoIdx !== null) {
                        $ofertaUsada[$elegidoIdx] = true;
                        $o = $oferta[$elegidoIdx];
                        $comparacion = $comparacionesPorIdx[$elegidoIdx]['programa'];
                        $estado = $comparacion['tipo'] === 'EXACTA' ? 'OK' : 'REVISAR_PROGRAMA_DIFERENTE';
                        if ($estado === 'OK') $resumen['ok']++; else $resumen['programa_diferente']++;
                        $cruces[] = [
                            'labor' => $l,
                            'oferta' => $o,
                            'estado' => $estado,
                            'coincidencia_programa' => $comparacion['tipo'],
                            'similitud_programa' => $comparacion['similitud'],
                            'motivo_programa' => $comparacion['motivo'],
                            'detalle_codigo_posible_error' => null,
                        ];
                        continue;
                    }
                }
                $laborPendiente[] = $l;
            }

            // PASADA 2: Procesar filas pendientes con diagnóstico de código diferente
            foreach ($laborPendiente as $l) {
                $ofertaLibreTotal = [];
                foreach ($oferta as $idx => $o) {
                    if (!$ofertaUsada[$idx]) {
                        $ofertaLibreTotal[] = ['__idx' => $idx] + $o;
                    }
                }
                $filaLaborTmp = [
                    'codigo_materia' => $l['codigo_materia'],
                    'programa_labor' => $l['programa'],
                    'materia_labor' => $l['materia'],
                ];
                $diagnostico = detectarPosibleCodigoErroneo($filaLaborTmp, $ofertaLibreTotal);
                $comparacion = compararProgramas($l['programa'], null);
                if ($diagnostico !== null) {
                    $estado = 'REVISAR_CODIGO_MATERIA_DIFERENTE';
                    $resumen['revisar_codigo_materia']++;
                    foreach ($ofertaLibreTotal as $cand) {
                        if ((int)$cand['id'] === (int)$diagnostico['oferta_id']) {
                            $ofertaUsada[$cand['__idx']] = true;
                            $o = $oferta[$cand['__idx']];
                            break;
                        }
                    }
                    if (!isset($o)) $o = null;
                } else {
                    $estado = 'OFERTA_INEXISTENTE';
                    $resumen['oferta_inexistente']++;
                    $o = null;
                }
                $cruces[] = [
                    'labor' => $l,
                    'oferta' => $o,
                    'estado' => $estado,
                    'coincidencia_programa' => $comparacion['tipo'] ?? 'NO_APLICA',
                    'similitud_programa' => $comparacion['similitud'] ?? null,
                    'motivo_programa' => $comparacion['motivo'] ?? 'Sin comparación',
                    'detalle_codigo_posible_error' => $diagnostico,
                ];
            }

            // Oferta sin Labor
            $ofertaSinLabor = [];
            foreach ($oferta as $idx => $o) {
                if (!$ofertaUsada[$idx]) $ofertaSinLabor[] = $o;
            }
            $resumen['oferta_sin_labor'] = count($ofertaSinLabor);
            foreach ($oferta as $o) {
                if ((int)($o['matriculados'] ?? -1) === 0) $resumen['matriculados_cero']++;
            }

            echo json_encode([
                'success' => true,
                'docente' => $labor[0]['docente'] ?? ($oferta[0]['docente'] ?? 'No encontrado'),
                'identificacion' => $identificacion,
                'periodo' => $periodo,
                'tipo_contrato' => $labor[0]['tipo_contrato'] ?? null,
                'facultad' => $labor[0]['facultad'] ?? null,
                'departamento' => $labor[0]['departamento'] ?? null,
                'resumen' => $resumen,
                'cruces' => $cruces,
                'oferta_sin_labor' => $ofertaSinLabor,
            ]);
            break;

        /* =====================================================================
           5. HISTÓRICO DE MATERIA
           ===================================================================== */
        case 'materia_historico':
            $codigo = trim($_GET['codigo_materia'] ?? '');
            if (!$codigo) {
                echo json_encode(['success' => false, 'error' => 'Se requiere el parámetro codigo_materia.']);
                exit;
            }

            $ordenCron = $pdo->query("SELECT periodo, orden_cronologico FROM periodo_catalogo")->fetchAll(PDO::FETCH_KEY_PAIR);

            $stmtL = $pdo->prepare("
                SELECT periodo, MAX(materia) AS materia, COUNT(*) AS grupos_labor,
                       COUNT(DISTINCT identificacion) AS docentes_labor, SUM(horas_teoricas) AS horas_labor,
                       GROUP_CONCAT(DISTINCT programa SEPARATOR '; ') AS programas_labor
                FROM labor WHERE TRIM(codigo_materia) = TRIM(:codigo) GROUP BY periodo
            ");
            $stmtL->execute(['codigo' => $codigo]);
            $laborPorPeriodo = [];
            foreach ($stmtL->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $laborPorPeriodo[$row['periodo']] = $row;
            }

            $stmtO = $pdo->prepare("
                SELECT periodo, COUNT(*) AS grupos_oferta, COALESCE(SUM(matriculados), 0) AS matriculados_oferta,
                       COALESCE(SUM(cupo), 0) AS cupos_oferta, GROUP_CONCAT(DISTINCT programa SEPARATOR '; ') AS programas_oferta
                FROM oferta WHERE TRIM(codigo_materia) = TRIM(:codigo) GROUP BY periodo
            ");
            $stmtO->execute(['codigo' => $codigo]);
            $ofertaPorPeriodo = [];
            foreach ($stmtO->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $ofertaPorPeriodo[$row['periodo']] = $row;
            }

            $stmtDetalleLabor = $pdo->prepare("
                SELECT periodo, facultad, departamento, programa, identificacion, docente, tipo_contrato,
                       materia, codigo_materia, grupo, horas_teoricas, pe
                FROM labor WHERE TRIM(codigo_materia) = TRIM(:codigo) ORDER BY periodo, programa, docente, grupo
            ");
            $stmtDetalleLabor->execute(['codigo' => $codigo]);
            $detalleLabor = [];
            foreach ($stmtDetalleLabor->fetchAll(PDO::FETCH_ASSOC) as $fila) {
                $periodoFila = (string)$fila['periodo'];
                if (!isset($detalleLabor[$periodoFila])) {
                    $detalleLabor[$periodoFila] = [];
                }
                $detalleLabor[$periodoFila][] = [
                    'periodo' => $fila['periodo'], 'facultad' => $fila['facultad'], 'departamento' => $fila['departamento'],
                    'programa' => $fila['programa'], 'identificacion' => $fila['identificacion'], 'docente' => $fila['docente'],
                    'tipo_contrato' => $fila['tipo_contrato'], 'materia' => $fila['materia'], 'codigo_materia' => $fila['codigo_materia'],
                    'grupo' => $fila['grupo'], 'horas_teoricas' => (float)($fila['horas_teoricas'] ?? 0), 'pe' => (float)($fila['pe'] ?? 0),
                ];
            }

            $stmtDetalleOferta = $pdo->prepare("
                SELECT periodo, identificacion, docente, programa, materia, codigo_materia, grupo, matriculados, cupo
                FROM oferta WHERE TRIM(codigo_materia) = TRIM(:codigo) ORDER BY periodo, programa, docente, grupo
            ");
            $stmtDetalleOferta->execute(['codigo' => $codigo]);
            $detalleOferta = [];
            foreach ($stmtDetalleOferta->fetchAll(PDO::FETCH_ASSOC) as $fila) {
                $periodoFila = (string)$fila['periodo'];
                if (!isset($detalleOferta[$periodoFila])) {
                    $detalleOferta[$periodoFila] = [];
                }
                $detalleOferta[$periodoFila][] = [
                    'periodo' => $fila['periodo'], 'identificacion' => $fila['identificacion'], 'docente' => $fila['docente'],
                    'programa' => $fila['programa'], 'materia' => $fila['materia'], 'codigo_materia' => $fila['codigo_materia'],
                    'grupo' => $fila['grupo'], 'matriculados' => (int)($fila['matriculados'] ?? 0),
                    'cupo' => $fila['cupo'] !== null ? (int)$fila['cupo'] : null,
                ];
            }

            $periodos = array_unique(array_merge(
                array_keys($laborPorPeriodo),
                array_keys($ofertaPorPeriodo),
                array_keys($detalleLabor),
                array_keys($detalleOferta)
            ));
            usort($periodos, function ($a, $b) use ($ordenCron) {
                $ordenA = $ordenCron[$a] ?? PHP_INT_MAX;
                $ordenB = $ordenCron[$b] ?? PHP_INT_MAX;
                if ((int)$ordenA === (int)$ordenB) {
                    return strcmp((string)$a, (string)$b);
                }
                return (int)$ordenA - (int)$ordenB;
            });

            $historia = [];
            $nombreMateria = null;
            foreach ($periodos as $p) {
                $laborPeriodo = $laborPorPeriodo[$p] ?? null;
                $ofertaPeriodo = $ofertaPorPeriodo[$p] ?? null;

                if ($laborPeriodo && !$nombreMateria) {
                    $nombreMateria = $laborPeriodo['materia'];
                }
                if (!$nombreMateria && !empty($detalleOferta[$p])) {
                    $nombreMateria = $detalleOferta[$p][0]['materia'] ?? null;
                }

                $historia[] = [
                    'periodo' => $p,
                    'grupos_labor' => (int)($laborPeriodo['grupos_labor'] ?? 0),
                    'docentes_labor' => (int)($laborPeriodo['docentes_labor'] ?? 0),
                    'horas_labor' => (float)($laborPeriodo['horas_labor'] ?? 0),
                    'programas_labor' => $laborPeriodo['programas_labor'] ?? '',
                    'grupos_oferta' => (int)($ofertaPeriodo['grupos_oferta'] ?? 0),
                    'matriculados_oferta' => (int)($ofertaPeriodo['matriculados_oferta'] ?? 0),
                    'cupos_oferta' => (int)($ofertaPeriodo['cupos_oferta'] ?? 0),
                    'programas_oferta' => $ofertaPeriodo['programas_oferta'] ?? '',
                    'detalle_labor' => $detalleLabor[$p] ?? [],
                    'detalle_oferta' => $detalleOferta[$p] ?? [],
                ];
            }

            echo json_encode([
                'success' => true,
                'codigo_materia' => $codigo,
                'materia' => $nombreMateria ?? 'Sin nombre en Labor',
                'total_periodos' => count($historia),
                'historia' => $historia,
            ], JSON_UNESCAPED_UNICODE);
            break;

        /* =====================================================================
           6. labor_materia_grupo
           ===================================================================== */
        case 'labor_materia_grupo':
            $periodo = trim($_GET['periodo'] ?? '');
            $codigoMateria = trim($_GET['codigo_materia'] ?? '');
            $programa = trim($_GET['programa'] ?? '');
            $grupo = trim($_GET['grupo'] ?? '');

            if (!$periodo || !$codigoMateria || !$programa || !$grupo) {
                echo json_encode(['success' => false, 'error' => 'Se requieren periodo, codigo_materia, programa y grupo.']);
                exit;
            }

            $sql = "SELECT id, periodo, identificacion, docente, programa, materia, codigo_materia, grupo, horas_teoricas, pe
                    FROM labor
                    WHERE periodo = :periodo AND TRIM(codigo_materia) = :codigo_materia AND TRIM(grupo) = :grupo
                          AND TRIM(LOWER(programa)) = TRIM(LOWER(:programa))
                    ORDER BY docente, codigo_materia, grupo";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['periodo' => $periodo, 'codigo_materia' => $codigoMateria, 'grupo' => $grupo, 'programa' => $programa]);
            $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($registros)) {
                echo json_encode(['success' => false, 'error' => 'No se encontraron registros para la combinación especificada.']);
                exit;
            }

            $cantidad = count($registros);
            $docentesInvolucrados = count(array_unique(array_column($registros, 'identificacion')));
            $horasTotales = array_sum(array_column($registros, 'horas_teoricas'));
            $peUnico = max(array_column($registros, 'pe'));
            $exceso = $horasTotales - $peUnico;
            $hayAlerta = ($cantidad >= 2) && ($horasTotales > $peUnico);
            $materia = $registros[0]['materia'] ?? '';

            echo json_encode([
                'success' => true,
                'periodo' => $periodo,
                'codigo_materia' => $codigoMateria,
                'programa' => $programa,
                'grupo' => $grupo,
                'materia' => $materia,
                'cantidad_registros' => $cantidad,
                'docentes_involucrados' => $docentesInvolucrados,
                'horas_totales' => $horasTotales,
                'pe_unico' => $peUnico,
                'exceso_horas' => $exceso,
                'hay_alerta' => $hayAlerta,
                'registros' => $registros,
            ]);
            break;

        /* =====================================================================
           7. EXPORTAR EXCEL — usa construirAuditoria()
           ===================================================================== */
        case 'exportar_excel':
            $filtros = [
                'periodo' => trim($_GET['periodo'] ?? ''),
                'facultad' => trim($_GET['facultad'] ?? ''),
                'departamento' => trim($_GET['departamento'] ?? ''),
                'programa' => trim($_GET['programa'] ?? ''),
                'tipo_contrato' => trim($_GET['tipo_contrato'] ?? ''),
                'estado' => trim($_GET['estado'] ?? ''),
                'search' => trim($_GET['search'] ?? ''),
            ];

            if (!$filtros['periodo']) {
                echo json_encode(['success' => false, 'error' => 'Se requiere el periodo.']);
                exit;
            }

            $resultado = construirAuditoria($pdo, $filtros);
            $alertas = $resultado['alertas'];

            $nombreArchivo = 'reporte_alertas_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $filtros['periodo']) . '.xls';
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
            header('Cache-Control: max-age=0');

            $ESTADO_META = [
                'OK' => ['OK', 'dcfce7', '15803d'],
                'REVISAR_PROGRAMA_DIFERENTE' => ['PROGRAMA DIFERENTE', 'fef3c7', 'b45309'],
                'OFERTA_INEXISTENTE' => ['OFERTA INEXISTENTE', 'fee2e2', 'b91c1c'],
                'FALTA_GRUPO_EN_OFERTA' => ['GRUPO SIN OFERTA', 'fed7aa', 'c2410c'],
                'LABOR_INEXISTENTE' => ['NO EXISTE EN LABOR', 'fce7f3', 'be185d'],
                'REVISAR_CODIGO_MATERIA_DIFERENTE' => ['REVISAR CÓDIGO MATERIA', 'e0e7ff', '4338ca'],
                'CERO_MATRICULADOS' => ['CERO MATRICULADOS', 'ffedd5', 'c2410c'],
            ];
            $HIST_META = [
                'SALTO_FUERTE' => ['SALTO FUERTE', 'fee2e2', 'b91c1c'],
                'SALTO_JUSTIFICADO' => ['SALTO JUSTIFICADO', 'd1fae5', '047857'],
                'SIN_HISTORIAL' => ['SIN HISTORIAL', 'e0f2fe', '075985'],
            ];
            $LABOR_META = [
                'DUPLICIDAD_GRUPO_EXCESO_PE' => ['DUPLICIDAD DE GRUPO', 'fdf2f8', 'be185d'],
            ];
            $COINCIDENCIA_META = [
                'EXACTA' => ['EXACTA', 'dcfce7', '15803d'],
                'ALTA' => ['ALTA', 'fef9c3', '92400e'],
                'MEDIA' => ['MEDIA', 'fef3c7', 'b45309'],
                'BAJA' => ['BAJA', 'fee2e2', 'b91c1c'],
                'NO_APLICA' => ['N/A', 'f1f5f9', '64748b'],
            ];

            echo "\xEF\xBB\xBF";
            echo "<html xmlns:x=\"urn:schemas-microsoft-com:office:excel\"><head><meta charset=\"UTF-8\"></head><body>";
            echo '<table border="1" cellpadding="4" cellspacing="0" style="border-collapse:collapse;font-family:Arial;font-size:11px;">';
           echo '<tr><td colspan="31" style="background:#1e3a8a;color:#fff;font-size:14px;font-weight:bold;">' . 'REPORTE DE ALERTAS - AUDITORÍA LABOR VS OFERTA' . '</td></tr>';
            echo '<tr><td colspan="31" style="background:#eff6ff;">'
                . '<b>Periodo:</b> ' . htmlspecialchars($filtros['periodo']) . '&nbsp;&nbsp;'
                . '<b>Facultad:</b> ' . htmlspecialchars($filtros['facultad'] ?: 'Todas') . '&nbsp;&nbsp;'
                . '<b>Departamento:</b> ' . htmlspecialchars($filtros['departamento'] ?: 'Todos') . '&nbsp;&nbsp;'
                . '<b>Programa:</b> ' . htmlspecialchars($filtros['programa'] ?: 'Todos') . '&nbsp;&nbsp;'
                . '<b>Estado:</b> ' . htmlspecialchars($filtros['estado'] ?: 'Todos') . '&nbsp;&nbsp;'
                . '<b>Generado:</b> ' . date('Y-m-d H:i') . '&nbsp;&nbsp;'
                . '<b>Registros:</b> ' . count($alertas)
                . '</td></tr>';

            $headers = [
                'Fuente principal',
                'Periodo',
                'Facultad',
                'Departamento',
                'Identificación',
                'Docente',
                'Tipo Contrato',
                'Código Labor',
                'Materia Labor',
                'Grupo Labor',                // ← movido aquí (era 'Grupo' al final)
                'Código Oferta',
                'Materia Oferta',
                'Grupo Oferta',               // ← NUEVO
                'Programa Labor',
                'Programa Oferta',
                'Coincidencia programa',
                'Similitud programa',
                'Motivo programa',
                'Horas T.',
                'PE',
                'Matriculados',
                'Cupo',
                'Alerta Labor vs Oferta',
                'Alerta Labor',
                'Detalle Alerta Labor',
                'Alerta Histórica',
                'Detalle del Salto',
                'Base Comparación',
                'Diagnóstico Código Materia',
                'Alerta Cupo Subutilizado',   // ← NUEVO
                'Detalle Cupo Subutilizado',  // ← NUEVO
            ];
            echo '<tr>';
            foreach ($headers as $h) {
                echo '<td style="background:#1f2937;color:#fff;font-weight:bold;">' . $h . '</td>';
            }
            echo '</tr>';

            foreach ($alertas as $r) {
                [$txtEstado, $bgEstado, $fgEstado] = $ESTADO_META[$r['estado_alerta']] ?? [$r['estado_alerta'], 'fff', '000'];

                $txtHist = ''; $bgHist = 'ffffff'; $fgHist = '6b7280'; $detalle = ''; $baseTxt = '';
                if (!empty($r['alerta_historica']) && !empty($r['salto_detalle'])) {
                    $d = $r['salto_detalle'];
                    $salto = (int)$d['grupos_act'] - (int)$d['grupos_prev'];
                    [$txtHist, $bgHist, $fgHist] = $HIST_META[$r['alerta_historica']] ?? [$r['alerta_historica'], 'ffffff', '6b7280'];
                    $detalle = "Grupos: {$d['grupos_prev']} -> {$d['grupos_act']} (+{$salto}). Matriculados: {$d['mat_prev']} -> {$d['mat_act']}. Cupo: " . ($d['cupo'] ?: 'N/D') . ". " . $d['motivo'];
                    $baseTxt = $d['periodo_anterior'] ?? '';
                    if (!empty($r['salto_detalle']) && !$r['salto_detalle']['base_es_inmediato']) {
                        $baseTxt .= ' (no existía en ' . $d['periodo_inmediato'] . ')';
                    }
                }

                $txtLabor = ''; $bgLabor = 'ffffff'; $fgLabor = '6b7280'; $detalleLaborTxt = '';
                if (($r['alerta_labor'] ?? null) === 'DUPLICIDAD_GRUPO_EXCESO_PE' && !empty($r['detalle_alerta_labor'])) {
                    $dLabor = $r['detalle_alerta_labor'];
                    [$txtLabor, $bgLabor, $fgLabor] = $LABOR_META['DUPLICIDAD_GRUPO_EXCESO_PE'];
                    $detalleLaborTxt = "Registros: {$dLabor['cantidad_registros']}. Docentes: {$dLabor['docentes_involucrados']}. Horas totales: {$dLabor['horas_totales']}. PE único: {$dLabor['pe_unico']}. Exceso: {$dLabor['exceso_horas']}. " . $dLabor['motivo'];
                }

                [$txtCoinc, $bgCoinc, $fgCoinc] = $COINCIDENCIA_META[$r['coincidencia_programa'] ?? 'NO_APLICA'] ?? ['N/A', 'f1f5f9', '64748b'];

                $diagnosticoTxt = '';
                if (!empty($r['detalle_codigo_posible_error'])) {
                    $dc = $r['detalle_codigo_posible_error'];
                    $pctMat = $dc['similitud_materia'] !== null ? round($dc['similitud_materia'] * 100) . '%' : 'N/D';
                    $pctProg = $dc['similitud_programa'] !== null ? round($dc['similitud_programa'] * 100) . '%' : 'N/D';
                    $diagnosticoTxt = "Cód. Labor: {$dc['codigo_materia_labor']} vs Cód. Oferta: {$dc['codigo_materia_oferta']} "
                        . "(similitud materia {$pctMat}, similitud programa {$pctProg}). " . $dc['motivo'];
                }

                $esSoloOferta = (($r['estado_alerta'] ?? '') === 'LABOR_INEXISTENTE')
                    || (($r['fuente_principal'] ?? '') === 'OFERTA');

                $codigoLabor = $esSoloOferta
                    ? ''
                    : ($r['codigo_materia'] ?? '');

                $codigoOferta = '';

                if ($esSoloOferta) {
                    $codigoOferta = $r['codigo_materia'] ?? '';
                } elseif (!empty($r['codigo_materia_oferta'])) {
                    $codigoOferta = $r['codigo_materia_oferta'];
                } elseif (!empty($r['detalle_codigo_posible_error']['codigo_materia_oferta'])) {
                    $codigoOferta = $r['detalle_codigo_posible_error']['codigo_materia_oferta'];
                } elseif (($r['estado_alerta'] ?? '') === 'OFERTA_INEXISTENTE') {
                    $codigoOferta = '';
                }

                $materiaLabor = $r['materia_labor'] ?? '';
                $materiaOferta = $r['materia_oferta'] ?? '';

                if ($esSoloOferta) {
                    $materiaLabor = '';
                    $materiaOferta = $r['materia_oferta'] ?? $r['materia'] ?? '';
                }

                if (!empty($r['detalle_codigo_posible_error'])) {
                    $dc = $r['detalle_codigo_posible_error'];
                    if (!empty($dc['materia_labor'])) {
                        $materiaLabor = $dc['materia_labor'];
                    }
                    if (!empty($dc['materia_oferta'])) {
                        $materiaOferta = $dc['materia_oferta'];
                    }
                }

                $celda = function ($v, $style = '') {
                    echo '<td style="' . $style . '">' . htmlspecialchars((string)($v ?? '—')) . '</td>';
                };

                $peValor = $r['pe'] ?? '';
                $esPECero = $peValor !== '' && is_numeric($peValor) && (float)$peValor === 0.0;
                $stylePE = $esPECero ? 'background:#fce4ec;color:#c62828;font-weight:bold;' : 'text-align:center;';

                echo '<tr>';

                $celda($r['fuente_principal'] ?? 'LABOR', 'font-weight:bold;');
                $celda($r['periodo']);
                $celda($r['facultad_labor']);
                $celda($r['departamento_labor']);
                $celda($r['identificacion']);
                $celda($r['docente'], 'font-weight:bold;');
                $celda($r['tipo_contrato']);

                $celda($codigoLabor);
                $celda($materiaLabor);
                $celda($r['grupo'] ?? '');                     // ← Grupo Labor
                $celda($codigoOferta);
                $celda($materiaOferta);
                $celda($r['grupo_oferta'] ?? $r['grupooferta'] ?? ''); // ← Grupo Oferta (NUEVO);

                $celda($r['programa_labor']);
                $celda($r['programa_oferta']);

                $celda(
                    $txtCoinc,
                    "background:#$bgCoinc;color:#$fgCoinc;font-weight:bold;text-align:center;"
                );
                $celda(
                    $r['similitud_programa'] !== null
                        ? round($r['similitud_programa'] * 100) . '%'
                        : '',
                    'text-align:center;'
                );
                $celda($r['motivo_programa']);


                $celda($r['horas_teoricas'], 'text-align:center;');
                echo '<td style="' . $stylePE . '">' . htmlspecialchars((string)($peValor !== '' ? $peValor : '—')) . '</td>';

                $celda(
                    $r['matriculados_oferta'] ?? $r['matriculados'] ?? '',
                    'text-align:center;'
                );
                $celda(
                    $r['cupo_oferta'] ?? $r['cupo'] ?? '',
                    'text-align:center;'
                );

                $celda(
                    $txtEstado,
                    "background:#$bgEstado;color:#$fgEstado;font-weight:bold;text-align:center;"
                );
                $celda(
                    $txtLabor,
                    "background:#$bgLabor;color:#$fgLabor;font-weight:bold;text-align:center;"
                );
                $celda($detalleLaborTxt);
                $celda(
                    $txtHist,
                    "background:#$bgHist;color:#$fgHist;font-weight:bold;text-align:center;"
                );
                                $celda($detalle);
                $celda($baseTxt);
                $celda($diagnosticoTxt);

                // ---- NUEVO: Alerta Cupo Subutilizado ----
                                 // ---- NUEVO: Alerta Cupo Subutilizado ----
                $tieneCupoSub = (($r['alerta_cupo_subutilizado'] ?? null) === 'CUPO_SUBUTILIZADO')
                                && (($r['fuente_principal'] ?? 'LABOR') === 'LABOR')
                                && !empty($r['oferta_id_asociada']);
                if ($tieneCupoSub) {
                    $dCS = $r['detalle_cupo_subutilizado'] ?? [];
                    $pctCS = (int)($dCS['porcentaje_cupo_vacio'] ?? 0);
                    $esSeveroCS = $pctCS >= 70;

                    $cupoLibreCS = ($dCS['total_cupo'] ?? 0) - ($dCS['total_matriculados'] ?? 0);

                    $detalleCS = 'Materia con ' . ($dCS['grupos_oferta'] ?? '?') . ' grupo(s) de Oferta que cruzaron con Labor, '
                               . ($dCS['total_matriculados'] ?? '?') . ' matriculados en total, '
                               . 'cupo total ' . ($dCS['total_cupo'] ?? '?') . ', cupo máximo ' . ($dCS['cupo_max'] ?? '?') . '. '
                               . 'Cupo sin usar: ' . $cupoLibreCS . ' de ' . ($dCS['total_cupo'] ?? '?') . ' (' . $pctCS . '%). '
                               . 'La matrícula cabe en ' . ($dCS['grupos_necesarios'] ?? '?') . ' grupo(s), '
                               . 'sobran ' . ($dCS['grupos_sobrantes'] ?? '?') . ' grupo(s).';

                    if ($esSeveroCS) {
                        $celda('CUPO SUBUTILIZADO ' . $pctCS . '%',
                            'background:#0f766e;color:#ffffff;font-weight:bold;text-align:center;');
                    } else {
                        $celda('CUPO SUBUTILIZADO',
                            'background:#ccfbf1;color:#0f766e;font-weight:bold;text-align:center;');
                    }
                    $celda($detalleCS);
                } else {
                    $celda('', 'text-align:center;color:#999;');
                    $celda('');
                }

                echo '</tr>';
            }

            echo '</table></body></html>';
            exit;

           /* =====================================================================
   8. EXPORTAR OBSERVACIÓN A WORD (con todos los tipos de alerta)
   ===================================================================== */
case 'exportar_observacion_word':
    $idObservacion = (int)($_GET['id'] ?? 0);
    if ($idObservacion <= 0) {
        echo json_encode(['success' => false, 'error' => 'Se requiere el id de la observación.']);
        exit;
    }

    $stmtObs = $pdo->prepare("SELECT * FROM observaciones WHERE id = :id");
    $stmtObs->execute(['id' => $idObservacion]);
    $observacion = $stmtObs->fetch(PDO::FETCH_ASSOC);
    if (!$observacion) {
        echo json_encode(['success' => false, 'error' => 'Observación no encontrada.']);
        exit;
    }

    $stmtFilas = $pdo->prepare("SELECT * FROM observaciones_filas WHERE id_observacion = :id");
    $stmtFilas->execute(['id' => $idObservacion]);
    $filasObs = $stmtFilas->fetchAll(PDO::FETCH_ASSOC);

    $periodoObs = $observacion['periodo'];

    // Recalcular auditoría actual
    $resultadoActual = construirAuditoria($pdo, ['periodo' => $periodoObs]);
    $indiceActual = [];
    foreach ($resultadoActual['alertas'] as $row) {
        if (empty($row['identificacion']) || empty($row['codigo_materia']) || empty($row['grupo'])) {
            continue;
        }
        $indiceActual[claveObsNormalizada($row['identificacion'], $row['codigo_materia'], $row['grupo'])] = $row;
    }

    // Tablas
    $tabla1 = []; // Plan de estudios superado (SOLO duplicidad-PE)
    $tablaProgramaDiferente = []; // Programa similar o diferente
    $tabla2 = []; // Diferencia en códigos
    $tabla3 = []; // Oferta inexistente (incluye subtipos: SIN_OFERTA y FALTA_GRUPO)
    $tabla4 = []; // Solo en Oferta
    $tablaDuplicados = []; // Registros duplicados en Labor
    $tablaSinMatriculados = []; // Grupos sin matriculados
    $tablaPECero = []; // ← AGREGAR ESTA LÍNEA

    $gruposDuplicidadProcesados = [];
    $gruposDuplicadosExactoProcesados = [];

    foreach ($filasObs as $f) {
        $clave = claveObsNormalizada($f['identificacion'], $f['codigo_materia'], $f['grupo']);
        $rowActual = $indiceActual[$clave] ?? null;
        if (!$rowActual) {
            continue;
        }

        $estadoActual = $rowActual['estado_alerta'] ?? null;
        $esDuplicidad = ($rowActual['alerta_labor'] ?? null) === 'DUPLICIDAD_GRUPO_EXCESO_PE';
        $tieneDuplicadoExacto = !empty($rowActual['alerta_duplicado_exacto']);

        // Obtener matriculados de la oferta asociada
        $matriculadosOferta = $rowActual['matriculados_oferta'] ?? $rowActual['matriculadosoferta'] ?? null;

        // --- NUEVA TABLA: Grupos sin matriculados ---
        if ($estadoActual !== 'OFERTA_INEXISTENTE' && $estadoActual !== 'LABOR_INEXISTENTE' && $matriculadosOferta !== null && (float)$matriculadosOferta === 0.0) {
            $tablaSinMatriculados[] = [
                'docente' => $rowActual['docente'] ?? null,
                'programa_oferta' => $rowActual['programa_oferta'] ?? null,
                'materia_labor' => $rowActual['materia_labor'] ?? null,
                'materia_oferta' => $rowActual['materia_oferta'] ?? null,
                'codigo_materia_labor' => $rowActual['codigo_materia'] ?? null,
                'codigo_materia_oferta' => $rowActual['codigo_materia_oferta'] ?? null,
                'grupo_oferta' => $rowActual['grupo_oferta'] ?? null,
                'matriculados' => $matriculadosOferta,
                'cupo' => $rowActual['cupo_oferta'] ?? $rowActual['cupooferta'] ?? null,
            ];
        }
          $peFila = $rowActual['pe'] ?? null;
        $esPECeroFila = ($peFila !== null && $peFila !== '' && is_numeric($peFila) && (float)$peFila === 0.0);
        if ($esPECeroFila) {
            $tablaPECero[] = [
                'departamento' => $rowActual['departamento_labor'] ?? null,
                'docente' => $rowActual['docente'] ?? null,
                'programa' => $rowActual['programa_labor'] ?? ($rowActual['programa_oferta'] ?? null),
                'materia' => $rowActual['materia_labor'] ?? ($rowActual['materia_oferta'] ?? null),
                'codigo_materia' => $rowActual['codigo_materia'] ?? ($rowActual['codigo_materia_oferta'] ?? null),
                'grupo' => $rowActual['grupo'] ?? null,
                'horas_teoricas' => $rowActual['horas_teoricas'] ?? null,
                'pe' => $peFila,
            ];
        }
        // --- Tabla 1: Plan de estudios superado (SOLO DUPLICIDAD_PE) ---
        // CORREGIDO: FALTA_GRUPO_EN_OFERTA ya NO va aquí
        if ($esDuplicidad) {
            $codigoNorm = trim((string)$f['codigo_materia']);
            $grupoNorm = trim((string)$f['grupo']);
            $programaRow = $rowActual['programa_labor'] ?? null;
            $grupoKey = $periodoObs . '|' . $codigoNorm . '|' . mb_strtolower((string)$programaRow) . '|' . $grupoNorm;
            if (!isset($gruposDuplicidadProcesados[$grupoKey])) {
                $gruposDuplicidadProcesados[$grupoKey] = true;
                $stmtDup = $pdo->prepare("SELECT facultad, departamento, docente, programa, materia,
                                              codigo_materia, grupo, horas_teoricas, pe
                                       FROM labor
                                       WHERE periodo = :periodo
                                         AND TRIM(codigo_materia) = :codigo
                                         AND TRIM(grupo) = :grupo
                                         AND TRIM(LOWER(programa)) = TRIM(LOWER(:programa))");
                $stmtDup->execute([
                    'periodo' => $periodoObs,
                    'codigo' => $codigoNorm,
                    'grupo' => $grupoNorm,
                    'programa' => $programaRow ?? '',
                ]);
                foreach ($stmtDup->fetchAll(PDO::FETCH_ASSOC) as $d) {
                    $tabla1[] = $d;
                }
            }
        }

        // --- Tabla 3: Oferta inexistente (AGRUPADA con subtipo) ---
        // Incluye OFERTA_INEXISTENTE y FALTA_GRUPO_EN_OFERTA
        if ($estadoActual === 'OFERTA_INEXISTENTE' || $estadoActual === 'FALTA_GRUPO_EN_OFERTA') {
            $subtipo = ($estadoActual === 'FALTA_GRUPO_EN_OFERTA') ? 'FALTA_GRUPO' : 'SIN_OFERTA';
            $tabla3[] = [
                'subtipo' => $subtipo,
                'departamento' => $rowActual['departamento_labor'] ?? null,
                'docente' => $rowActual['docente'] ?? null,
                'programa' => $rowActual['programa_labor'] ?? null,
                'materia' => $rowActual['materia_labor'] ?? null,
                'codigo_materia' => $rowActual['codigo_materia'] ?? null,
                'grupo' => $rowActual['grupo'] ?? null,
                'horas_teoricas' => $rowActual['horas_teoricas'] ?? null,
                'pe' => $rowActual['pe'] ?? null,
                // Para FALTA_GRUPO, agregamos información adicional de cobertura
                'detalle_cobertura' => $rowActual['detalle_cobertura'] ?? null,
            ];
        }

        // --- Tabla: Diferencia de programa ---
        $debeIrPrograma = false;
        if ($estadoActual === 'REVISAR_PROGRAMA_DIFERENTE') {
            $debeIrPrograma = true;
        } elseif ($estadoActual === 'REVISAR_CODIGO_MATERIA_DIFERENTE' && in_array($rowActual['coincidencia_programa'], ['ALTA', 'MEDIA', 'BAJA'])) {
            $debeIrPrograma = true;
        }

        if ($debeIrPrograma) {
            $clavePrograma = $clave;
            $existe = false;
            foreach ($tablaProgramaDiferente as $existing) {
                if (($existing['_clave'] ?? '') === $clavePrograma) {
                    $existe = true;
                    break;
                }
            }
            if (!$existe) {
                $tablaProgramaDiferente[] = [
                    '_clave' => $clavePrograma,
                    'docente' => $rowActual['docente'] ?? null,
                    'programa_labor' => $rowActual['programa_labor'] ?? null,
                    'programa_oferta' => $rowActual['programa_oferta'] ?? null,
                    'materia' => $rowActual['materia_labor'] ?? null,
                    'codigo' => $rowActual['codigo_materia'] ?? null,
                    'grupo' => $rowActual['grupo'] ?? null,
                ];
            }
        }

        // --- Tabla 2: Diferencia en códigos ---
        if ($estadoActual === 'REVISAR_CODIGO_MATERIA_DIFERENTE') {
            $dc = $rowActual['detalle_codigo_posible_error'] ?? null;
            $tabla2[] = [
                'docente' => $rowActual['docente'] ?? null,
                'programa_labor' => $rowActual['programa_labor'] ?? null,
                'programa_oferta' => $dc['programa_oferta'] ?? ($rowActual['programa_oferta'] ?? null),
                'similitud_programa' => $rowActual['similitud_programa'] ?? null,
                'materia_labor' => $rowActual['materia_labor'] ?? null,
                'codigo_materia_labor' => $rowActual['codigo_materia'] ?? null,
                'grupo' => $rowActual['grupo'] ?? null,
                'horas_teoricas' => $rowActual['horas_teoricas'] ?? null,
                'codigo_materia_oferta' => $dc['codigo_materia_oferta'] ?? ($rowActual['codigo_materia_oferta'] ?? null),
                'materia_oferta' => $dc['materia_oferta'] ?? ($rowActual['materia_oferta'] ?? null),
            ];
        }

        // --- Tabla 4: Solo en Oferta ---
        if ($estadoActual === 'LABOR_INEXISTENTE') {
            $tabla4[] = [
                'docente' => $rowActual['docente'] ?? null,
                'programa' => $rowActual['programa_oferta'] ?? null,
                'materia' => $rowActual['materia_oferta'] ?? null,
                'codigo_materia' => $rowActual['codigo_materia'] ?? null,
                'grupo' => $rowActual['grupo'] ?? null,
                'matriculados' => $rowActual['matriculados'] ?? null,
                'cupo' => $rowActual['cupo'] ?? null,
            ];
        }

        // --- Tabla 5: Registros duplicados en Labor ---
        if ($tieneDuplicadoExacto) {
            $codigoNorm = trim((string)$f['codigo_materia']);
            $grupoNorm = trim((string)$f['grupo']);
            $identificacionNorm = trim((string)$f['identificacion']);
            $grupoKey = $periodoObs . '|' . $identificacionNorm . '|' . $codigoNorm . '|' . $grupoNorm;
            if (!isset($gruposDuplicadosExactoProcesados[$grupoKey])) {
                $gruposDuplicadosExactoProcesados[$grupoKey] = true;
                $stmtDupExacto = $pdo->prepare("SELECT docente, programa, materia, codigo_materia, grupo, horas_teoricas, pe, id
                                                FROM labor
                                                WHERE periodo = :periodo
                                                  AND identificacion = :identificacion
                                                  AND TRIM(codigo_materia) = :codigo
                                                  AND TRIM(grupo) = :grupo");
                $stmtDupExacto->execute([
                    'periodo' => $periodoObs,
                    'identificacion' => $identificacionNorm,
                    'codigo' => $codigoNorm,
                    'grupo' => $grupoNorm,
                ]);
                foreach ($stmtDupExacto->fetchAll(PDO::FETCH_ASSOC) as $d) {
                    $tablaDuplicados[] = $d;
                }
            }
        }
    }

    // --- Generación del documento Word ---
    $nombreArchivo = 'observacion_' . $idObservacion . '_' .
        preg_replace('/[^A-Za-z0-9_\-]/', '_', $periodoObs) . '.doc';

    header('Content-Type: application/msword; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    header('Cache-Control: max-age=0');

    // Estilo para PE=0 y Matriculados=0
    $stylePECero = 'background:#fce4ec;color:#c62828;font-weight:bold;';
    $styleMatriculadosCero = 'background:#fce4ec;color:#c62828;font-weight:bold;';

    echo "\xEF\xBB\xBF";
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" 
                xmlns:w="urn:schemas-microsoft-com:office:word" 
                xmlns="http://www.w3.org/TR/REC-html40">
          <head>
            <meta charset="UTF-8">
            <!--[if gte mso 9]>
            <xml>
              <w:WordDocument>
                <w:View>Print</w:View>
                <w:Zoom>100</w:Zoom>
              </w:WordDocument>
            </xml>
            <![endif]-->
            <style>
                /* PÁGINA EN HORIZONTAL (LANDSCAPE) CON MÁRGENES ESTRECHOS */
                @page {
                    size: 11in 8.5in;
                    margin: 0.5in 0.5in 0.5in 0.5in
                    mso-page-orientation: landscape;
                }
                @page Section1 {
                    size: 11in 8.5in;
                    mso-page-orientation: landscape;
                    mso-header-margin: 0.3in;
                    mso-footer-margin: 0.3in;
                    mso-page-margin: 0.5in 0.5in 0.5in 0.5in;
                }
                div.Section1 { page: Section1; }

                body {
                        font-family: Arial, sans-serif;
                        font-size: 10pt;
                        margin: 0;
                    }
                h2 {
                    color: #1e3a8a;
                    font-size: 14pt;
                    border-bottom: 2px solid #1e3a8a;
                    padding-bottom: 4px;
                    margin-bottom: 8px;
                }
                h3 {
                    color: #1e3a8a;
                    font-size: 11pt;
                    margin-top: 14px;
                    margin-bottom: 6px;
                }
                table {
                    border-collapse: collapse;
                    width: auto;
                    max-width: 100%;
                    table-layout: auto;
                    margin: 0 auto 8px auto;
                    font-size: 8pt;
                }
                th {
                    padding: 2px 3px;
                    border: 1px solid #999;
                    text-align: left;
                    font-size: 8pt;
                    font-weight: bold;
                    white-space: nowrap;
                }
                td {
                    padding: 1px 3px;
                    border: 1px solid #999;
                    font-size: 8pt;
                    word-wrap: break-word;
                    overflow-wrap: anywhere;
                }
                .subtipo-falta-grupo {
                    background: #ffedd5;
                    font-weight: bold;
                    color: #9a3412;
                    padding: 1px 4px;
                    border-radius: 3px;
                    font-size: 7pt;
                    display: inline-block;
                }
                .subtipo-sin-oferta {
                    background: #fee2e2;
                    font-weight: bold;
                    color: #991b1b;
                    padding: 1px 4px;
                    border-radius: 3px;
                    font-size: 7pt;
                    display: inline-block;
                }
            </style>
          </head>
          <body>
            <div class="Section1">';

    $celda = function ($v) {
        return '<td style="border:1px solid #999;padding:1px 3px;font-size:8pt;">'
             . htmlspecialchars((string)($v ?? '—')) . '</td>';
    };
    $thEstilo = function ($color) {
        return 'border:1px solid #999;padding:2px 3px;background:' . $color . ';color:#fff;font-size:8pt;font-weight:bold;white-space:nowrap;';
    };

    // Función auxiliar para pintar PE con estilo si es 0
    $celdaPE = function($pe) use ($stylePECero) {
        $peValor = $pe ?? '';
        $esCero = $peValor !== '' && is_numeric($peValor) && (float)$peValor === 0.0;
        $style = $esCero ? $stylePECero : '';
        return '<td style="border:1px solid #999;padding:1px 3px;font-size:8pt;' . $style . '">'
             . htmlspecialchars((string)($peValor !== '' ? $peValor : '—')) . '</td>';
    };

    $celdaMatriculados = function($mat) use ($styleMatriculadosCero) {
        $matValor = $mat ?? '';
        $esCero = $matValor !== '' && is_numeric($matValor) && (float)$matValor === 0.0;
        $style = $esCero ? $styleMatriculadosCero . ';' : '';
        $nota = $esCero ? ' ⚠ Sin matrícula' : '';
        return '<td style="border:1px solid #999;padding:1px 3px;font-size:8pt;' . $style . '">'
             . htmlspecialchars((string)($matValor !== '' ? $matValor : '—')) . $nota . '</td>';
    };

    echo '<h2>' . htmlspecialchars($observacion['titulo']) . '</h2>';
    echo '<p style="font-size:9pt;"><b>Periodo:</b> ' . htmlspecialchars($periodoObs)
       . ' &nbsp; <b>Estado:</b> ' . htmlspecialchars($observacion['estado'])
       . ' &nbsp; <b>Generado:</b> ' . date('Y-m-d H:i') . '</p>';
    if (!empty($observacion['descripcion'])) {
        echo '<p style="font-size:9pt;">' . nl2br(htmlspecialchars($observacion['descripcion'])) . '</p>';
    }

    // 1. Plan de estudios superado (SOLO duplicidad-PE)
    if (!empty($tabla1)) {
        echo '<h3>&#9888;&#65039; Plan de estudios superado</h3>';
        echo '<p style="font-size:8.5pt;">A continuación, se enuncian los cursos a los cuales se les ha asignado más horas '
           . 'de las estipuladas por el plan de estudios (la suma de horas teóricas supera el PE).</p>';
        echo '<table style="border-collapse:collapse;width:100%;"><tr>'
           . '<th style="' . $thEstilo('#1e3a8a') . '">DEPARTAMENTO</th>'
           . '<th style="' . $thEstilo('#1e3a8a') . '">APELLIDOS NOMBRES</th>'
           . '<th style="' . $thEstilo('#1e3a8a') . '">PROGRAMA</th>'
           . '<th style="' . $thEstilo('#1e3a8a') . '">NOMBRE MATERIA</th>'
           . '<th style="' . $thEstilo('#1e3a8a') . '">CÓDIGO MATERIA</th>'
           . '<th style="' . $thEstilo('#1e3a8a') . '">GRUPO</th>'
           . '<th style="' . $thEstilo('#1e3a8a') . '">HORAS TEÓRICAS</th>'
           . '<th style="' . $thEstilo('#1e3a8a') . '">PE</th></tr>';
        foreach ($tabla1 as $r) {
            echo '<tr>' . $celda($r['departamento'] ?? null) . $celda($r['docente'] ?? null)
               . $celda($r['programa'] ?? null) . $celda($r['materia'] ?? null)
               . $celda($r['codigo_materia'] ?? null) . $celda($r['grupo'] ?? null)
               . $celda($r['horas_teoricas'] ?? null) . $celdaPE($r['pe'] ?? null) . '</tr>';
        }
        echo '</table><br>';
    }

    // 2. Oferta inexistente (AGRUPADA con subtipos)
    if (!empty($tabla3)) {
        // Contar cuántos de cada subtipo hay
        $totalSinOferta = 0;
        $totalFaltaGrupo = 0;
        foreach ($tabla3 as $r) {
            if ($r['subtipo'] === 'SIN_OFERTA') {
                $totalSinOferta++;
            } else {
                $totalFaltaGrupo++;
            }
        }

        $tituloTabla = '&#128683; Oferta inexistente o insuficiente';
        $descripcion = 'Estos registros existen en Labor pero NO tienen correspondencia en Oferta. ';
        if ($totalSinOferta > 0 && $totalFaltaGrupo > 0) {
            $descripcion .= 'Se distinguen dos casos: ';
            $descripcion .= '<span class="subtipo-sin-oferta">SIN OFERTA</span> (la materia no existe en Oferta) y ';
            $descripcion .= '<span class="subtipo-falta-grupo">FALTA GRUPO</span> (la materia existe pero no hay suficientes grupos).';
        } elseif ($totalSinOferta > 0) {
            $descripcion .= 'La materia no existe en el sistema de Oferta para este periodo.';
        } else {
            $descripcion .= 'La materia existe en Oferta pero no hay suficientes grupos disponibles para cubrir la demanda.';
        }

        echo '<h3>' . $tituloTabla . '</h3>';
        echo '<p style="font-size:8.5pt;">' . $descripcion . '</p>';

        echo '<table style="border-collapse:collapse;width:100%;"><tr>'
           . '<th style="' . $thEstilo('#b91c1c') . '">TIPO</th>'
           . '<th style="' . $thEstilo('#b91c1c') . '">DEPARTAMENTO</th>'
           . '<th style="' . $thEstilo('#b91c1c') . '">APELLIDOS NOMBRES</th>'
           . '<th style="' . $thEstilo('#b91c1c') . '">PROGRAMA</th>'
           . '<th style="' . $thEstilo('#b91c1c') . '">NOMBRE MATERIA</th>'
           . '<th style="' . $thEstilo('#b91c1c') . '">CÓDIGO MATERIA</th>'
           . '<th style="' . $thEstilo('#b91c1c') . '">GRUPO</th>'
           . '<th style="' . $thEstilo('#b91c1c') . '">HORAS TEÓRICAS</th>'
           . '<th style="' . $thEstilo('#b91c1c') . '">PE</th></tr>';

        foreach ($tabla3 as $r) {
            $subtipoLabel = ($r['subtipo'] === 'FALTA_GRUPO')
                ? '<span class="subtipo-falta-grupo">FALTA GRUPO</span>'
                : '<span class="subtipo-sin-oferta">SIN OFERTA</span>';

            // Si es FALTA_GRUPO, agregar tooltip con detalle de cobertura
            $detalleCobertura = '';
            if ($r['subtipo'] === 'FALTA_GRUPO' && !empty($r['detalle_cobertura'])) {
                $dc = $r['detalle_cobertura'];
                $detalleCobertura = ' title="Ofertas disponibles: ' . ($dc['ofertas_disponibles'] ?? '?')
                                  . ' | Ofertas necesarias: ' . ($dc['ofertas_necesarias'] ?? '?')
                                  . ' | Horas Labor: ' . ($dc['horas_labor'] ?? '?')
                                  . ' | PE: ' . ($dc['pe'] ?? '?') . '"';
            }

            echo '<tr' . $detalleCobertura . '>'
               . '<td style="border:1px solid #999;padding:1px 3px;font-size:8pt;text-align:center;">' . $subtipoLabel . '</td>'
               . $celda($r['departamento'] ?? null)
               . $celda($r['docente'] ?? null)
               . $celda($r['programa'] ?? null)
               . $celda($r['materia'] ?? null)
               . $celda($r['codigo_materia'] ?? null)
               . $celda($r['grupo'] ?? null)
               . $celda($r['horas_teoricas'] ?? null)
               . $celdaPE($r['pe'] ?? null) . '</tr>';
        }
        echo '</table><br>';
    }

    // 3. Programa similar o diferente
    if (!empty($tablaProgramaDiferente)) {
        echo '<h3>&#128202; Programa similar o diferente</h3>';
        echo '<p style="font-size:8.5pt;">Se detectaron diferencias entre el programa reportado en Labor y el registrado en Oferta.</p>';

        echo '<table style="border-collapse:collapse;width:100%;table-layout:auto;"><tr>'
           . '<th style="' . $thEstilo('#b45309') . 'width:14%;">APELLIDOS NOMBRES</th>'
           . '<th style="' . $thEstilo('#b45309') . 'width:20%;">PROGRAMA LABOR</th>'
           . '<th style="' . $thEstilo('#b45309') . 'width:20%;">PROGRAMA OFERTA</th>'
           . '<th style="' . $thEstilo('#b45309') . 'width:18%;">MAT LABOR</th>'
           . '<th style="' . $thEstilo('#b45309') . 'width:8%;text-align:center;">COD LABOR</th>'
           . '<th style="' . $thEstilo('#b45309') . 'width:8%;text-align:center;">GRUPO</th>'
           . '</tr>';

        foreach ($tablaProgramaDiferente as $r) {
            echo '<tr>'
               . $celda($r['docente'] ?? null)
               . $celda($r['programa_labor'] ?? null)
               . $celda($r['programa_oferta'] ?? null)
               . $celda($r['materia'] ?? null)
               . $celda($r['codigo'] ?? null)
               . $celda($r['grupo'] ?? null)
               . '</tr>';
        }

        echo '</table><br>';
    }

    // 4. Diferencia en códigos de labor y oferta
    if (!empty($tabla2)) {
        echo '<h3>&#128269; Diferencia en códigos de labor y oferta</h3>';
        echo '<p style="font-size:8.5pt;">Se detectaron asignaturas con diferencia entre los códigos reportados en Labor y los registrados en Oferta.</p>';

        echo '<table style="border-collapse:collapse;width:100%;table-layout:auto;"><tr>'
           . '<th style="' . $thEstilo('#4338ca') . 'width:10%;">APELLIDOS NOMBRES</th>'
           . '<th style="' . $thEstilo('#4338ca') . 'width:15%;">PROGRAMA LABOR</th>'
           . '<th style="' . $thEstilo('#4338ca') . 'width:15%;">PROGRAMA OFERTA</th>'
           . '<th style="' . $thEstilo('#4338ca') . 'width:14%;">MAT LABOR</th>'
           . '<th style="' . $thEstilo('#4338ca') . 'width:6%;text-align:center;">COD LABOR</th>'
           . '<th style="' . $thEstilo('#4338ca') . 'width:6%;text-align:center;">GRUPO</th>'
           . '<th style="' . $thEstilo('#4338ca') . 'width:5%;text-align:center;">HRS T</th>'
           . '<th style="' . $thEstilo('#4338ca') . 'width:8%;text-align:center;">COD OFERTA</th>'
           . '<th style="' . $thEstilo('#4338ca') . 'width:15%;">MAT OFERTA</th>'
           . '</tr>';

        foreach ($tabla2 as $r) {
            echo '<tr>'
               . $celda($r['docente'] ?? null)
               . $celda($r['programa_labor'] ?? null)
               . $celda($r['programa_oferta'] ?? null)
               . $celda($r['materia_labor'] ?? null)
               . $celda($r['codigo_materia_labor'] ?? null)
               . $celda($r['grupo'] ?? null)
               . $celda($r['horas_teoricas'] ?? null)
               . $celda($r['codigo_materia_oferta'] ?? null)
               . $celda($r['materia_oferta'] ?? null)
               . '</tr>';
        }

        echo '</table><br>';
    }

    // 5. Solo en Oferta
    if (!empty($tabla4)) {
        echo '<h3>&#128203; Solo en Oferta (sin Labor)</h3>';
        echo '<p style="font-size:8.5pt;">Estos registros existen en Oferta pero NO tienen correspondencia en Labor.</p>';
        echo '<table style="border-collapse:collapse;width:100%;"><tr>'
           . '<th style="' . $thEstilo('#be185d') . '">APELLIDOS NOMBRES</th>'
           . '<th style="' . $thEstilo('#be185d') . '">PROGRAMA</th>'
           . '<th style="' . $thEstilo('#be185d') . '">NOMBRE MATERIA</th>'
           . '<th style="' . $thEstilo('#be185d') . '">CÓDIGO MATERIA</th>'
           . '<th style="' . $thEstilo('#be185d') . '">GRUPO</th>'
           . '<th style="' . $thEstilo('#be185d') . '">MATRICULADOS</th>'
           . '<th style="' . $thEstilo('#be185d') . '">CUPO</th></tr>';
        foreach ($tabla4 as $r) {
            echo '<tr>' . $celda($r['docente'] ?? null) . $celda($r['programa'] ?? null)
               . $celda($r['materia'] ?? null) . $celda($r['codigo_materia'] ?? null)
               . $celda($r['grupo'] ?? null) . $celdaMatriculados($r['matriculados'] ?? null)
               . $celda($r['cupo'] ?? null) . '</tr>';
        }
        echo '</table><br>';
    }

    // 6. Registros duplicados en Labor
    if (!empty($tablaDuplicados)) {
        echo '<h3>&#128203; Registro duplicado en Labor</h3>';
        echo '<p style="font-size:8.5pt;">Se encontraron registros duplicados en la fuente Labor para el mismo docente, código de materia y grupo, con o sin cambio de programa.</p>';
        echo '<table style="border-collapse:collapse;width:100%;"><tr>'
           . '<th style="' . $thEstilo('#0891b2') . '">DOCENTE</th>'
           . '<th style="' . $thEstilo('#0891b2') . '">PROGRAMA</th>'
           . '<th style="' . $thEstilo('#0891b2') . '">MATERIA</th>'
           . '<th style="' . $thEstilo('#0891b2') . '">CÓDIGO</th>'
           . '<th style="' . $thEstilo('#0891b2') . '">GRUPO</th>'
           . '<th style="' . $thEstilo('#0891b2') . '">HORAS</th>'
           . '<th style="' . $thEstilo('#0891b2') . '">PE</th>'
           . '<th style="' . $thEstilo('#0891b2') . '">ID REGISTRO</th></tr>';
        foreach ($tablaDuplicados as $r) {
            echo '<tr>' . $celda($r['docente'] ?? null) . $celda($r['programa'] ?? null)
               . $celda($r['materia'] ?? null) . $celda($r['codigo_materia'] ?? null)
               . $celda($r['grupo'] ?? null) . $celda($r['horas_teoricas'] ?? null)
               . $celdaPE($r['pe'] ?? null) . $celda($r['id'] ?? null) . '</tr>';
        }
        echo '</table><br>';
    }

    // 7. Grupos sin matriculados
    if (!empty($tablaSinMatriculados)) {
        echo '<h3>&#128203; Grupos sin matriculados</h3>';
        echo '<p style="font-size:8.5pt;">Los siguientes grupos tienen matrícula 0 en Oferta.</p>';
        echo '<table style="border-collapse:collapse;width:100%;"><tr>'
           . '<th style="' . $thEstilo('#ea580c') . '">DOCENTE</th>'
           . '<th style="' . $thEstilo('#ea580c') . '">PROGRAMA OFERTA</th>'
           . '<th style="' . $thEstilo('#ea580c') . '">MAT LABOR</th>'
           . '<th style="' . $thEstilo('#ea580c') . '">MAT OFERTA</th>'
           . '<th style="' . $thEstilo('#ea580c') . '">COD LABOR</th>'
           . '<th style="' . $thEstilo('#ea580c') . '">COD OFERTA</th>'
           . '<th style="' . $thEstilo('#ea580c') . '">GRUPO OFERTA</th>'
           . '<th style="' . $thEstilo('#ea580c') . '">MATRICULADOS</th>'
           . '<th style="' . $thEstilo('#ea580c') . '">CUPO</th></tr>';
        foreach ($tablaSinMatriculados as $r) {
            echo '<tr>' . $celda($r['docente'] ?? null)
               . $celda($r['programa_oferta'] ?? null)
               . $celda($r['materia_labor'] ?? null)
               . $celda($r['materia_oferta'] ?? null)
               . $celda($r['codigo_materia_labor'] ?? null)
               . $celda($r['codigo_materia_oferta'] ?? null)
               . $celda($r['grupo_oferta'] ?? null)
               . $celdaMatriculados($r['matriculados'] ?? null)
               . $celda($r['cupo'] ?? null) . '</tr>';
        }
        echo '</table><br>';
    }

    // 8. PE = 0  ← NUEVA SECCIÓN
    if (!empty($tablaPECero)) {
        echo '<h3>&#128308; PE = 0</h3>';
        echo '<p style="font-size:8.5pt;">Los siguientes registros tienen Punto de Equilibrio (PE) igual a 0. Este valor debe revisarse porque impide calcular la cobertura horaria correctamente.</p>';
        echo '<table style="border-collapse:collapse;width:100%;"><tr>'
           . '<th style="' . $thEstilo('#b91c1c') . '">DEPARTAMENTO</th>'
           . '<th style="' . $thEstilo('#b91c1c') . '">APELLIDOS NOMBRES</th>'
           . '<th style="' . $thEstilo('#b91c1c') . '">PROGRAMA</th>'
           . '<th style="' . $thEstilo('#b91c1c') . '">NOMBRE MATERIA</th>'
           . '<th style="' . $thEstilo('#b91c1c') . '">CÓDIGO MATERIA</th>'
           . '<th style="' . $thEstilo('#b91c1c') . '">GRUPO</th>'
           . '<th style="' . $thEstilo('#b91c1c') . '">HORAS TEÓRICAS</th>'
           . '<th style="' . $thEstilo('#b91c1c') . '">PE</th></tr>';
        foreach ($tablaPECero as $r) {
            echo '<tr>'
               . $celda($r['departamento'] ?? null)
               . $celda($r['docente'] ?? null)
               . $celda($r['programa'] ?? null)
               . $celda($r['materia'] ?? null)
               . $celda($r['codigo_materia'] ?? null)
               . $celda($r['grupo'] ?? null)
               . $celda($r['horas_teoricas'] ?? null)
               . $celdaPE($r['pe'] ?? null)
               . '</tr>';
        }
        echo '</table><br>';
    }
    // Mensaje final
      if (empty($tabla1) && empty($tablaProgramaDiferente) && empty($tabla2) && empty($tabla3) && empty($tabla4) && empty($tablaDuplicados) && empty($tablaSinMatriculados) && empty($tablaPECero)) {
        echo '<p style="font-size:9pt;font-style:italic;">Todas las filas de esta observación ya fueron subsanadas o no se encontraron '
           . 'en el periodo vigente.</p>';
    }

    echo '<div style="margin-top:16px;font-size:8pt;color:#666;border-top:1px solid #ddd;padding-top:6px;">'
       . 'Documento generado automáticamente desde el Sistema de Auditoría Docente - Universidad del Cauca<br>'
       . 'Fecha de generación: ' . date('Y-m-d H:i:s') . '</div>';

    echo '</div></body></html>';
    break;

        default:
            echo json_encode([
                'success' => false,
                'error' => 'Acción no válida. Opciones: filtros, alertas, docente_radiografia, docente_periodo, materia_historico, labor_materia_grupo, exportar_excel, exportar_observacion_word, guardar_observacion, obtener_observacion, listar_observaciones',
            ]);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}