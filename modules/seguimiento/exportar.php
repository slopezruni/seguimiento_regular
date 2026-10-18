<?php
require_once '../../config/database.php';

$mes_id = $_GET['mes_id'] ?? null;
$tipo = $_GET['tipo'] ?? 'todos'; // Recibe el filtro: todos, cumple, cancelados

if (!$mes_id) {
    exit('Mes no especificado.');
}

// Consultar el nombre del mes y de la convocatoria
$stmtMeta = $pdo->prepare("
    SELECT m.mes, c.nombre AS convocatoria 
    FROM meses_seguimiento m 
    JOIN convocatorias c ON m.convocatoria_id = c.id 
    WHERE m.id = ?
");
$stmtMeta->execute([$mes_id]);
$meta = $stmtMeta->fetch(PDO::FETCH_ASSOC);

if (!$meta) {
    exit('Seguimiento no encontrado.');
}

// Definir sufijo para el nombre del archivo según el filtro
$sufijo_archivo = "Todos";
if ($tipo === 'cumple') $sufijo_archivo = "Cumplen";
if ($tipo === 'cancelados') $sufijo_archivo = "Cancelados";

$nombre_archivo = "Reporte_" . $sufijo_archivo . "_" . preg_replace('/[^a-zA-Z0-9]/', '_', $meta['convocatoria']) . "_" . $meta['mes'] . ".csv";

// Configurar cabeceras HTTP para forzar la descarga del CSV compatible con Excel
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8 para tildes y ñ

// Cabeceras de columnas
fputcsv($output, [
    'TIPO DOC', 
    'DOCUMENTO', 
    'NOMBRE COMPLETO', 
    'FICHA', 
    'FIN LECTIVA', 
    'APOYO FIC', 
    'CONTRATO APRENDIZAJE', 
    'VÍNCULO LABORAL', 
    'ETAPA PRODUCTIVA', 
    'ESTADO FINAL', 
    'NOVEDAD ENCONTRADA'
], ';');

// Consulta de aprendices
$sqlList = "SELECT a.*, f.fecha_fin_lectiva, (CASE WHEN fic.documento IS NOT NULL THEN 'SI' ELSE 'NO' END) AS tiene_fic_global 
            FROM aprendices_mes a 
            LEFT JOIN distribucion_fichas f ON a.ficha = f.numero_ficha 
            LEFT JOIN base_fic fic ON a.documento = fic.documento 
            WHERE a.mes_id = ? ORDER BY a.nombre ASC";
$stmtList = $pdo->prepare($sqlList);
$stmtList->execute([$mes_id]);
$aprendices = $stmtList->fetchAll(PDO::FETCH_ASSOC);

$hoy = new DateTime();
$hoy->setTime(0,0,0);

foreach ($aprendices as $a) {
    $estado_calculado = 'Cumple';
    $novedad_texto = 'Sin novedad';

    if (!empty($a['fecha_fin_lectiva'])) {
        $fin_lectiva = new DateTime($a['fecha_fin_lectiva']);
        $fecha_limite = clone $fin_lectiva;
        $fecha_limite->modify('+20 days');
        $fecha_limite->setTime(0,0,0);
        
        $dias_restantes = (int)$hoy->diff($fecha_limite)->format('%R%a');

        if ($a['etapa_productiva'] == 'NO') {
            if ($dias_restantes < 0) {
                $estado_calculado = 'Cancelado';
                $novedad_texto = 'Fin de lectiva sin E. Productiva';
            }
        }
    }

    if ($a['tiene_fic_global'] == 'SI' || $a['apoyo_fic'] == 'SI') {
        $estado_calculado = 'Cancelado';
        $novedad_texto = 'Cancelado por FIC';
    } elseif ($a['contrato_aprendizaje'] == 'SI') {
        $estado_calculado = 'Cancelado';
        $novedad_texto = 'Cancelado por Contrato';
    } elseif ($a['vinculo_laboral'] == 'SI') {
        $estado_calculado = 'Cancelado';
        $novedad_texto = 'Cancelado por Vínculo Laboral';
    }

    // APLICAR FILTRO SELECCIONADO
    if ($tipo === 'cumple' && $estado_calculado !== 'Cumple') continue;
    if ($tipo === 'cancelados' && $estado_calculado !== 'Cancelado') continue;

    fputcsv($output, [
        $a['tipo_documento'],
        $a['documento'],
        $a['nombre'],
        $a['ficha'],
        $a['fecha_fin_lectiva'] ?? 'Sin Ficha',
        ($a['tiene_fic_global'] == 'SI' ? 'SI' : $a['apoyo_fic']),
        $a['contrato_aprendizaje'],
        $a['vinculo_laboral'],
        $a['etapa_productiva'],
        $estado_calculado,
        $novedad_texto
    ], ';');
}

fclose($output);
exit;