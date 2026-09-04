<?php
// cargar_excel.php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/conexion.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// ------------------------------------------------------------------
// 1. VALIDACIONES INICIALES
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido. Use POST.']);
    exit;
}

$periodo = trim($_POST['periodo'] ?? '');

if (empty($periodo)) {
    echo json_encode(['success' => false, 'message' => 'El período académico es obligatorio.']);
    exit;
}

if (!preg_match('/^\d{4}\.\d$/', $periodo)) {
    echo json_encode(['success' => false, 'message' => 'Formato de período inválido. Use AAAA.N (ej: 2026.2)']);
    exit;
}

if (!isset($_FILES['archivo_excel']) || $_FILES['archivo_excel']['error'] !== UPLOAD_ERR_OK) {
    $err = $_FILES['archivo_excel']['error'] ?? 'No se recibió archivo';
    $msg = match ($err) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo permitido (20MB).',
        UPLOAD_ERR_PARTIAL => 'El archivo se subió incompleto.',
        UPLOAD_ERR_NO_FILE => 'No se seleccionó ningún archivo.',
        default => 'Error al subir el archivo.'
    };
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

// ------------------------------------------------------------------
// 2. LECTURA DEL EXCEL
// ------------------------------------------------------------------
$filePath = $_FILES['archivo_excel']['tmp_name'];

try {
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, true);

    if (count($rows) <= 1) {
        throw new Exception("El archivo Excel está vacío o solo contiene encabezados.");
    }

    // ------------------------------------------------------------------
    // 3. PREPARACIÓN DE SENTENCIAS
    // ------------------------------------------------------------------
    $pdo->beginTransaction();

    // Limpieza previa (destructiva, pero con rollback seguro)
    $pdo->prepare("DELETE FROM labor WHERE periodo = ?")->execute([$periodo]);
    $pdo->prepare("DELETE FROM oferta WHERE periodo = ?")->execute([$periodo]);

    $stmtLab = $pdo->prepare("INSERT INTO labor (
        periodo, facultad, departamento, identificacion, docente, 
        tipo_contrato, dedicacion, programa, materia, codigo_materia, 
        grupo, horas_teoricas, pe
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmtOf = $pdo->prepare("INSERT INTO oferta (
        periodo, facultad, departamento, identificacion, docente, 
        tipo_contrato, dedicacion, programa, materia, codigo_materia, 
        grupo, matriculados, cupo
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $filasLabor = 0;
    $filasOferta = 0;
    $filasOmitidas = 0;

    // ------------------------------------------------------------------
    // 4. PROCESAMIENTO FILA A FILA
    // ------------------------------------------------------------------
    for ($i = 2; $i <= count($rows); $i++) {
        $r = $rows[$i];
        
        // Columna P = origen (LABOR / OFERTA)
        $origen = strtoupper(trim($r['P'] ?? ''));

        if (empty($origen)) {
            $filasOmitidas++;
            continue;
        }

        // Normalizar campos comunes
        $facultad = trim($r['B'] ?? '') ?: null;
        $departamento = trim($r['C'] ?? '') ?: null;
        $identificacion = trim($r['D'] ?? '');
        $docente = trim($r['E'] ?? '') ?: null;
        $tipoContrato = trim($r['F'] ?? '') ?: null;
        $dedicacion = trim($r['G'] ?? '') ?: null;
        $programa = trim($r['H'] ?? '') ?: null;
        $materia = trim($r['I'] ?? '') ?: null;
        $codigoMateria = trim($r['J'] ?? '');
        $grupo = trim($r['K'] ?? '');

        // Saltar filas sin identificación ni código de materia (basura)
        if (empty($identificacion) && empty($codigoMateria)) {
            $filasOmitidas++;
            continue;
        }

        if ($origen === 'LABOR') {
            $horas = is_numeric($r['L'] ?? null) ? floatval($r['L']) : 0;
            $pe = is_numeric($r['M'] ?? null) ? intval($r['M']) : 0;

            $stmtLab->execute([
                $periodo, $facultad, $departamento, $identificacion, $docente,
                $tipoContrato, $dedicacion, $programa, $materia, $codigoMateria,
                $grupo, $horas, $pe
            ]);
            $filasLabor++;

        } elseif ($origen === 'OFERTA') {
            $matStr = preg_replace('/[^0-9]/', '', strval($r['N'] ?? '0'));
            $cupStr = preg_replace('/[^0-9]/', '', strval($r['O'] ?? '0'));
            $matriculados = is_numeric($matStr) ? intval($matStr) : 0;
            $cupo = is_numeric($cupStr) ? intval($cupStr) : 0;

            $stmtOf->execute([
                $periodo, $facultad, $departamento, $identificacion, $docente,
                $tipoContrato, $dedicacion, $programa, $materia, $codigoMateria,
                $grupo, $matriculados, $cupo
            ]);
            $filasOferta++;

        } else {
            // Origen desconocido
            $filasOmitidas++;
        }
    }

    // ------------------------------------------------------------------
    // 5. REGISTRO EN CATÁLOGO DE PERÍODOS
    // ------------------------------------------------------------------
    $partes = explode('.', $periodo);
    $anio = intval($partes[0]);
    $semestre = intval($partes[1]);
    $orden = ($anio * 10) + $semestre;

    $stmtCat = $pdo->prepare("
        INSERT INTO periodo_catalogo 
            (periodo, anio, semestre, es_activo, orden_cronologico, fecha_carga) 
        VALUES (?, ?, ?, 1, ?, NOW()) 
        ON DUPLICATE KEY UPDATE 
            es_activo = 1, 
            orden_cronologico = VALUES(orden_cronologico),
            fecha_carga = NOW()
    ");
    $stmtCat->execute([$periodo, $anio, $semestre, $orden]);

    $pdo->commit();

    // ------------------------------------------------------------------
    // 6. RESPUESTA (compatible con el modal)
    // ------------------------------------------------------------------
    echo json_encode([
        'success' => true,
        'message' => "Período {$periodo} procesado correctamente.",
        'insertados_labor' => $filasLabor,
        'insertados_oferta' => $filasOferta,
        'filas_omitidas' => $filasOmitidas,
        'periodo' => $periodo
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}