<?php
require_once '../../config/database.php';
require_once '../../includes/header.php';

// AUTO-VERIFICACIÓN DE LA TABLA Y COLUMNA 'saldo_por_pagar'
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS datos_bancarios_convocatoria (
            id INT AUTO_INCREMENT PRIMARY KEY,
            convocatoria_id INT NOT NULL,
            tipo_documento VARCHAR(10) DEFAULT NULL,
            documento VARCHAR(20) NOT NULL,
            nombre VARCHAR(150) DEFAULT NULL,
            crp VARCHAR(50) DEFAULT NULL,
            no_cuentas_bancarias VARCHAR(50) DEFAULT NULL,
            entidad_bancaria VARCHAR(100) DEFAULT NULL,
            tipo_cuenta_bancaria VARCHAR(50) DEFAULT NULL,
            valor_crp DECIMAL(15, 2) DEFAULT 0,
            pagos_acumulados DECIMAL(15, 2) DEFAULT 0,
            cuota VARCHAR(20) DEFAULT NULL,
            saldo_por_pagar DECIMAL(15, 2) DEFAULT 0,
            UNIQUE KEY (convocatoria_id, documento)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $checkCol = $pdo->query("SHOW COLUMNS FROM datos_bancarios_convocatoria LIKE 'saldo_por_pagar'");
    if ($checkCol->rowCount() == 0) {
        $pdo->exec("ALTER TABLE datos_bancarios_convocatoria ADD COLUMN saldo_por_pagar DECIMAL(15, 2) DEFAULT 0 AFTER cuota");
    }
} catch (PDOException $e) {
    // Continuar si ya existe
}

// Función para limpiar valores de moneda sin romper números enteros ni decimales
function limpiarMoneda($valor) {
    if ($valor === null || $valor === '') return 0;
    // Quitar signo peso, espacios y comillas
    $val = trim(str_replace(['$', ' ', '"', "'"], '', $valor));
    // Si tiene puntos y comas (ej: 1.500.000,00 o 1,500,000.00)
    if (strpos($val, '.') !== false && strpos($val, ',') !== false) {
        if (strrpos($val, ',') > strrpos($val, '.')) {
            // Formato colombiano/europeo: 1.500.000,00
            $val = str_replace('.', '', $val);
            $val = str_replace(',', '.', $val);
        } else {
            // Formato americano: 1,500,000.00
            $val = str_replace(',', '', $val);
        }
    } elseif (substr_count($val, '.') > 1) {
        // Múltiples puntos de miles: 1.500.000
        $val = str_replace('.', '', $val);
    } elseif (substr_count($val, ',') > 1) {
        // Múltiples comas de miles: 1,500,000
        $val = str_replace(',', '', $val);
    } elseif (strpos($val, '.') !== false) {
        // Un solo punto: si después del punto hay 3 dígitos exactos (ej: 875.453), es separador de miles
        $partes = explode('.', $val);
        if (strlen(end($partes)) === 3) {
            $val = str_replace('.', '', $val);
        }
    } elseif (strpos($val, ',') !== false) {
        // Una sola coma: si después hay 3 dígitos exactos, es miles; si hay 1 o 2, es decimal
        $partes = explode(',', $val);
        if (strlen(end($partes)) === 3) {
            $val = str_replace(',', '', $val);
        } else {
            $val = str_replace(',', '.', $val);
        }
    }
    return floatval($val);
}

// Obtener todas las convocatorias para el selector principal
$stmtConvs = $pdo->query("SELECT id, nombre FROM convocatorias ORDER BY id DESC");
$convocatorias = $stmtConvs->fetchAll(PDO::FETCH_ASSOC);

$convocatoria_id = $_GET['convocatoria_id'] ?? ($_POST['convocatoria_id'] ?? ($convocatorias[0]['id'] ?? null));
$convocatoria_nombre = "";

if ($convocatoria_id) {
    $stmtC = $pdo->prepare("SELECT nombre FROM convocatorias WHERE id = ?");
    $stmtC->execute([$convocatoria_id]);
    $convocatoria_nombre = $stmtC->fetchColumn();
}

$mensaje = "";

// Procesar carga masiva de datos bancarios para la convocatoria seleccionada
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_banco_convocatoria'])) {
    if ($convocatoria_id && isset($_FILES['archivo_csv']) && $_FILES['archivo_csv']['error'] == 0) {
        $archivo = $_FILES['archivo_csv']['tmp_name'];
        $lineas = file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $registrados = 0;
        
        $stmtUpsert = $pdo->prepare("
            INSERT INTO datos_bancarios_convocatoria 
            (convocatoria_id, tipo_documento, documento, nombre, crp, no_cuentas_bancarias, entidad_bancaria, tipo_cuenta_bancaria, valor_crp, pagos_acumulados, cuota, saldo_por_pagar) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            tipo_documento = VALUES(tipo_documento),
            nombre = VALUES(nombre),
            crp = VALUES(crp),
            no_cuentas_bancarias = VALUES(no_cuentas_bancarias),
            entidad_bancaria = VALUES(entidad_bancaria),
            tipo_cuenta_bancaria = VALUES(tipo_cuenta_bancaria),
            valor_crp = VALUES(valor_crp),
            pagos_acumulados = VALUES(pagos_acumulados),
            cuota = VALUES(cuota),
            saldo_por_pagar = VALUES(saldo_por_pagar)
        ");

        foreach ($lineas as $index => $linea) {
            if ($index === 0) continue; // Saltar cabecera
            
            $separador = strpos($linea, ';') !== false ? ';' : ',';
            $datos = str_getcsv($linea, $separador);
            
            // Acepta desde 10 columnas (si aún no tenía saldo) o 11 columnas (con saldo por pagar)
            if (count($datos) >= 10) {
                $tipo_doc = trim(str_replace(['"', "'"], '', $datos[0]));
                $documento = trim(str_replace(['"', "'", '.', ' '], '', $datos[1]));
                $nombre = trim(str_replace(['"', "'"], '', $datos[2]));
                $crp = trim(str_replace(['"', "'"], '', $datos[3]));
                $cuenta = trim(str_replace(['"', "'"], '', $datos[4]));
                $entidad = trim(str_replace(['"', "'"], '', $datos[5]));
                $tipo_cuenta = trim(str_replace(['"', "'"], '', $datos[6]));
                $val_crp = limpiarMoneda($datos[7]);
                $pagos_acum = limpiarMoneda($datos[8]);
                
                // Cuota como entero limpio (ej: "3.00" o "3,00" -> 3)
                $cuota_raw = trim(str_replace(['"', "'", ','], ['', '', '.'], $datos[9]));
                $cuota = is_numeric($cuota_raw) ? (string)intval(floatval($cuota_raw)) : $cuota_raw;
                
                // Saldo por pagar (columna 11, índice 10)
                $saldo_pagar = isset($datos[10]) ? limpiarMoneda($datos[10]) : 0;

                if (!empty($documento) && is_numeric($documento)) {
                    $stmtUpsert->execute([
                        $convocatoria_id,
                        strtoupper($tipo_doc),
                        $documento, 
                        strtoupper($nombre),
                        $crp, 
                        $cuenta, 
                        $entidad, 
                        $tipo_cuenta, 
                        $val_crp, 
                        $pagos_acum,
                        $cuota,
                        $saldo_pagar
                    ]);
                    $registrados++;
                }
            }
        }
        $mensaje = "<div class='alert alert-success fw-bold'>¡Carga exitosa! Se actualizaron los datos financieros de $registrados aprendices en esta convocatoria.</div>";
    } else {
        $mensaje = "<div class='alert alert-danger fw-bold'>Selecciona una convocatoria válida y adjunta un archivo CSV.</div>";
    }
}

// Obtener lista de registros de la convocatoria seleccionada
$registros = [];
if ($convocatoria_id) {
    $stmtList = $pdo->prepare("SELECT * FROM datos_bancarios_convocatoria WHERE convocatoria_id = ? ORDER BY nombre ASC");
    $stmtList->execute([$convocatoria_id]);
    $registros = $stmtList->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="bi bi-bank2 text-primary"></i> Módulo Ciberbanco</h2>
            <p class="text-muted mb-0">Gestión de datos bancarios, cuentas, CRPs y saldos por convocatoria.</p>
        </div>
    </div>

    <?= $mensaje ?>

    <!-- Selector de Convocatoria -->
    <div class="card shadow-sm mb-4 border-0 bg-light">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-md-5">
                    <label class="fw-bold mb-1">Seleccionar Convocatoria:</label>
                    <select name="convocatoria_id" class="form-select fw-bold" onchange="this.form.submit()">
                        <?php foreach ($convocatorias as $conv): ?>
                            <option value="<?= $conv['id'] ?>" <?= $conv['id'] == $convocatoria_id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($conv['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <?php if ($convocatoria_id): ?>
        <!-- Tarjeta de Carga Masiva -->
        <div class="card shadow-sm border-success mb-4">
            <div class="card-header bg-success text-white fw-bold"><i class="bi bi-cloud-arrow-up-fill"></i> Carga Masiva de Datos Bancarios para: <?= htmlspecialchars($convocatoria_nombre) ?></div>
            <div class="card-body">
                <p class="text-muted small">
                    Sube tu archivo CSV con el siguiente orden de 11 columnas (la primera fila es la cabecera):<br>
                    <b>Tipo Doc ; Documento ; Nombre ; CRP ; N° Cuenta ; Entidad Bancaria ; Tipo Cuenta ; Valor CRP ; Pagos Acumulados ; Cuota ; Saldo Por Pagar</b>
                </p>
                <form method="POST" enctype="multipart/form-data" class="row g-2 align-items-center">
                    <input type="hidden" name="convocatoria_id" value="<?= $convocatoria_id ?>">
                    <div class="col-md-6">
                        <input type="file" name="archivo_csv" class="form-control" accept=".csv" required>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" name="subir_banco_convocatoria" class="btn btn-success fw-bold w-100"><i class="bi bi-upload"></i> Subir Datos</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla Resumen -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white fw-bold">Registros Bancarios de <?= htmlspecialchars($convocatoria_nombre) ?> (Total: <?= count($registros) ?>)</div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                    <table class="table table-striped table-hover mb-0 align-middle" style="font-size: 0.85em;">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Tipo Doc</th>
                                <th>Documento</th>
                                <th>Nombre Completo</th>
                                <th>CRP</th>
                                <th>N° Cuenta</th>
                                <th>Entidad Bancaria</th>
                                <th>Tipo Cuenta</th>
                                <th class="text-end">Valor CRP</th>
                                <th class="text-end">Pagos Acum.</th>
                                <th class="text-center">Cuota</th>
                                <th class="text-end">Saldo Pagar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($registros) === 0): ?>
                                <tr><td colspan="11" class="text-center py-4 text-muted">No hay datos bancarios cargados para esta convocatoria todavía.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($registros as $reg): ?>
                                <tr>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($reg['tipo_documento']) ?></span></td>
                                    <td class="fw-bold"><?= htmlspecialchars($reg['documento']) ?></td>
                                    <td class="fw-bold text-uppercase"><?= htmlspecialchars($reg['nombre']) ?></td>
                                    <td><?= htmlspecialchars($reg['crp'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($reg['no_cuentas_bancarias'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($reg['entidad_bancaria'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($reg['tipo_cuenta_bancaria'] ?? '-') ?></td>
                                    <td class="text-end fw-bold text-success"><?= !empty($reg['valor_crp']) ? '$'.number_format($reg['valor_crp'], 0, ',', '.') : '-' ?></td>
                                    <td class="text-end fw-bold text-secondary"><?= !empty($reg['pagos_acumulados']) ? '$'.number_format($reg['pagos_acumulados'], 0, ',', '.') : '$0' ?></td>
                                    <td class="text-center fw-bold text-primary"><?= ($reg['cuota'] !== null && $reg['cuota'] !== '') ? intval($reg['cuota']) : '-' ?></td>
                                    <td class="text-end fw-bold text-danger"><?= !empty($reg['saldo_por_pagar']) ? '$'.number_format($reg['saldo_por_pagar'], 0, ',', '.') : '$0' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>