<?php
// Activar visualización de errores por si falta algún archivo o permiso
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/database.php';

$mes_id = $_GET['mes_id'] ?? null;

if (!$mes_id) {
    die("ID de mes no especificado.");
}

// Asegurar que la tabla y la columna saldo_por_pagar existan antes de consultar
try {
    $checkCol = $pdo->query("SHOW COLUMNS FROM datos_bancarios_convocatoria LIKE 'saldo_por_pagar'");
    if ($checkCol && $checkCol->rowCount() == 0) {
        $pdo->exec("ALTER TABLE datos_bancarios_convocatoria ADD COLUMN saldo_por_pagar DECIMAL(15, 2) DEFAULT 0 AFTER cuota");
    }
} catch (PDOException $e) {
    // Continuar
}

// 1. Obtener la información del mes y convocatoria
$stmtMes = $pdo->prepare("
    SELECT m.mes, m.convocatoria_id, c.nombre as convocatoria_nombre 
    FROM meses_seguimiento m 
    JOIN convocatorias c ON m.convocatoria_id = c.id 
    WHERE m.id = ?
");
$stmtMes->execute([$mes_id]);
$datos_mes = $stmtMes->fetch(PDO::FETCH_ASSOC);

if (!$datos_mes) {
    die("Mes no encontrado.");
}

$convocatoria_id = $datos_mes['convocatoria_id'];

// 2. Obtener aprendices que CUMPLEN y hacer CRUCE con los datos bancarios
$stmtApr = $pdo->prepare("
    SELECT am.documento, am.tipo_documento, am.nombre, 
           db.crp, db.entidad_bancaria, db.no_cuentas_bancarias, db.tipo_cuenta_bancaria, 
           db.valor_crp, db.pagos_acumulados, db.cuota, db.saldo_por_pagar
    FROM aprendices_mes am
    LEFT JOIN datos_bancarios_convocatoria db 
           ON db.convocatoria_id = ? 
          AND TRIM(am.documento) COLLATE utf8mb4_general_ci = TRIM(db.documento) COLLATE utf8mb4_general_ci
    WHERE am.mes_id = ? AND am.estado_seguimiento = 'Cumple' 
    ORDER BY am.nombre ASC
");
$stmtApr->execute([$convocatoria_id, $mes_id]);
$aprendices = $stmtApr->fetchAll(PDO::FETCH_ASSOC);

// 3. Buscar plantilla exacta
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
    die("Error: No se encontró el archivo de la plantilla 'plantilla_sena.xlsx'.");
}

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
        $relativePath = str_replace('\\', '/', substr($filePath, strlen($dir_origen) + 1));

        $content = file_get_contents($filePath);
        $unc_len = strlen($content);
        $crc = crc32($content);
        
        $zdata = gzdeflate($content, 6);
        $c_len = strlen($zdata);
        $fn_len = strlen($relativePath);

        $local_header = "\x50\x4b\x03\x04"
            . pack('v', 20) . pack('v', 0) . pack('v', 8) . pack('v', 0) . pack('v', 33)
            . pack('V', $crc) . pack('V', $c_len) . pack('V', $unc_len)
            . pack('v', $fn_len) . pack('v', 0) . $relativePath;

        fwrite($fp, $local_header);
        fwrite($fp, $zdata);

        $central_dir .= "\x50\x4b\x01\x02"
            . pack('v', 20) . pack('v', 20) . pack('v', 0) . pack('v', 8) . pack('v', 0) . pack('v', 33)
            . pack('V', $crc) . pack('V', $c_len) . pack('V', $unc_len)
            . pack('v', $fn_len) . pack('v', 0) . pack('v', 0) . pack('v', 0) . pack('v', 0)
            . pack('V', 32) . pack('V', $offset) . $relativePath;

        $offset += strlen($local_header) + $c_len;
        $num_entries++;
    }

    $eocd = "\x50\x4b\x05\x06"
        . pack('v', 0) . pack('v', 0) . pack('v', $num_entries) . pack('v', $num_entries)
        . pack('V', strlen($central_dir)) . pack('V', $offset) . pack('v', 0);

    fwrite($fp, $central_dir);
    fwrite($fp, $eocd);
    fclose($fp);

    return file_exists($archivo_destino) && filesize($archivo_destino) > 0;
}

$temp_id = uniqid('sena_excel_');
$temp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $temp_id;
$temp_zip = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $temp_id . '.zip';
$temp_xlsx = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $temp_id . '_out.xlsx';

mkdir($temp_dir, 0777, true);
copy($plantilla_path, $temp_zip);

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
    } catch (Exception $e) { $extraido = false; }
}
@unlink($temp_zip);

$sheet1_path = $temp_dir . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . 'worksheets' . DIRECTORY_SEPARATOR . 'sheet1.xml';
if (!$extraido || !file_exists($sheet1_path)) {
    eliminarDirectorioTemporal($temp_dir);
    die("Error: No se pudo descomprimir la plantilla Excel.");
}

$xml_string = file_get_contents($sheet1_path);
$dom = new DOMDocument();
$dom->preserveWhiteSpace = false;
$dom->loadXML($xml_string);

$xpath = new DOMXPath($dom);
$ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
$xpath->registerNamespace('main', $ns);

$meses_es = ['01' => 'ENERO', '02' => 'FEBRERO', '03' => 'MARZO', '04' => 'ABRIL', '05' => 'MAYO', '06' => 'JUNIO', '07' => 'JULIO', '08' => 'AGOSTO', '09' => 'SEPTIEMBRE', '10' => 'OCTUBRE', '11' => 'NOVIEMBRE', '12' => 'DICIEMBRE'];
$partes_mes = explode('-', $datos_mes['mes']);
$nombre_mes_pago = $meses_es[$partes_mes[1]] ?? strtoupper($datos_mes['mes']);
$mes_actual_es = $meses_es[date('m')] ?? strtoupper(date('F'));

$setCellText = function($cellNode, $text) use ($dom, $ns) {
    if (!$cellNode) return;
    while ($cellNode->firstChild) $cellNode->removeChild($cellNode->firstChild);
    if ($text === '' || $text === null) { $cellNode->removeAttribute('t'); return; }
    $cellNode->setAttribute('t', 'inlineStr');
    $is = $dom->createElementNS($ns, 'is');
    $t = $dom->createElementNS($ns, 't');
    $t->appendChild($dom->createTextNode((string)$text));
    $is->appendChild($t);
    $cellNode->appendChild($is);
};

$setCellNumber = function($cellNode, $number) use ($dom, $ns) {
    if (!$cellNode) return;
    while ($cellNode->firstChild) $cellNode->removeChild($cellNode->firstChild);
    $cellNode->removeAttribute('t');
    if ($number === '' || $number === null) return;
    $v = $dom->createElementNS($ns, 'v', (string)$number);
    $cellNode->appendChild($v);
};

$setCellFormula = function($cellNode, $formula, $calculatedValue) use ($dom, $ns) {
    if (!$cellNode) return;
    while ($cellNode->firstChild) $cellNode->removeChild($cellNode->firstChild);
    $cellNode->removeAttribute('t');
    $f = $dom->createElementNS($ns, 'f', $formula);
    $v = $dom->createElementNS($ns, 'v', (string)$calculatedValue);
    $cellNode->appendChild($f);
    $cellNode->appendChild($v);
};

$nodoJ6 = $xpath->query("//main:sheetData/main:row[@r='6']/main:c[@r='J6']")->item(0);
if ($nodoJ6) $setCellText($nodoJ6, "FECHA DE ELABORACIÓN " . date('d') . " " . $mes_actual_es . " DEL " . date('Y'));

$nodoJ7 = $xpath->query("//main:sheetData/main:row[@r='7']/main:c[@r='J7']")->item(0);
if ($nodoJ7) $setCellText($nodoJ7, "PERIODO DE PAGO: " . $nombre_mes_pago);

$sheetData = $xpath->query("//main:sheetData")->item(0);
$total_cumplen = count($aprendices);
$cantidad_filas_datos = max(1, $total_cumplen);

$nodoQ10_orig = $xpath->query("//main:sheetData/main:row[@r='10']/main:c[@r='Q10']/main:v")->item(0);
$val_pago_def = $nodoQ10_orig ? (float)trim($nodoQ10_orig->nodeValue) : 875453;

$row10_molde = $xpath->query("//main:sheetData/main:row[@r='10']")->item(0)->cloneNode(true);
$row61_original = $xpath->query("//main:sheetData/main:row[@r='61']")->item(0);

for ($r = 10; $r <= 60; $r++) {
    $rNode = $xpath->query("//main:sheetData/main:row[@r='$r']")->item(0);
    if ($rNode) $sheetData->removeChild($rNode);
}

$suma_vr = 0;
$suma_neto = 0;
$suma_crp = 0;
$suma_pagos_acum = 0;
$suma_saldo_pagar = 0;

for ($i = 0; $i < $cantidad_filas_datos; $i++) {
    $r = 10 + $i;
    $nuevoRow = $row10_molde->cloneNode(true);
    $nuevoRow->setAttribute('r', (string)$r);

    foreach ($nuevoRow->childNodes as $cell) {
        if ($cell->nodeType === XML_ELEMENT_NODE && $cell->localName === 'c') {
            $oldR = $cell->getAttribute('r');
            $colLetter = preg_replace('/[0-9]+/', '', $oldR);
            $cell->setAttribute('r', $colLetter . $r);
        }
    }
    $sheetData->insertBefore($nuevoRow, $row61_original);

    $cellB = $xpath->query("main:c[@r='B$r']", $nuevoRow)->item(0);
    $cellC = $xpath->query("main:c[@r='C$r']", $nuevoRow)->item(0);
    $cellD = $xpath->query("main:c[@r='D$r']", $nuevoRow)->item(0);
    $cellE = $xpath->query("main:c[@r='E$r']", $nuevoRow)->item(0);
    $cellF = $xpath->query("main:c[@r='F$r']", $nuevoRow)->item(0);
    $cellH = $xpath->query("main:c[@r='H$r']", $nuevoRow)->item(0);
    $cellI = $xpath->query("main:c[@r='I$r']", $nuevoRow)->item(0);
    $cellJ = $xpath->query("main:c[@r='J$r']", $nuevoRow)->item(0);
    $cellK = $xpath->query("main:c[@r='K$r']", $nuevoRow)->item(0);
    $cellL = $xpath->query("main:c[@r='L$r']", $nuevoRow)->item(0);
    $cellM = $xpath->query("main:c[@r='M$r']", $nuevoRow)->item(0);
    $cellN = $xpath->query("main:c[@r='N$r']", $nuevoRow)->item(0);
    $cellO = $xpath->query("main:c[@r='O$r']", $nuevoRow)->item(0);
    $cellP = $xpath->query("main:c[@r='P$r']", $nuevoRow)->item(0);
    $cellQ = $xpath->query("main:c[@r='Q$r']", $nuevoRow)->item(0);
    $cellR = $xpath->query("main:c[@r='R$r']", $nuevoRow)->item(0);
    $cellS = $xpath->query("main:c[@r='S$r']", $nuevoRow)->item(0);
    $cellT = $xpath->query("main:c[@r='T$r']", $nuevoRow)->item(0);

    if ($cellC) $cellC->setAttribute('s', '9');
    if ($cellD) $cellD->setAttribute('s', '9');
    if ($cellE) $cellE->setAttribute('s', '9');
    if ($cellR) $cellR->setAttribute('s', '20');

    if ($i < $total_cumplen) {
        $apr = $aprendices[$i];
        $doc_limpio = trim($apr['documento']);

        $crp_val = $apr['crp'] ?? '';
        $cuenta_val = $apr['no_cuentas_bancarias'] ?? '';
        $banco_val = $apr['entidad_bancaria'] ?? '';
        $tipo_cuenta_val = $apr['tipo_cuenta_bancaria'] ?? '';
        $val_crp_num = !empty($apr['valor_crp']) ? (float)$apr['valor_crp'] : 0;
        $pagos_acum_num = !empty($apr['pagos_acumulados']) ? (float)$apr['pagos_acumulados'] : 0;
        
        // Cuota como número entero limpio (ej: "3")
        $cuota_val = ($apr['cuota'] !== null && $apr['cuota'] !== '') ? (int)$apr['cuota'] : '';
        
        $saldo_pagar_num = !empty($apr['saldo_por_pagar']) ? (float)$apr['saldo_por_pagar'] : 0;

        $setCellNumber($cellB, $i + 1);
        if (is_numeric($doc_limpio)) {
            $setCellNumber($cellC, $doc_limpio);
        } else {
            $setCellText($cellC, $doc_limpio);
        }
        $setCellText($cellD, strtoupper(trim($apr['tipo_documento'])));
        $setCellText($cellE, strtoupper(trim($apr['nombre'])));

        $setCellText($cellF, '');
        
        // H: CRP
        if (is_numeric($crp_val)) {
            $setCellNumber($cellH, $crp_val);
        } else {
            $setCellText($cellH, $crp_val);
        }
        
        // I: N° Cuenta (como texto para no perder ceros a la izquierda)
        $setCellText($cellI, $cuenta_val);
        // J: Banco
        $setCellText($cellJ, $banco_val);
        // K: Tipo Cuenta
        $setCellText($cellK, $tipo_cuenta_val);
        // L: Valor CRP
        $setCellNumber($cellL, $val_crp_num);
        // M: Pagos Acumulados
        $setCellNumber($cellM, $pagos_acum_num);
        
        $setCellNumber($cellN, 0); // Adiciones
        
        // O: Cuota (entero limpio sin decimales)
        if ($cuota_val !== '') {
            $setCellNumber($cellO, $cuota_val);
        } else {
            $setCellNumber($cellO, '');
        }
        
        $setCellNumber($cellP, 30); // Días
        
        // Q: Valor del pago
        $setCellNumber($cellQ, $val_pago_def);
        $setCellNumber($cellR, 0); // Retención
        // S: Neto a pagar
        $setCellNumber($cellS, $val_pago_def);
        
        // T: Saldo por pagar
        $setCellNumber($cellT, $saldo_pagar_num);

        $suma_crp += $val_crp_num;
        $suma_pagos_acum += $pagos_acum_num;
        $suma_vr += $val_pago_def;
        $suma_neto += $val_pago_def;
        $suma_saldo_pagar += $saldo_pagar_num;
    } else {
        $setCellNumber($cellB, '');
        $setCellNumber($cellC, '');
        $setCellText($cellD, '');
        $setCellText($cellE, '');
        $setCellText($cellF, '');
        $setCellNumber($cellH, '');
        $setCellText($cellI, '');
        $setCellText($cellJ, '');
        $setCellText($cellK, '');
        $setCellNumber($cellL, '');
        $setCellNumber($cellM, '');
        $setCellNumber($cellN, 0);
        $setCellNumber($cellO, '');
        $setCellNumber($cellP, 0);
        $setCellNumber($cellQ, 0);
        $setCellNumber($cellR, 0);
        $setCellNumber($cellS, 0);
        $setCellNumber($cellT, 0);
    }
}

$fila_totales_nueva = 10 + $cantidad_filas_datos;
$desplazamiento = $fila_totales_nueva - 61;
$ultima_fila_datos = $fila_totales_nueva - 1;

$filas_finales = [];
for ($r_orig = 61; $r_orig <= 70; $r_orig++) {
    $fNode = $xpath->query("//main:sheetData/main:row[@r='$r_orig']")->item(0);
    if ($fNode) $filas_finales[$r_orig] = $fNode;
}

foreach ($filas_finales as $r_orig => $fNode) {
    $r_nuevo = $r_orig + $desplazamiento;
    $fNode->setAttribute('r', (string)$r_nuevo);
    foreach ($fNode->childNodes as $cell) {
        if ($cell->nodeType === XML_ELEMENT_NODE && $cell->localName === 'c') {
            $oldR = $cell->getAttribute('r');
            $colLetter = preg_replace('/[0-9]+/', '', $oldR);
            $cell->setAttribute('r', $colLetter . $r_nuevo);
        }
    }
}

if ($row61_original) {
    $tRow = $fila_totales_nueva;
    $cellL = $xpath->query("main:c[@r='L$tRow']", $row61_original)->item(0);
    $cellM = $xpath->query("main:c[@r='M$tRow']", $row61_original)->item(0);
    $cellN = $xpath->query("main:c[@r='N$tRow']", $row61_original)->item(0);
    $cellQ = $xpath->query("main:c[@r='Q$tRow']", $row61_original)->item(0);
    $cellR = $xpath->query("main:c[@r='R$tRow']", $row61_original)->item(0);
    $cellS = $xpath->query("main:c[@r='S$tRow']", $row61_original)->item(0);
    $cellT = $xpath->query("main:c[@r='T$tRow']", $row61_original)->item(0);

    if ($cellN) $cellN->setAttribute('s', '21');
    if ($cellQ) $cellQ->setAttribute('s', '21');
    if ($cellR) $cellR->setAttribute('s', '21');

    if ($cellL) $setCellFormula($cellL, "SUM(L10:L{$ultima_fila_datos})", $suma_crp);
    if ($cellM) $setCellFormula($cellM, "SUM(M10:M{$ultima_fila_datos})", $suma_pagos_acum);
    if ($cellN) $setCellFormula($cellN, "SUM(N10:N{$ultima_fila_datos})", 0);
    if ($cellQ) $setCellFormula($cellQ, "SUM(Q10:Q{$ultima_fila_datos})", $suma_vr);
    if ($cellR) $setCellFormula($cellR, "SUM(R10:R{$ultima_fila_datos})", 0);
    if ($cellS) $setCellFormula($cellS, "SUM(S10:S{$ultima_fila_datos})", $suma_neto);
    if ($cellT) $setCellFormula($cellT, "SUM(T10:T{$ultima_fila_datos})", $suma_saldo_pagar);
}

if ($desplazamiento !== 0) {
    $mergeNodes = $xpath->query("//main:mergeCells/main:mergeCell");
    foreach ($mergeNodes as $mc) {
        $ref = $mc->getAttribute('ref');
        if (preg_match('/^([A-Z]+)([0-9]+):([A-Z]+)([0-9]+)$/', $ref, $m)) {
            $col1 = $m[1]; $row1 = (int)$m[2];
            $col2 = $m[3]; $row2 = (int)$m[4];
            if ($row1 >= 61) {
                $nr1 = $row1 + $desplazamiento;
                $nr2 = $row2 + $desplazamiento;
                $mc->setAttribute('ref', "{$col1}{$nr1}:{$col2}{$nr2}");
            }
        }
    }

    $dimNode = $xpath->query("//main:dimension")->item(0);
    if ($dimNode) $dimNode->setAttribute('ref', "B1:T" . (70 + $desplazamiento));

    $drawing_path = $temp_dir . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . 'drawings' . DIRECTORY_SEPARATOR . 'drawing1.xml';
    if (file_exists($drawing_path)) {
        $domDraw = new DOMDocument();
        $domDraw->preserveWhiteSpace = false;
        $domDraw->loadXML(file_get_contents($drawing_path));
        $xpathDraw = new DOMXPath($domDraw);
        $xpathDraw->registerNamespace('xdr', 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing');
        foreach ($xpathDraw->query("//xdr:from/xdr:row | //xdr:to/xdr:row") as $rAnchor) {
            $rVal = (int)trim($rAnchor->nodeValue);
            if ($rVal >= 60) $rAnchor->nodeValue = (string)($rVal + $desplazamiento);
        }
        file_put_contents($drawing_path, $domDraw->saveXML());
    }
}

$cfNodes = $xpath->query("//main:conditionalFormatting");
foreach ($cfNodes as $cf) $cf->parentNode->removeChild($cf);

file_put_contents($sheet1_path, $dom->saveXML());

$calc_chain_path = $temp_dir . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . 'calcChain.xml';
if (file_exists($calc_chain_path)) @unlink($calc_chain_path);

$comprimido = crearXlsxNativo($temp_dir, $temp_xlsx);
eliminarDirectorioTemporal($temp_dir);

if (!$comprimido || !file_exists($temp_xlsx)) {
    die("Error al generar el contenedor final de Excel.");
}

$nombre_descarga = "PLANILLA_DE_PAGO_REGULAR_" . str_replace(' ', '_', strtoupper($datos_mes['mes'])) . ".xlsx";
while (ob_get_level()) {
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