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
    ];
}

/**
 * Búsqueda tolerante (sin tildes, sin mayúsculas, sin espacios extra) sobre
 * una colección combinada de filas Labor / Oferta-sin-Labor.
 */
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
        // (ej. "Inglés IV" no puede sustituir a "Inglés V"), sin importar
        // qué tan alta sea la similitud textual bruta.
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

    $maxSimMateria = max(array_column($candidatos, 'sim_materia'));

    $empatados = array_values(array_filter($candidatos, function ($c) use ($maxSimMateria, $margenEmpate) {
        return $c['sim_materia'] >= ($maxSimMateria - $margenEmpate);
    }));
    usort($empatados, function ($a, $b) {
        return $b['sim_programa'] <=> $a['sim_programa'];
    });

    $mejor = $empatados[0];
    $mejorOferta = $mejor['oferta'];

    return [
        'codigo_materia_labor' => $filaLabor['codigo_materia'] ?? null,
        'codigo_materia_oferta' => $mejorOferta['codigo_materia'],
        'materia_labor' => $materiaLabor,
        'materia_oferta' => $mejorOferta['materia'],
        'programa_labor' => $programaLabor,
        'programa_oferta' => $mejorOferta['programa'],
        'grupo_oferta' => $mejorOferta['grupo'],
        'matriculados_oferta' => $mejorOferta['matriculados'] ?? null,
        'cupo_oferta' => $mejorOferta['cupo'] ?? null,
        'oferta_id' => $mejorOferta['id'],
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
 * - Recorre las observaciones ABIERTAS del periodo.
 * - Para cada una, revisa si TODAS sus filas ya dejaron de tener el error
 *   original (comparando contra $alertasActuales, que es el resultado YA
 *   calculado por construirAuditoria() para ese mismo periodo).
 * - Si una fila ya no aparece en $alertasActuales -> se asume subsanada
 *   (el registro fue borrado/cambiado en el nuevo Excel SIMCA).
 * - Si aparece pero su estado_alerta es 'OK' y no tiene alerta_labor de
 *   duplicidad -> también se considera subsanada.
 * - Si TODAS las filas de una observación están subsanadas, se actualiza en
 *   BD su estado a 'SUBSANADA'.
 *
 * Retorna un mapa [claveNormalizada => ['id_observacion' => int, 'estado' => string]]
 * para que el llamador pueda inyectar el badge en cada fila del dashboard.
 */
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
                $tieneAlertaLabor = !empty($actual['alerta_labor']);
                $sigueConError = !$esOk || $tieneAlertaLabor;
                if ($sigueConError) {
                    $razones[] = "Fila {$f['identificacion']}|{$f['codigo_materia']}|{$f['grupo']} aún tiene error: estado={$actual['estado_alerta']}, alerta_labor={$actual['alerta_labor']}";
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

            $alertas[] = array_merge($l, [
                'fuente_principal' => 'LABOR',
                'estado_alerta' => $estadoAlerta,
                'es_prestacion_servicio' => 0,

                'programa_oferta' => $o['programa'],
                'materia_oferta' => $o['materia'],
                'codigo_materia_oferta' => $o['codigo_materia'],
                'matriculados_oferta' => $o['matriculados'] ?? null,
                'cupo_oferta' => $o['cupo'] ?? null,

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

                $alertas[] = array_merge($l, [
                    'fuente_principal' => 'LABOR',
                    'estado_alerta' => $estadoAlerta,
                    'es_prestacion_servicio' => 0,

                    'programa_oferta' => $o['programa'],
                    'materia_oferta' => $o['materia'],
                    'codigo_materia_oferta' => $o['codigo_materia'],
                    'matriculados_oferta' => $o['matriculados'] ?? null,
                    'cupo_oferta' => $o['cupo'] ?? null,

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
        $comparacionSinOferta = compararProgramas($l['programa_labor'], null);

        $estadoFinal = $diagnosticoCodigo !== null ? 'REVISAR_CODIGO_MATERIA_DIFERENTE' : 'OFERTA_INEXISTENTE';

        if ($diagnosticoCodigo !== null) {
            foreach ($ofertaMismoDocenteLibre as $cand) {
                if ((int)$cand['id'] === (int)$diagnosticoCodigo['oferta_id']) {
                    $ofertaUsada[$cand['__idx']] = true;
                    break;
                }
            }
        }

        $alertas[] = array_merge($l, [
            'fuente_principal' => 'LABOR',
            'estado_alerta' => $estadoFinal,
            'es_prestacion_servicio' => $diagnosticoCodigo !== null ? 0 : 1,

            'programa_oferta' => $diagnosticoCodigo['programa_oferta'] ?? null,
            'materia_oferta' => $diagnosticoCodigo['materia_oferta'] ?? null,
            'codigo_materia_oferta' => $diagnosticoCodigo['codigo_materia_oferta'] ?? null,
            'matriculados_oferta' => $diagnosticoCodigo['matriculados_oferta'] ?? null,
            'cupo_oferta' => $diagnosticoCodigo['cupo_oferta'] ?? null,

            'coincidencia_programa' => $comparacionSinOferta['tipo'],
            'similitud_programa' => $comparacionSinOferta['similitud'],
            'motivo_programa' => $comparacionSinOferta['motivo'],
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

    foreach ($alertas as &$row) {
        $row['alerta_labor'] = null;
        $row['detalle_alerta_labor'] = null;

        if (($row['fuente_principal'] ?? 'LABOR') !== 'LABOR') {
            continue;
        }

        $clave = trim((string)($row['codigo_materia'] ?? '')) . '|' . mb_strtolower(trim((string)($row['programa_labor'] ?? ''))) . '|' . trim((string)($row['grupo'] ?? ''));
        if (isset($mapaDuplicidad[$clave])) {
            $dup = $mapaDuplicidad[$clave];
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
    }
    unset($row);

    /* -----------------------------------------------------------------
       7. Histórico por materia, independiente del docente
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
        $rsHist = $pdo->query("SELECT periodo, TRIM(codigo_materia) AS codigo_materia, COUNT(*) AS grupos,
                                       COALESCE(SUM(matriculados), 0) AS matriculados, MAX(cupo) AS cupo
                                FROM oferta
                                GROUP BY periodo, TRIM(codigo_materia)");
        foreach ($rsHist->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $ofeHist[$r['periodo']][$r['codigo_materia']] = [
                'grupos' => (int)$r['grupos'],
                'matriculados' => (int)$r['matriculados'],
                'cupo' => (int)($r['cupo'] ?? 0),
            ];
        }
    }

    foreach ($alertas as &$row) {
        $row['alerta_historica'] = null;
        $row['salto_detalle'] = null;

        $codigo = trim((string)($row['codigo_materia'] ?? ''));
        if ($codigo === '') {
            continue;
        }
        $actual = $ofeHist[$periodo][$codigo] ?? null;
        if ($actual === null) {
            continue;
        }

        $periodoBase = null;
        $base = null;
        foreach ($prevPeriodos as $p => $orden) {
            if (isset($ofeHist[$p][$codigo])) {
                $periodoBase = $p;
                $base = $ofeHist[$p][$codigo];
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
       8. COBERTURA HORARIA POR MATERIA + PROGRAMA
       
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
        // Solo evaluamos filas de Labor que están en estado OFERTA_INEXISTENTE
        // (es decir, que no tienen oferta asignada)
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

    // Ahora aplicamos la cobertura solo a las filas que están en OFERTA_INEXISTENTE
    foreach ($alertas as &$row) {
        if (($row['fuente_principal'] ?? 'LABOR') !== 'LABOR') {
            continue;
        }
        // Solo procesar filas que están en OFERTA_INEXISTENTE
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
        }
        if ((int)($row['es_prestacion_servicio'] ?? 0) === 1) {
            $kpis['prestacion_servicio']++;
        }
        if (($row['alerta_labor'] ?? null) === 'DUPLICIDAD_GRUPO_EXCESO_PE') {
            $kpis['duplicidad_grupo_pe']++;
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
 * BLOQUE 2.B — NUEVO case. Pégalo en cualquier parte del switch, por ejemplo
 * justo antes del "default:" final.
 * Guarda una observación nueva vinculando las filas seleccionadas por
 * Clave de Negocio (periodo + identificacion + codigo_materia + grupo).
 *
 * Espera POST JSON:
 * {
 *   "periodo": "2026.2",
 *   "titulo": "...",
 *   "descripcion": "...",
 *   "filas": [
 *     { "identificacion": "123", "codigo_materia": "ABC1", "grupo": "01", "estado_alerta_original": "DUPLICIDAD_GRUPO_EXCESO_PE" },
 *     ...
 *   ]
 * }
 * ========================================================================== */
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
                continue; // fila incompleta, se omite (p.ej. filas 'oferta_xxx' sin identificación real)
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
 * BLOQUE 2.C — NUEVO case. Devuelve el detalle completo de una observación
 * (encabezado + filas) para pintar el modal de detalle al hacer clic en el
 * badge de la tabla.
 * ========================================================================== */
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
 * BLOQUE 2.D — NUEVO case. Lista las observaciones de un periodo (para
 * mostrar, si algún día quieres, un panel independiente con todas ellas).
 * No es estrictamente indispensable para el flujo pedido, pero es barato y
 * útil como apoyo administrativo.
 * ========================================================================== */
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
                            'labor' => $l,  // $l tiene las claves originales 'programa' y 'materia'
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
                // Para detectarPosibleCodigoErroneo necesitamos un arreglo con claves 'programa_labor' y 'materia_labor'
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
                    'labor' => $l,  // $l con claves originales
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
            ];
            $HIST_META = [
                'SALTO_FUERTE' => ['SALTO FUERTE', 'fee2e2', 'b91c1c'],
                'SALTO_JUSTIFICADO' => ['SALTO JUSTIFICADO', 'd1fae5', '047857'],
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
            echo '<tr><td colspan="28" style="background:#1e3a8a;color:#fff;font-size:14px;font-weight:bold;">' . 'REPORTE DE ALERTAS - AUDITORÍA LABOR VS OFERTA' . '</td></tr>';
            echo '<tr><td colspan="28" style="background:#eff6ff;">'
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
                'Código Oferta',
                'Materia Oferta',
                'Programa Labor',
                'Programa Oferta',
                'Coincidencia programa',
                'Similitud programa',
                'Motivo programa',
                'Grupo',
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
                $esPECero = $peValor !== '' && is_numeric($peValor) && (float)$peValor === 0;
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
                $celda($codigoOferta);
                $celda($materiaOferta);

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

                $celda($r['grupo']);
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

                echo '</tr>';
            }

            echo '</table></body></html>';
            exit;
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

    // Recalculamos la auditoría COMPLETA y actual de ese periodo (sin filtros)
    // para tener los datos vigentes (docente, programa, materia, horas, PE,
    // matriculados, cupo, código/materia de oferta, etc.) de cada fila.
    $resultadoActual = construirAuditoria($pdo, ['periodo' => $periodoObs]);
    $indiceActual = [];
    foreach ($resultadoActual['alertas'] as $row) {
        if (empty($row['identificacion']) || empty($row['codigo_materia']) || empty($row['grupo'])) {
            continue;
        }
        $indiceActual[claveObsNormalizada($row['identificacion'], $row['codigo_materia'], $row['grupo'])] = $row;
    }


$tabla1 = []; // DUPLICIDAD_GRUPO_EXCESO_PE + FALTA_GRUPO_EN_OFERTA
$tabla2 = []; // REVISAR_CODIGO_MATERIA_DIFERENTE
$tabla3 = []; // OFERTA_INEXISTENTE
$tabla4 = []; // LABOR_INEXISTENTE
$gruposDuplicidadProcesados = [];

foreach ($filasObs as $f) {
    $clave = claveObsNormalizada($f['identificacion'], $f['codigo_materia'], $f['grupo']);
    $rowActual = $indiceActual[$clave] ?? null;

    if (!$rowActual) {
        // La fila ya no existe en el periodo vigente (fue borrada del
        // Excel más reciente). No hay datos frescos que reportar; se
        // omite de las tablas del Word (la reconciliación ya la habrá
        // marcado como subsanada en el badge del dashboard).
        continue;
    }

    $estadoActual = $rowActual['estado_alerta'] ?? null;
    $esDuplicidad = ($rowActual['alerta_labor'] ?? null) === 'DUPLICIDAD_GRUPO_EXCESO_PE';

    // ---------------------------------------------------------------
    // TABLA 1.A — Duplicidad de grupo (expande TODOS los docentes del
    // mismo grupo/materia/programa, no solo el que se seleccionó).
    // ---------------------------------------------------------------
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

    // ---------------------------------------------------------------
    // TABLA 1.B — Falta grupo en oferta (fila única, sin expandir).
    // ---------------------------------------------------------------
    if ($estadoActual === 'FALTA_GRUPO_EN_OFERTA') {
        $tabla1[] = [
            'departamento' => $rowActual['departamento_labor'] ?? null,
            'docente' => $rowActual['docente'] ?? null,
            'programa' => $rowActual['programa_labor'] ?? null,
            'materia' => $rowActual['materia_labor'] ?? null,
            'codigo_materia' => $rowActual['codigo_materia'] ?? null,
            'grupo' => $rowActual['grupo'] ?? null,
            'horas_teoricas' => $rowActual['horas_teoricas'] ?? null,
            'pe' => $rowActual['pe'] ?? null,
        ];
    }

    // ---------------------------------------------------------------
    // TABLA 2 — Revisar código de materia diferente.
    // ---------------------------------------------------------------
    if ($estadoActual === 'REVISAR_CODIGO_MATERIA_DIFERENTE') {
        $dc = $rowActual['detalle_codigo_posible_error'] ?? null;
        $tabla2[] = [
            'docente' => $rowActual['docente'] ?? null,
            'programa' => $rowActual['programa_labor'] ?? null,
            'materia_labor' => $rowActual['materia_labor'] ?? null,
            'codigo_materia_labor' => $rowActual['codigo_materia'] ?? null,
            'grupo' => $rowActual['grupo'] ?? null,
            'horas_teoricas' => $rowActual['horas_teoricas'] ?? null,
            'codigo_materia_oferta' => $dc['codigo_materia_oferta'] ?? ($rowActual['codigo_materia_oferta'] ?? null),
            'materia_oferta' => $dc['materia_oferta'] ?? ($rowActual['materia_oferta'] ?? null),
        ];
    }

    // ---------------------------------------------------------------
    // TABLA 3 — Oferta inexistente. OJO: este "if" es independiente
    // del de arriba, por eso una fila con duplicidad Y oferta
    // inexistente ahora cae en AMBAS tablas.
    // ---------------------------------------------------------------
    if ($estadoActual === 'OFERTA_INEXISTENTE') {
        $tabla3[] = [
            'docente' => $rowActual['docente'] ?? null,
            'programa' => $rowActual['programa_labor'] ?? null,
            'materia' => $rowActual['materia_labor'] ?? null,
            'codigo_materia' => $rowActual['codigo_materia'] ?? null,
            'grupo' => $rowActual['grupo'] ?? null,
            'horas_teoricas' => $rowActual['horas_teoricas'] ?? null,
            'pe' => $rowActual['pe'] ?? null,
        ];
    }

    // ---------------------------------------------------------------
    // TABLA 4 — Solo existe en Oferta (sin Labor).
    // ---------------------------------------------------------------
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
}

    $nombreArchivo = 'observacion_' . $idObservacion . '_' .
        preg_replace('/[^A-Za-z0-9_\-]/', '_', $periodoObs) . '.doc';

    header('Content-Type: application/msword; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    header('Cache-Control: max-age=0');

    $celda = function ($v) {
        return '<td style="border:1px solid #999;padding:5px;font-size:11px;">'
             . htmlspecialchars((string)($v ?? '—')) . '</td>';
    };
    $thEstilo = function ($color) {
        return 'border:1px solid #999;padding:6px;background:' . $color . ';color:#fff;font-size:11px;';
    };

    echo "\xEF\xBB\xBF";
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" '
       . 'xmlns:w="urn:schemas-microsoft-com:office:word" '
       . 'xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="UTF-8"></head>'
       . '<body style="font-family:Arial;font-size:12px;">';

    echo '<h2>' . htmlspecialchars($observacion['titulo']) . '</h2>';
    echo '<p><b>Periodo:</b> ' . htmlspecialchars($periodoObs)
       . ' &nbsp; <b>Estado:</b> ' . htmlspecialchars($observacion['estado'])
       . ' &nbsp; <b>Generado:</b> ' . date('Y-m-d H:i') . '</p>';
    if (!empty($observacion['descripcion'])) {
        echo '<p>' . nl2br(htmlspecialchars($observacion['descripcion'])) . '</p>';
    }

    if (!empty($tabla1)) {
        echo '<h3>&#9888;&#65039; Plan de estudios superado</h3>';
        echo '<p>A continuación, se enuncian los cursos a los cuales se les ha asignado más horas '
           . 'de las estipuladas por el plan de estudios, o para los cuales la oferta disponible '
           . 'resulta insuficiente frente a la carga registrada en Labor.</p>';
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
               . $celda($r['horas_teoricas'] ?? null) . $celda($r['pe'] ?? null) . '</tr>';
        }
        echo '</table><br>';
    }

    if (!empty($tabla2)) {
        echo '<h3>&#128269; Diferencia en códigos de labor y oferta</h3>';
        echo '<p>Se detectaron asignaturas con diferencia en los códigos de materia entre lo '
           . 'reportado en Labor y lo registrado en Oferta.</p>';
        echo '<table style="border-collapse:collapse;width:100%;"><tr>'
           . '<th style="' . $thEstilo('#4338ca') . '">APELLIDOS NOMBRES</th>'
           . '<th style="' . $thEstilo('#4338ca') . '">PROGRAMA</th>'
           . '<th style="' . $thEstilo('#4338ca') . '">NOMBRE MATERIA LABOR</th>'
           . '<th style="' . $thEstilo('#4338ca') . '">CÓDIGO MATERIA LABOR</th>'
           . '<th style="' . $thEstilo('#4338ca') . '">GRUPO</th>'
           . '<th style="' . $thEstilo('#4338ca') . '">HORAS TEÓRICAS</th>'
           . '<th style="' . $thEstilo('#4338ca') . '">CÓDIGO MATERIA OFERTA</th>'
           . '<th style="' . $thEstilo('#4338ca') . '">NOMBRE MATERIA OFERTA</th></tr>';
        foreach ($tabla2 as $r) {
            echo '<tr>' . $celda($r['docente'] ?? null) . $celda($r['programa'] ?? null)
               . $celda($r['materia_labor'] ?? null) . $celda($r['codigo_materia_labor'] ?? null)
               . $celda($r['grupo'] ?? null) . $celda($r['horas_teoricas'] ?? null)
               . $celda($r['codigo_materia_oferta'] ?? null) . $celda($r['materia_oferta'] ?? null) . '</tr>';
        }
        echo '</table><br>';
    }

    if (!empty($tabla3)) {
        echo '<h3>&#128683; Oferta inexistente</h3>';
        echo '<p>Estos registros existen en Labor pero NO tienen correspondencia en Oferta.</p>';
        echo '<table style="border-collapse:collapse;width:100%;"><tr>'
           . '<th style="' . $thEstilo('#b91c1c') . '">APELLIDOS NOMBRES</th>'
           . '<th style="' . $thEstilo('#b91c1c') . '">PROGRAMA</th>'
           . '<th style="' . $thEstilo('#b91c1c') . '">NOMBRE MATERIA</th>'
           . '<th style="' . $thEstilo('#b91c1c') . '">CÓDIGO MATERIA</th>'
           . '<th style="' . $thEstilo('#b91c1c') . '">GRUPO</th>'
           . '<th style="' . $thEstilo('#b91c1c') . '">HORAS TEÓRICAS</th>'
           . '<th style="' . $thEstilo('#b91c1c') . '">PE</th></tr>';
        foreach ($tabla3 as $r) {
            echo '<tr>' . $celda($r['docente'] ?? null) . $celda($r['programa'] ?? null)
               . $celda($r['materia'] ?? null) . $celda($r['codigo_materia'] ?? null)
               . $celda($r['grupo'] ?? null) . $celda($r['horas_teoricas'] ?? null)
               . $celda($r['pe'] ?? null) . '</tr>';
        }
        echo '</table><br>';
    }

    if (!empty($tabla4)) {
        echo '<h3>&#128203; Solo en Oferta (sin Labor)</h3>';
        echo '<p>Estos registros existen en Oferta pero NO tienen correspondencia en Labor.</p>';
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
               . $celda($r['grupo'] ?? null) . $celda($r['matriculados'] ?? null)
               . $celda($r['cupo'] ?? null) . '</tr>';
        }
        echo '</table><br>';
    }

    if (empty($tabla1) && empty($tabla2) && empty($tabla3) && empty($tabla4)) {
        echo '<p><i>Todas las filas de esta observación ya fueron subsanadas o no se encontraron '
           . 'en el periodo vigente.</i></p>';
    }

    echo '</body></html>';
    break;
        default:
            echo json_encode([
                'success' => false,
                'error' => 'Acción no válida. Opciones: filtros, alertas, docente_radiografia, docente_periodo, materia_historico, labor_materia_grupo, exportar_excel',
            ]);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}