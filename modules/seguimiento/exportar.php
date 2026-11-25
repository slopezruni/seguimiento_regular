<?php
require_once '../../config/database.php';

$mes_id = $_GET['mes_id'] ?? null;
$tipo = $_GET['tipo'] ?? 'todos'; // 'todos', 'cumple', 'cancelados'

if (!$mes_id) {
    die("ID de mes no especificado.");
}

// 1. Obtener la información del mes, convocatoria y tipo de apoyo
$stmtMes = $pdo->prepare("
    SELECT m.mes, c.nombre AS convocatoria_nombre, c.apoyo_id, t.nombre AS apoyo_nombre
    FROM meses_seguimiento m 
    JOIN convocatorias c ON m.convocatoria_id = c.id 
    LEFT JOIN tipos_apoyo t ON c.apoyo_id = t.id
    WHERE m.id = ?
");
$stmtMes->execute([$mes_id]);
$datos_mes = $stmtMes->fetch(PDO::FETCH_ASSOC);

if (!$datos_mes) {
    die("Mes no encontrado.");
}

$es_transporte = (stripos($datos_mes['apoyo_nombre'] ?? '', 'transporte') !== false);

// 2. Traer los aprendices junto con la fecha de fin de lectiva, validación FIC y cruce Regular
$sql = "
    SELECT a.*, 
           (SELECT f.fecha_fin_lectiva 
            FROM distribucion_fichas f 
            WHERE TRIM(REPLACE(REPLACE(f.numero_ficha, '\r', ''), '\n', '')) = TRIM(REPLACE(REPLACE(a.ficha, '\r', ''), '\n', '')) 
            LIMIT 1) AS fecha_fin_lectiva,
           (CASE WHEN fic.documento IS NOT NULL THEN 'SI' ELSE 'NO' END) AS tiene_fic_global,
           (SELECT CASE WHEN COUNT(*) > 0 THEN 'SI' ELSE 'NO' END 
            FROM aprendices_mes am2 
            JOIN meses_seguimiento ms2 ON am2.mes_id = ms2.id 
            JOIN convocatorias c2 ON ms2.convocatoria_id = c2.id 
            JOIN tipos_apoyo t2 ON c2.apoyo_id = t2.id 
            WHERE TRIM(am2.documento) = TRIM(a.documento) 
            AND t2.nombre LIKE '%Regular%') AS activo_en_regular
    FROM aprendices_mes a 
    LEFT JOIN base_fic fic ON TRIM(a.documento) = TRIM(fic.documento) 
    WHERE a.mes_id = ?
    ORDER BY a.nombre ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$mes_id]);
$todos_aprendices = $stmt->fetchAll(PDO::FETCH_ASSOC);

$hoy = new DateTime();
$hoy->setTime(0, 0, 0);

$stmtSync = $pdo->prepare("UPDATE aprendices_mes SET estado_seguimiento = ? WHERE id = ?");
$aprendices_filtrados = [];

foreach ($todos_aprendices as $a) {
    $estado_calculado = 'Cumple';
    $novedad_texto = 'Sin novedad';
    $motivo_manual_limpio = trim((string)($a['motivo_cancelacion'] ?? ''));
    $tiene_novedad_manual = ($motivo_manual_limpio !== '');

    // Regla de tiempo de 30 días calendario
    if (!empty($a['fecha_fin_lectiva'])) {
        $fin_lectiva = new DateTime($a['fecha_fin_lectiva']);
        $fecha_limite = clone $fin_lectiva;
        $fecha_limite->modify('+30 days');
        $fecha_limite->setTime(0, 0, 0);

        $dias_restantes = (int)$hoy->diff($fecha_limite)->format('%R%a');

        // Solo se cancela si los días restantes son negativos (< 0) Y no tiene etapa productiva
        if ($a['etapa_productiva'] == 'NO' && $dias_restantes < 0) {
            $estado_calculado = 'Cancelado';
            $novedad_texto = 'Fin de lectiva sin E. Productiva';
        }
    }

    // Reglas excluyentes prioritarias (incluyendo novedad manual de cancelación)
    if ($tiene_novedad_manual) {
        $estado_calculado = 'Cancelado';
        $novedad_texto = $motivo_manual_limpio;
    } elseif ($a['tiene_fic_global'] == 'SI') {
        $estado_calculado = 'Cancelado';
        $novedad_texto = 'Cancelado por FIC Automático';
    } elseif ($es_transporte && ($a['activo_en_regular'] ?? 'NO') == 'SI') {
        $estado_calculado = 'Cancelado';
        $novedad_texto = 'Activo en Apoyo Regular';
    } elseif ($a['contrato_aprendizaje'] == 'SI') {
        $estado_calculado = 'Cancelado';
        $novedad_texto = 'Cancelado por Contrato';
    } elseif ($a['vinculo_laboral'] == 'SI') {
        $estado_calculado = 'Cancelado';
        $novedad_texto = 'Cancelado por Vínculo Laboral';
    }

    // Sincronizar en base de datos si estaba desactualizado
    if (trim((string)$a['estado_seguimiento']) !== $estado_calculado) {
        $stmtSync->execute([$estado_calculado, $a['id']]);
        $a['estado_seguimiento'] = $estado_calculado;
    }

    $a['novedad_reporte'] = $novedad_texto;

    // Aplicar filtro según el botón presionado
    if ($tipo === 'cumple' && $estado_calculado === 'Cumple') {
        $aprendices_filtrados[] = $a;
    } elseif ($tipo === 'cancelados' && $estado_calculado === 'Cancelado') {
        $aprendices_filtrados[] = $a;
    } elseif ($tipo === 'todos') {
        $aprendices_filtrados[] = $a;
    }
}

// 3. Generar la descarga en formato CSV / Excel
$nombre_archivo = "REPORTE_" . strtoupper($tipo) . "_" . str_replace(' ', '_', $datos_mes['mes']) . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');

$output = fopen('php://output', 'w');
// Agregar BOM para que Excel en Windows reconozca tildes y caracteres especiales
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Cabeceras
fputcsv($output, ['#', 'Tipo Doc', 'Documento', 'Nombre Completo', 'Ficha', 'Fin Lectiva', 'FIC', 'Contrato', 'V. Laboral', 'E. Productiva', 'Estado Final', 'Motivo / Novedad'], ';');

$i = 1;
foreach ($aprendices_filtrados as $row) {
    fputcsv($output, [
        $i++,
        $row['tipo_documento'],
        $row['documento'],
        $row['nombre'],
        $row['ficha'],
        $row['fecha_fin_lectiva'] ?? 'Sin registro',
        $row['tiene_fic_global'],
        $row['contrato_aprendizaje'],
        $row['vinculo_laboral'],
        $row['etapa_productiva'],
        $row['estado_seguimiento'],
        $row['novedad_reporte']
    ], ';');
}

fclose($output);
exit;