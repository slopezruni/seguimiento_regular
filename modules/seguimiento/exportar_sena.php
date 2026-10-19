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

// =========================================================================
// MOTOR NATIVO EN PHP PURO PARA CREAR ARCHIVOS .XLSX (ZIP) SIN EXTENSIONES
// =========================================================================
function crearXlsxNativo($dir_origen, $archivo_destino) {
    $fp = fopen($archivo_destino, 'wb');
    if (!$fp) return false;

    $central_dir = '';
    $offset = 0;
    $num_entries = 0;

    $dir_origen = rtrim(realpath($dir_origen), DIRECTORY_SEPARATOR);

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir_origen, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $file) {
        $filePath = $file->getRealPath();
        // Excel exige barras inclinadas '/' dentro del contenedor ZIP
        $relativePath = str_replace('\\', '/', substr($filePath, strlen($dir_origen) + 1));

        $content = file_get_contents($filePath);
        $unc_len = strlen($content);
        $crc = crc32($content);
        
        // Comprimir con zlib nativo de PHP
        $zdata = gzdeflate($content, 6);
        $c_len = strlen($zdata);
        $fn_len = strlen($relativePath);

        // Cabecera de archivo local (Local File Header)
        $local_header = "\x50\x4b\x03\x04"
            . pack('v', 20)
            . pack('v', 0)
            . pack('v', 8)
            . pack('v', 0)
            . pack('v', 33)
            . pack('V', $crc)
            . pack('V', $c_len)
            . pack('V', $unc_len)
            . pack('v', $fn_len)
            . pack('v', 0)
            . $relativePath;

        fwrite($fp, $local_header);
        fwrite($fp, $zdata);

        // Registro del Directorio Central (Central Directory Header)
        $central_dir .= "\x50\x4b\x01\x02"
            . pack('v', 20)
            . pack('v', 20)
            . pack('v', 0)
            . pack('v', 8)
            . pack('v', 0)
            . pack('v', 33)
            . pack('V', $crc)
            . pack('V', $c_len)
            . pack('V', $unc_len)
            . pack('v', $fn_len)
            . pack('v', 0)
            . pack('v', 0)
            . pack('v', 0)
            . pack('v', 0)
            . pack('V', 32)
            . pack('V', $offset)
            . $relativePath;

        $offset += strlen($local_header) + $c_len;
        $num_entries++;
    }

    // Fin del Directorio Central (End of Central Directory Record)
    $eocd = "\x50\x4b\x05\x06"
        . pack('v', 0)
        . pack('v', 0)
        . pack('v', $num_entries)
        . pack('v', $num_entries)
        . pack('V', strlen($central_dir))
        . pack('V', $offset)
        . pack('v', 0);

    fwrite($fp, $central_dir);
    fwrite($fp, $eocd);
    fclose($fp);

    return file_exists($archivo_destino) && filesize($archivo_destino) > 0;
}

// 4. Crear entorno temporal de trabajo
$temp_id = uniqid('sena_excel_');
$temp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $temp_id;
$temp_zip = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $temp_id . '.zip';
$temp_xlsx = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $temp_id . '_out.xlsx';

mkdir($temp_dir, 0777, true);
copy($plantilla_path, $temp_zip);

// Extraer el contenido del Excel usando PharData o ZipArchive
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

@unlink($temp_zip);

$sheet1_path = $temp_dir . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . 'worksheets' . DIRECTORY_SEPARATOR . 'sheet1.xml';

if (!$extraido || !file_exists($sheet1_path)) {
    eliminarDirectorioTemporal($temp_dir);
    die("Error: No se pudo descomprimir la plantilla Excel.");
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

// Función auxiliar para escribir una FÓRMULA con su resultado calculado actualizado
$setCellFormula = function($cellNode, $formula, $calculatedValue) use ($dom, $ns) {
    if (!$cellNode) return;
    while ($cellNode->firstChild) {
        $cellNode->removeChild($cellNode->firstChild);
    }
    $cellNode->removeAttribute('t');
    $f = $dom->createElementNS($ns, 'f', $formula);
    $v = $dom->createElementNS($ns, 'v', (string)$calculatedValue);
    $cellNode->appendChild($f);
    $cellNode->appendChild($v);
};

// Actualizar celdas J6 y J7
$nodoJ6 = $xpath->query("//main:sheetData/main:row[@r='6']/main:c[@r='J6']")->item(0);
if ($nodoJ6) $setCellText($nodoJ6, $fecha_elaboracion_texto);

$nodoJ7 = $xpath->query("//main:sheetData/main:row[@r='7']/main:c[@r='J7']")->item(0);
if ($nodoJ7) $setCellText($nodoJ7, $periodo_pago_texto);

// 6. Rellenar las filas de la plantilla (10 a 60) con los aprendices que CUMPLEN
$total_cumplen = count($aprendices);
$max_filas_plantilla = 51; // De la fila 10 a la 60

// Capturar valores base de la fila 10 de la plantilla para VR. (Q10) y NETO A PAGAR (S10)
$nodoQ10 = $xpath->query("//main:sheetData/main:row[@r='10']/main:c[@r='Q10']/main:v")->item(0);
$valor_vr_base = $nodoQ10 ? (float)trim($nodoQ10->nodeValue) : 875453;

$nodoS10 = $xpath->query("//main:sheetData/main:row[@r='10']/main:c[@r='S10']/main:v")->item(0);
$valor_neto_base = $nodoS10 ? (float)trim($nodoS10->nodeValue) : 875453;

$suma_vr = 0;
$suma_neto = 0;

for ($i = 0; $i < $max_filas_plantilla; $i++) {
    $r = 10 + $i;
    $rowNode = $xpath->query("//main:sheetData/main:row[@r='$r']")->item(0);
    if (!$rowNode) continue;

    $cellB = $xpath->query("main:c[@r='B$r']", $rowNode)->item(0);
    $cellC = $xpath->query("main:c[@r='C$r']", $rowNode)->item(0);
    $cellD = $xpath->query("main:c[@r='D$r']", $rowNode)->item(0);
    $cellE = $xpath->query("main:c[@r='E$r']", $rowNode)->item(0);
    $cellF = $xpath->query("main:c[@r='F$r']", $rowNode)->item(0);
    $cellG = $xpath->query("main:c[@r='G$r']", $rowNode)->item(0);
    $cellH = $xpath->query("main:c[@r='H$r']", $rowNode)->item(0);
    $cellI = $xpath->query("main:c[@r='I$r']", $rowNode)->item(0);
    $cellJ = $xpath->query("main:c[@r='J$r']", $rowNode)->item(0);
    $cellK = $xpath->query("main:c[@r='K$r']", $rowNode)->item(0);
    $cellL = $xpath->query("main:c[@r='L$r']", $rowNode)->item(0);
    $cellM = $xpath->query("main:c[@r='M$r']", $rowNode)->item(0);
    $cellN = $xpath->query("main:c[@r='N$r']", $rowNode)->item(0);
    $cellO = $xpath->query("main:c[@r='O$r']", $rowNode)->item(0);
    $cellP = $xpath->query("main:c[@r='P$r']", $rowNode)->item(0);
    $cellQ = $xpath->query("main:c[@r='Q$r']", $rowNode)->item(0);
    $cellR = $xpath->query("main:c[@r='R$r']", $rowNode)->item(0);
    $cellS = $xpath->query("main:c[@r='S$r']", $rowNode)->item(0);
    $cellT = $xpath->query("main:c[@r='T$r']", $rowNode)->item(0);

    // Asegurar estilo normal uniforme (sin color rojo de cancelado en C ni E)
    if ($cellC) $cellC->setAttribute('s', '9');
    if ($cellD) $cellD->setAttribute('s', '9');
    if ($cellE) $cellE->setAttribute('s', '9');
    // Usar el mismo estilo numérico/moneda en R (Retenc.) para que el 0 se vea con formato moneda ($ -)
    if ($cellR) $cellR->setAttribute('s', '20');

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

        // F: RESOLUCION -> En blanco
        $setCellText($cellF, '');

        // G: CDP -> Se deja el que ya está en la plantilla (no se modifica $cellG)

        // H: CRP -> En blanco
        $setCellNumber($cellH, '');

        // I: No° CUENTAS BANCARIAS -> En blanco
        $setCellText($cellI, '');

        // J: ENTIDAD BANCARIA -> En blanco
        $setCellText($cellJ, '');

        // K: TIPO DE CUENTA BANCARIA -> En blanco
        $setCellText($cellK, '');

        // L: VALOR DEL CRP -> En blanco
        $setCellNumber($cellL, '');

        // M: PAGOS ACUMULADOS -> En blanco
        $setCellNumber($cellM, '');

        // N: ADICIONES -> Todas en 0
        $setCellNumber($cellN, 0);

        // O: CUOTA -> En blanco
        $setCellNumber($cellO, '');

        // P: DIAS -> Todos con 30
        $setCellNumber($cellP, 30);

        // Q: VR. -> El valor que está en la plantilla para todos
        $setCellNumber($cellQ, $valor_vr_base);

        // R: RETENC. -> En 0
        $setCellNumber($cellR, 0);

        // S: NETO A PAGAR -> Como está en la plantilla
        $setCellNumber($cellS, $valor_neto_base);

        // T: SALDO POR PAGAR -> En blanco
        $setCellNumber($cellT, '');

        // Acumular sumas reales de los aprendices activos
        $suma_vr += $valor_vr_base;
        $suma_neto += $valor_neto_base;

    } else {
        // Limpiar por completo las filas sobrantes (cuando hay menos de 51 aprendices que cumplen)
        $setCellNumber($cellB, '');
        $setCellNumber($cellC, '');
        $setCellText($cellD, '');
        $setCellText($cellE, '');
        $setCellText($cellF, '');
        $setCellText($cellG, '');
        $setCellNumber($cellH, '');
        $setCellText($cellI, '');
        $setCellText($cellJ, '');
        $setCellText($cellK, '');
        $setCellNumber($cellL, '');
        $setCellNumber($cellM, '');
        $setCellNumber($cellN, '');
        $setCellNumber($cellO, '');
        $setCellNumber($cellP, '');
        $setCellNumber($cellQ, '');
        $setCellNumber($cellR, '');
        $setCellNumber($cellS, '');
        $setCellNumber($cellT, '');
    }
}

// 7. ACTUALIZAR LAS SUMAS EN LA FILA 61 (TOTALES) CON LOS VALORES REALES ACTUALIZADOS
$row61 = $xpath->query("//main:sheetData/main:row[@r='61']")->item(0);
if ($row61) {
    $cellL61 = $xpath->query("main:c[@r='L61']", $row61)->item(0); // Total Valor del CRP
    $cellM61 = $xpath->query("main:c[@r='M61']", $row61)->item(0); // Total Pagos Acumulados
    $cellN61 = $xpath->query("main:c[@r='N61']", $row61)->item(0); // Total Adiciones
    $cellQ61 = $xpath->query("main:c[@r='Q61']", $row61)->item(0); // Total VR. (Pagos)
    $cellR61 = $xpath->query("main:c[@r='R61']", $row61)->item(0); // Total Retenciones
    $cellS61 = $xpath->query("main:c[@r='S61']", $row61)->item(0); // Total Neto a Pagar
    $cellT61 = $xpath->query("main:c[@r='T61']", $row61)->item(0); // Total Saldo por Pagar

    // Aplicar estilo de total en moneda con negrita (s=21) a N61, Q61 y R61 para que luzcan igual a S61
    if ($cellN61) $cellN61->setAttribute('s', '21');
    if ($cellQ61) $cellQ61->setAttribute('s', '21');
    if ($cellR61) $cellR61->setAttribute('s', '21');

    // Asignar fórmulas SUM(...) y sus resultados actualizados exactos
    if ($cellL61) $setCellFormula($cellL61, 'SUM(L10:L60)', 0);
    if ($cellM61) $setCellFormula($cellM61, 'SUM(M10:M60)', 0);
    if ($cellN61) $setCellFormula($cellN61, 'SUM(N10:N60)', 0);
    if ($cellQ61) $setCellFormula($cellQ61, 'SUM(Q10:Q60)', $suma_vr);
    if ($cellR61) $setCellFormula($cellR61, 'SUM(R10:R60)', 0);
    if ($cellS61) $setCellFormula($cellS61, 'SUM(S10:S60)', $suma_neto);
    if ($cellT61) $setCellFormula($cellT61, 'SUM(T10:T60)', 0);
}

// Guardar el XML modificado
file_put_contents($sheet1_path, $dom->saveXML());

// Eliminar calcChain.xml para que Excel no tenga conflicto con las fórmulas actualizadas
$calc_chain_path = $temp_dir . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . 'calcChain.xml';
if (file_exists($calc_chain_path)) {
    @unlink($calc_chain_path);
}

// 8. Reempaquetar todo nuevamente como archivo .xlsx usando nuestro motor nativo en PHP puro
$comprimido = crearXlsxNativo($temp_dir, $temp_xlsx);

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