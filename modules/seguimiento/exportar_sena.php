<?php
require_once __DIR__ . '/../../config/database.php';

$mes_id = $_GET['mes_id'] ?? null;

if (!$mes_id) {
    die("ID de mes no especificado.");
}

// 1. Obtener la información del mes y convocatoria
$stmtMes = $pdo->prepare("
    SELECT m.mes, c.nombre as convocatoria_nombre 
    FROM meses_seguimiento m 
    JOIN convocatorias c ON m.convocatoria_id = c.id 
    WHERE m.id = ?
");
$stmtMes->execute([$mes_id]);
$datos_mes = $stmtMes->fetch(PDO::FETCH_ASSOC);

if (!$datos_mes) {
    die("Mes no encontrado.");
}

// 2. Obtener únicamente los aprendices que CUMPLEN en este mes
$stmtApr = $pdo->prepare("
    SELECT documento, tipo_documento, nombre 
    FROM aprendices_mes 
    WHERE mes_id = ? AND estado_seguimiento = 'Cumple' 
    ORDER BY nombre ASC
");
$stmtApr->execute([$mes_id]);
$aprendices = $stmtApr->fetchAll(PDO::FETCH_ASSOC);

// 3. Buscar tu plantilla exacta en la carpeta
$rutas_posibles = [
    __DIR__ . '/plantilla_sena.xlsx',
    __DIR__ . '/plantilla_sena.xlsx.xlsx',
    __DIR__ . '/../../plantilla_sena.xlsx',
    __DIR__ . '/../../plantilla_sena.xlsx.xlsx',
    __DIR__ . '/../plantilla_sena.xlsx',
    __DIR__ . '/../plantilla_sena.xlsx.xlsx',
    __DIR__ . '/PLANILLA DE PAGO - REGULAR - SEPTIEMBRE.xlsx'
];

$plantilla_path = '';
foreach ($rutas_posibles as $ruta) {
    if (file_exists($ruta)) {
        $plantilla_path = $ruta;
        break;
    }
}

if (empty($plantilla_path)) {
    die("Error: No se encontró el archivo de la plantilla. Verifica que 'plantilla_sena.xlsx' esté en: " . __DIR__);
}

// Función recursiva para limpiar carpeta temporal
function eliminarDirectorioTemporal($dir) {
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            eliminarDirectorioTemporal($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

// 4. Crear entorno temporal de trabajo
$temp_id = uniqid('sena_excel_');
$temp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $temp_id;
$temp_zip = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $temp_id . '.zip';
$temp_xlsx = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $temp_id . '_out.xlsx';

mkdir($temp_dir, 0777, true);
copy($plantilla_path, $temp_zip);

// Extraer el contenido del Excel (usando ZipArchive, PharData o PowerShell nativo de Windows)
$extraido = false;

if (class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    if ($zip->open($temp_zip) === TRUE) {
        $zip->extractTo($temp_dir);
        $zip->close();
        $extraido = true;
    }
}

if (!$extraido && class_exists('PharData')) {
    try {
        $phar = new PharData($temp_zip);
        $phar->extractTo($temp_dir, null, true);
        $extraido = true;
    } catch (Exception $e) {
        $extraido = false;
    }
}

if (!$extraido) {
    // Fallback infalible en Windows con PowerShell
    $cmd_extract = 'powershell -NoProfile -Command "Expand-Archive -Path ' . escapeshellarg($temp_zip) . ' -DestinationPath ' . escapeshellarg($temp_dir) . ' -Force"';
    shell_exec($cmd_extract);
    if (file_exists($temp_dir . '/xl/worksheets/sheet1.xml')) {
        $extraido = true;
    }
}

@unlink($temp_zip);

$sheet1_path = $temp_dir . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . 'worksheets' . DIRECTORY_SEPARATOR . 'sheet1.xml';

if (!$extraido || !file_exists($sheet1_path)) {
    eliminarDirectorioTemporal($temp_dir);
    die("Error: No se pudo descomprimir la plantilla Excel. Para activarlo en XAMPP rápidamente: abre el Panel de Control de XAMPP, haz clic en 'Config' (en la fila de Apache) -> 'PHP (php.ini)', busca ';extension=zip', bórralo dejando 'extension=zip', guarda y reinicia Apache.");
}

// 5. Leer y modificar la hoja principal "Planilla de Pago" (sheet1.xml)
$xml_string = file_get_contents($sheet1_path);

$dom = new DOMDocument();
$dom->preserveWhiteSpace = false;
$dom->loadXML($xml_string);

$xpath = new DOMXPath($dom);
$ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
$xpath->registerNamespace('main', $ns);

// Formatear textos de Fecha de Elaboración (J6) y Periodo de Pago (J7)
$meses_es = [
    '01' => 'ENERO', '02' => 'FEBRERO', '03' => 'MARZO', '04' => 'ABRIL',
    '05' => 'MAYO', '06' => 'JUNIO', '07' => 'JULIO', '08' => 'AGOSTO',
    '09' => 'SEPTIEMBRE', '10' => 'OCTUBRE', '11' => 'NOVIEMBRE', '12' => 'DICIEMBRE'
];
$partes_mes = explode('-', $datos_mes['mes']);
$nombre_mes_pago = isset($partes_mes[1], $meses_es[$partes_mes[1]]) ? $meses_es[$partes_mes[1]] : strtoupper($datos_mes['mes']);
$mes_actual_es = $meses_es[date('m')] ?? strtoupper(date('F'));
$fecha_elaboracion_texto = "FECHA DE ELABORACIÓN " . date('d') . " " . $mes_actual_es . " DEL " . date('Y');
$periodo_pago_texto = "PERIODO DE PAGO: " . $nombre_mes_pago;

// Función auxiliar para escribir texto directo en una celda conservando su estilo exacto (atributo s)
$setCellText = function($cellNode, $text) use ($dom, $ns) {
    if (!$cellNode) return;
    while ($cellNode->firstChild) {
        $cellNode->removeChild($cellNode->firstChild);
    }
    if ($text === '' || $text === null) {
        $cellNode->removeAttribute('t');
        return;
    }
    $cellNode->setAttribute('t', 'inlineStr');
    $is = $dom->createElementNS($ns, 'is');
    $t = $dom->createElementNS($ns, 't');
    $t->appendChild($dom->createTextNode($text));
    $is->appendChild($t);
    $cellNode->appendChild($is);
};

// Función auxiliar para escribir un número en una celda conservando su estilo exacto (atributo s)
$setCellNumber = function($cellNode, $number) use ($dom, $ns) {
    if (!$cellNode) return;
    while ($cellNode->firstChild) {
        $cellNode->removeChild($cellNode->firstChild);
    }
    $cellNode->removeAttribute('t');
    if ($number === '' || $number === null) {
        return;
    }
    $v = $dom->createElementNS($ns, 'v', (string)$number);
    $cellNode->appendChild($v);
};

// Actualizar celdas J6 y J7
$nodoJ6 = $xpath->query("//main:sheetData/main:row[@r='6']/main:c[@r='J6']")->item(0);
if ($nodoJ6) $setCellText($nodoJ6, $fecha_elaboracion_texto);

$nodoJ7 = $xpath->query("//main:sheetData/main:row[@r='7']/main:c[@r='J7']")->item(0);
if ($nodoJ7) $setCellText($nodoJ7, $periodo_pago_texto);

// 6. Guardar en memoria las columnas F a T que ya están en la plantilla (Filas 10 a 60)
// asociadas al documento de cada aprendiz para conservar sus datos bancarios y CRP
$datos_plantilla_por_doc = [];
$columnas_extra = ['F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T'];

for ($r = 10; $r <= 60; $r++) {
    $rowNode = $xpath->query("//main:sheetData/main:row[@r='$r']")->item(0);
    if (!$rowNode) continue;

    $cDoc = $xpath->query("main:c[@r='C$r']/main:v", $rowNode)->item(0);
    $doc_en_plantilla = $cDoc ? trim($cDoc->nodeValue) : '';

    if ($doc_en_plantilla !== '') {
        $clones_extra = [];
        foreach ($columnas_extra as $col) {
            $cellNode = $xpath->query("main:c[@r='{$col}{$r}']", $rowNode)->item(0);
            if ($cellNode) {
                $clones_extra[$col] = $cellNode->cloneNode(true);
            }
        }
        $datos_plantilla_por_doc[$doc_en_plantilla] = $clones_extra;
    }
}

// 7. Rellenar las filas de la plantilla (10 a 60) con los aprendices que CUMPLEN
$total_cumplen = count($aprendices);
$max_filas_plantilla = 51; // De la fila 10 a la 60

for ($i = 0; $i < $max_filas_plantilla; $i++) {
    $r = 10 + $i;
    $rowNode = $xpath->query("//main:sheetData/main:row[@r='$r']")->item(0);
    if (!$rowNode) continue;

    $cellB = $xpath->query("main:c[@r='B$r']", $rowNode)->item(0);
    $cellC = $xpath->query("main:c[@r='C$r']", $rowNode)->item(0);
    $cellD = $xpath->query("main:c[@r='D$r']", $rowNode)->item(0);
    $cellE = $xpath->query("main:c[@r='E$r']", $rowNode)->item(0);

    // Asegurar estilo normal uniforme (sin color rojo de cancelado)
    if ($cellC) $cellC->setAttribute('s', '9');
    if ($cellD) $cellD->setAttribute('s', '9');
    if ($cellE) $cellE->setAttribute('s', '9');

    if ($i < $total_cumplen) {
        $apr = $aprendices[$i];
        $doc_limpio = trim($apr['documento']);

        // B: Consecutivo (1, 2, 3...)
        $setCellNumber($cellB, $i + 1);

        // C: Documento
        if (is_numeric($doc_limpio)) {
            $setCellNumber($cellC, $doc_limpio);
        } else {
            $setCellText($cellC, $doc_limpio);
        }

        // D: Tipo de Documento
        $setCellText($cellD, strtoupper(trim($apr['tipo_documento'])));

        // E: Nombre Completo
        $setCellText($cellE, strtoupper(trim($apr['nombre'])));

        // F a T: Si el aprendiz ya tenía datos bancarios/CRP en la plantilla, reubicarlos en su fila actual
        if (isset($datos_plantilla_por_doc[$doc_limpio])) {
            foreach ($columnas_extra as $col) {
                $actualNode = $xpath->query("main:c[@r='{$col}{$r}']", $rowNode)->item(0);
                if ($actualNode && isset($datos_plantilla_por_doc[$doc_limpio][$col])) {
                    $nuevoNode = $datos_plantilla_por_doc[$doc_limpio][$col]->cloneNode(true);
                    $nuevoNode->setAttribute('r', $col . $r);
                    $rowNode->replaceChild($nuevoNode, $actualNode);
                }
            }
        }
    } else {
        // Limpiar filas sobrantes dejando intactos los bordes y el formato
        $setCellNumber($cellB, '');
        $setCellNumber($cellC, '');
        $setCellText($cellD, '');
        $setCellText($cellE, '');

        foreach ($columnas_extra as $col) {
            $extraNode = $xpath->query("main:c[@r='{$col}{$r}']", $rowNode)->item(0);
            if ($extraNode) {
                $setCellNumber($extraNode, '');
            }
        }
    }
}

// Guardar el XML modificado
file_put_contents($sheet1_path, $dom->saveXML());

// Eliminar calcChain.xml para que Excel recalcule las fórmulas de suma (Fila 61) al abrirlo
$calc_chain_path = $temp_dir . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . 'calcChain.xml';
if (file_exists($calc_chain_path)) {
    @unlink($calc_chain_path);
}

// 8. Reempaquetar todo nuevamente como archivo .xlsx
$comprimido = false;

if (class_exists('ZipArchive')) {
    $zipOut = new ZipArchive();
    if ($zipOut->open($temp_xlsx, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($temp_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            $relativePath = str_replace('\\', '/', substr($filePath, strlen($temp_dir) + 1));
            $zipOut->addFile($filePath, $relativePath);
        }
        $zipOut->close();
        $comprimido = true;
    }
}

if (!$comprimido) {
    // Usar compresor nativo de Windows (.NET ZipFile) vía PowerShell para preservar rutas internas exactas de Excel
    $ps_script = "Add-Type -AssemblyName System.IO.Compression.FileSystem; [System.IO.Compression.ZipFile]::CreateFromDirectory(" . escapeshellarg($temp_dir) . ", " . escapeshellarg($temp_xlsx) . ");";
    shell_exec('powershell -NoProfile -Command "' . $ps_script . '"');
    if (file_exists($temp_xlsx)) {
        $comprimido = true;
    }
}

eliminarDirectorioTemporal($temp_dir);

if (!$comprimido || !file_exists($temp_xlsx)) {
    die("Error al generar el contenedor final de Excel.");
}

// 9. Enviar el archivo Excel exacto para descarga
$nombre_descarga = "PLANILLA_DE_PAGO_REGULAR_" . str_replace(' ', '_', strtoupper($datos_mes['mes'])) . ".xlsx";

if (ob_get_length()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $nombre_descarga . '"');
header('Content-Length: ' . filesize($temp_xlsx));
header('Cache-Control: max-age=0');
header('Pragma: public');

readfile($temp_xlsx);
@unlink($temp_xlsx);
exit;