<?php
require_once '../../config/database.php';

$mes_id = $_GET['mes_id'] ?? null;
$tipo = $_GET['tipo'] ?? 'todos'; // 'todos', 'cumple', 'cancelados'

if (!$mes_id) {
    die("ID de mes no especificado.");
}

// 1. Obtener la información del mes
$stmtMes = $pdo->prepare("
    SELECT m.mes, c.nombre AS convocatoria_nombre 
    FROM meses_seguimiento m 
    JOIN convocatorias c ON m.convocatoria_id = c.id 
    WHERE m.id = ?
");
$stmtMes->execute([$mes_id]);
$datos_mes = $stmtMes->fetch(PDO::FETCH_ASSOC);

if (!$datos_mes) {
    die("Mes no encontrado.");
}

// 2. Traer los aprendices junto con la fecha de fin de lectiva y validación FIC
$sql = "
    SELECT a.*, 
           (SELECT f.fecha_fin_lectiva 
            FROM distribucion_fichas f 
            WHERE TRIM(REPLACE(REPLACE(f.numero_ficha, '\r', ''), '\n', '')) = TRIM(REPLACE(REPLACE(a.ficha, '\r', ''), '\n', '')) 
            LIMIT 1) AS fecha_fin_lectiva,
           (CASE WHEN fic.documento IS NOT NULL THEN 'SI' ELSE 'NO' END) AS tiene_fic_global 
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

$aprendices_filtrados = [];

foreach ($todos_aprendices as $a) {
    $estado_calculado = 'Cumple';

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
        }
    }

    // Reglas excluyentes prioritarias
    if ($a['tiene_fic_global'] == 'SI' || $a['contrato_aprendizaje'] == 'SI' || $a['vinculo_laboral'] == 'SI') {
        $estado_calculado = 'Cancelado';
    }

    // Sincronizar en base de datos si estaba desactualizado
    if ($a['estado_seguimiento'] !== $estado_calculado) {
        $pdo->prepare("UPDATE aprendices_mes SET estado_seguimiento = ? WHERE id = ?")
            ->execute([$estado_calculado, $a['id']]);
        $a['estado_seguimiento'] = $estado_calculado;
    }

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
fputcsv($output, ['#', 'Tipo Doc', 'Documento', 'Nombre Completo', 'Ficha', 'Fin Lectiva', 'FIC', 'Contrato', 'V. Laboral', 'E. Productiva', 'Estado Final'], ';');

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
        $row['estado_seguimiento']
    ], ';');
}

fclose($output);
exit;