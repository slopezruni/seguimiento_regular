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
    if ($checkCol && $checkCol->rowCount() == 0) {
        $pdo->exec("ALTER TABLE datos_bancarios_convocatoria ADD COLUMN saldo_por_pagar DECIMAL(15, 2) DEFAULT 0 AFTER cuota");
    }
} catch (PDOException $e) {
    // Continuar
}

function limpiarMoneda($valor) {
    if ($valor === null || $valor === '') return 0;
    $val = trim(str_replace(['$', ' ', '"', "'"], '', $valor));
    if (strpos($val, '.') !== false && strpos($val, ',') !== false) {
        if (strrpos($val, ',') > strrpos($val, '.')) {
            $val = str_replace('.', '', $val);
            $val = str_replace(',', '.', $val);
        } else {
            $val = str_replace(',', '', $val);
        }
    } elseif (substr_count($val, '.') > 1) {
        $val = str_replace('.', '', $val);
    } elseif (substr_count($val, ',') > 1) {
        $val = str_replace(',', '', $val);
    } elseif (strpos($val, '.') !== false) {
        $partes = explode('.', $val);
        if (strlen(end($partes)) === 3) {
            $val = str_replace('.', '', $val);
        }
    } elseif (strpos($val, ',') !== false) {
        $partes = explode(',', $val);
        if (strlen(end($partes)) === 3) {
            $val = str_replace(',', '', $val);
        } else {
            $val = str_replace(',', '.', $val);
        }
    }
    return floatval($val);
}

// Obtener convocatorias
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

// 1. ACCIÓN: CREAR REGISTRO MANUAL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_manual'])) {
    $doc = trim(str_replace([' ', '.'], '', $_POST['documento']));
    $tipo = strtoupper(trim($_POST['tipo_documento']));
    $nombre = strtoupper(trim($_POST['nombre']));
    $crp = trim($_POST['crp']);
    $cuenta = trim($_POST['no_cuentas_bancarias']);
    $entidad = trim($_POST['entidad_bancaria']);
    $tipo_cuenta = trim($_POST['tipo_cuenta_bancaria']);
    $valor_crp = limpiarMoneda($_POST['valor_crp']);
    $pagos_acum = limpiarMoneda($_POST['pagos_acumulados']);
    $cuota = !empty($_POST['cuota']) ? (string)intval($_POST['cuota']) : '';
    $saldo_pagar = limpiarMoneda($_POST['saldo_por_pagar']);

    if (!empty($doc) && is_numeric($doc)) {
        try {
            $stmtInsert = $pdo->prepare("
                INSERT INTO datos_bancarios_convocatoria 
                (convocatoria_id, tipo_documento, documento, nombre, crp, no_cuentas_bancarias, entidad_bancaria, tipo_cuenta_bancaria, valor_crp, pagos_acumulados, cuota, saldo_por_pagar) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtInsert->execute([$convocatoria_id, $tipo, $doc, $nombre, $crp, $cuenta, $entidad, $tipo_cuenta, $valor_crp, $pagos_acum, $cuota, $saldo_pagar]);
            $mensaje = "<div class='alert alert-success fw-bold'><i class='bi bi-check-circle-fill'></i> Aprendiz guardado correctamente en el Ciberbanco.</div>";
        } catch (PDOException $e) {
            $mensaje = "<div class='alert alert-warning fw-bold'><i class='bi bi-exclamation-triangle-fill'></i> El documento $doc ya existe en esta convocatoria. Puedes usar el botón 'Editar' en la tabla.</div>";
        }
    } else {
        $mensaje = "<div class='alert alert-danger fw-bold'>El número de documento no es válido.</div>";
    }
}

// 2. ACCIÓN: EDITAR REGISTRO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_registro'])) {
    $reg_id = $_POST['registro_id'];
    $tipo = strtoupper(trim($_POST['tipo_documento']));
    $nombre = strtoupper(trim($_POST['nombre']));
    $crp = trim($_POST['crp']);
    $cuenta = trim($_POST['no_cuentas_bancarias']);
    $entidad = trim($_POST['entidad_bancaria']);
    $tipo_cuenta = trim($_POST['tipo_cuenta_bancaria']);
    $valor_crp = limpiarMoneda($_POST['valor_crp']);
    $pagos_acum = limpiarMoneda($_POST['pagos_acumulados']);
    $cuota = !empty($_POST['cuota']) ? (string)intval($_POST['cuota']) : '';
    $saldo_pagar = limpiarMoneda($_POST['saldo_por_pagar']);

    $stmtUpdate = $pdo->prepare("
        UPDATE datos_bancarios_convocatoria 
        SET tipo_documento = ?, nombre = ?, crp = ?, no_cuentas_bancarias = ?, entidad_bancaria = ?, tipo_cuenta_bancaria = ?, 
            valor_crp = ?, pagos_acumulados = ?, cuota = ?, saldo_por_pagar = ?
        WHERE id = ? AND convocatoria_id = ?
    ");
    $stmtUpdate->execute([$tipo, $nombre, $crp, $cuenta, $entidad, $tipo_cuenta, $valor_crp, $pagos_acum, $cuota, $saldo_pagar, $reg_id, $convocatoria_id]);
    $mensaje = "<div class='alert alert-success fw-bold'><i class='bi bi-check-circle-fill'></i> Datos del aprendiz actualizados con éxito.</div>";
}

// 3. ACCIÓN: CARGA MASIVA CSV
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
            if ($index === 0) continue;
            
            $separador = strpos($linea, ';') !== false ? ';' : ',';
            $datos = str_getcsv($linea, $separador);
            
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
                
                $cuota_raw = trim(str_replace(['"', "'", ','], ['', '', '.'], $datos[9]));
                $cuota = is_numeric($cuota_raw) ? (string)intval(floatval($cuota_raw)) : $cuota_raw;
                
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
        $mensaje = "<div class='alert alert-success fw-bold'><i class='bi bi-cloud-check-fill'></i> Se actualizaron los datos financieros de $registrados aprendices.</div>";
    }
}

// Obtener registros
$registros = [];
if ($convocatoria_id) {
    $stmtList = $pdo->prepare("SELECT * FROM datos_bancarios_convocatoria WHERE convocatoria_id = ? ORDER BY nombre ASC");
    $stmtList->execute([$convocatoria_id]);
    $registros = $stmtList->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2><i class="bi bi-bank2 text-success"></i> Módulo Ciberbanco</h2>
            <p class="text-muted mb-0">Gestión de datos bancarios, cuentas, CRPs y saldos por convocatoria.</p>
        </div>
        <?php if ($convocatoria_id): ?>
        <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#modalNuevo">
            <i class="bi bi-person-plus-fill"></i> Agregar Aprendiz Manual
        </button>
        <?php endif; ?>
    </div>

    <?= $mensaje ?>

    <!-- Selector de Convocatoria y Búsqueda Rápida -->
    <div class="card shadow-sm mb-3 border-0 bg-light">
        <div class="card-body py-2">
            <div class="row g-2 align-items-center">
                <div class="col-md-5">
                    <form method="GET" class="m-0">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white fw-bold">Convocatoria:</span>
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
                <div class="col-md-7">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" id="filtroCiberbanco" class="form-control" placeholder="Buscar aprendiz por documento, nombre o CRP en tiempo real...">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($convocatoria_id): ?>
        <!-- Acordeón para Carga Masiva (Ocultable para no estorbar la vista) -->
        <div class="accordion mb-3" id="accordionCSV">
            <div class="accordion-item shadow-sm border-success">
                <h2 class="accordion-header" id="headingCSV">
                    <button class="accordion-button collapsed py-2 bg-success bg-opacity-10 text-dark fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCSV" aria-expanded="false">
                        <i class="bi bi-file-earmark-spreadsheet-fill me-2"></i> ¿Deseas hacer una carga masiva mediante archivo CSV? (Clic aquí)
                    </button>
                </h2>
                <div id="collapseCSV" class="accordion-collapse collapse" data-bs-parent="#accordionCSV">
                    <div class="accordion-body bg-light">
                        <p class="text-muted small mb-2">
                            Orden requerido de columnas (primera fila cabecera):<br>
                            <b>Tipo Doc ; Documento ; Nombre ; CRP ; N° Cuenta ; Entidad Bancaria ; Tipo Cuenta ; Valor CRP ; Pagos Acumulados ; Cuota ; Saldo Por Pagar</b>
                        </p>
                        <form method="POST" enctype="multipart/form-data" class="row g-2 align-items-center">
                            <input type="hidden" name="convocatoria_id" value="<?= $convocatoria_id ?>">
                            <div class="col-md-6">
                                <input type="file" name="archivo_csv" class="form-control form-control-sm" accept=".csv" required>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" name="subir_banco_convocatoria" class="btn btn-sm btn-success fw-bold w-100">
                                    <i class="bi bi-upload"></i> Subir Base CSV
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla Resumen -->
        <div class="card shadow-sm border-0 shadow-lg">
            <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between align-items-center">
                <span>Registros de <?= htmlspecialchars($convocatoria_nombre) ?> (<span id="conteoRegistros"><?= count($registros) ?></span>)</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 550px; overflow-y: auto;">
                    <table class="table table-striped table-hover mb-0 align-middle" id="tablaCiberbanco" style="font-size: 0.85em;">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Tipo</th>
                                <th>Documento</th>
                                <th>Nombre Completo</th>
                                <th>CRP</th>
                                <th>N° Cuenta</th>
                                <th>Entidad</th>
                                <th>Tipo Cta</th>
                                <th class="text-end">Valor CRP</th>
                                <th class="text-end">Pagos Acum.</th>
                                <th class="text-center">Cuota</th>
                                <th class="text-end">Saldo Pagar</th>
                                <th class="text-center">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyCiberbanco">
                            <?php if (count($registros) === 0): ?>
                                <tr id="filaVacia"><td colspan="12" class="text-center py-4 text-muted">No hay datos bancarios registrados en esta convocatoria.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($registros as $reg): ?>
                                <tr class="fila-ciberbanco" data-busqueda="<?= strtolower(htmlspecialchars($reg['documento'] . ' ' . $reg['nombre'] . ' ' . ($reg['crp'] ?? ''))) ?>">
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
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-primary py-0 px-2 fw-bold btn-editar" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalEditar"
                                                data-id="<?= $reg['id'] ?>"
                                                data-tipo="<?= htmlspecialchars($reg['tipo_documento']) ?>"
                                                data-doc="<?= htmlspecialchars($reg['documento']) ?>"
                                                data-nombre="<?= htmlspecialchars($reg['nombre']) ?>"
                                                data-crp="<?= htmlspecialchars($reg['crp']) ?>"
                                                data-cuenta="<?= htmlspecialchars($reg['no_cuentas_bancarias']) ?>"
                                                data-entidad="<?= htmlspecialchars($reg['entidad_bancaria']) ?>"
                                                data-tipocuenta="<?= htmlspecialchars($reg['tipo_cuenta_bancaria']) ?>"
                                                data-valcrp="<?= $reg['valor_crp'] ?>"
                                                data-pagosacum="<?= $reg['pagos_acumulados'] ?>"
                                                data-cuota="<?= ($reg['cuota'] !== null && $reg['cuota'] !== '') ? intval($reg['cuota']) : '' ?>"
                                                data-saldopagar="<?= $reg['saldo_por_pagar'] ?>">
                                            <i class="bi bi-pencil-square"></i> Editar
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- MODAL 1: AGREGAR APRENDIZ MANUAL -->
        <div class="modal fade" id="modalNuevo" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="POST">
                        <input type="hidden" name="convocatoria_id" value="<?= $convocatoria_id ?>">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill me-2"></i> Agregar Datos Bancarios Manualmente</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Tipo Doc</label>
                                    <input type="text" name="tipo_documento" class="form-control form-control-sm text-uppercase" placeholder="CC / TI" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Número Documento</label>
                                    <input type="number" name="documento" class="form-control form-control-sm" placeholder="Sin puntos" required>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small fw-bold">Nombre Completo</label>
                                    <input type="text" name="nombre" class="form-control form-control-sm text-uppercase" placeholder="Nombres y Apellidos" required>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">CRP</label>
                                    <input type="text" name="crp" class="form-control form-control-sm" placeholder="Ej: 52424">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Número de Cuenta</label>
                                    <input type="text" name="no_cuentas_bancarias" class="form-control form-control-sm" placeholder="N° de Cuenta">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Entidad Bancaria</label>
                                    <input type="text" name="entidad_bancaria" class="form-control form-control-sm" placeholder="Ej: BANCOLOMBIA">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Tipo Cuenta</label>
                                    <input type="text" name="tipo_cuenta_bancaria" class="form-control form-control-sm" placeholder="Ej: AHORROS">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Valor CRP</label>
                                    <input type="number" step="any" name="valor_crp" class="form-control form-control-sm" placeholder="0">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold">Pagos Acum.</label>
                                    <input type="number" step="any" name="pagos_acumulados" class="form-control form-control-sm" placeholder="0">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold">Cuota (Entero)</label>
                                    <input type="number" name="cuota" class="form-control form-control-sm" placeholder="Ej: 3">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold">Saldo Pagar</label>
                                    <input type="number" step="any" name="saldo_por_pagar" class="form-control form-control-sm" placeholder="0">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer py-2">
                            <button type="button" class="btn btn-secondary btn-sm fw-bold" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" name="crear_manual" class="btn btn-primary btn-sm fw-bold"><i class="bi bi-save"></i> Guardar Aprendiz</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL 2: EDITAR APRENDIZ EXISTENTE -->
        <div class="modal fade" id="modalEditar" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="POST">
                        <input type="hidden" name="convocatoria_id" value="<?= $convocatoria_id ?>">
                        <input type="hidden" name="registro_id" id="edit_id">
                        <div class="modal-header bg-dark text-white">
                            <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i> Editar Datos Bancarios del Aprendiz</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Tipo Doc</label>
                                    <input type="text" name="tipo_documento" id="edit_tipo" class="form-control form-control-sm text-uppercase" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Documento (Bloqueado)</label>
                                    <input type="text" id="edit_doc" class="form-control form-control-sm bg-light" readonly>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small fw-bold">Nombre Completo</label>
                                    <input type="text" name="nombre" id="edit_nombre" class="form-control form-control-sm text-uppercase" required>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">CRP</label>
                                    <input type="text" name="crp" id="edit_crp" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Número de Cuenta</label>
                                    <input type="text" name="no_cuentas_bancarias" id="edit_cuenta" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Entidad Bancaria</label>
                                    <input type="text" name="entidad_bancaria" id="edit_entidad" class="form-control form-control-sm">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Tipo Cuenta</label>
                                    <input type="text" name="tipo_cuenta_bancaria" id="edit_tipocuenta" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Valor CRP</label>
                                    <input type="number" step="any" name="valor_crp" id="edit_valcrp" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold">Pagos Acum.</label>
                                    <input type="number" step="any" name="pagos_acumulados" id="edit_pagosacum" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold">Cuota (Entero)</label>
                                    <input type="number" name="cuota" id="edit_cuota" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold">Saldo Pagar</label>
                                    <input type="number" step="any" name="saldo_por_pagar" id="edit_saldopagar" class="form-control form-control-sm">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer py-2">
                            <button type="button" class="btn btn-secondary btn-sm fw-bold" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" name="editar_registro" class="btn btn-success btn-sm fw-bold"><i class="bi bi-check-circle"></i> Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Filtrado instantáneo en la tabla
            const inputFiltro = document.getElementById('filtroCiberbanco');
            const filas = document.querySelectorAll('.fila-ciberbanco');
            const conteo = document.getElementById('conteoRegistros');

            if (inputFiltro) {
                inputFiltro.addEventListener('input', function() {
                    const texto = this.value.toLowerCase().trim();
                    let visibles = 0;

                    filas.forEach(function(fila) {
                        const data = fila.getAttribute('data-busqueda') || '';
                        if (texto === '' || data.includes(texto)) {
                            fila.style.display = '';
                            visibles++;
                        } else {
                            fila.style.display = 'none';
                        }
                    });
                    if (conteo) conteo.textContent = visibles;
                });
            }

            // Inyectar datos al Modal de Editar
            const botonesEditar = document.querySelectorAll('.btn-editar');
            botonesEditar.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    document.getElementById('edit_id').value = this.dataset.id;
                    document.getElementById('edit_tipo').value = this.dataset.tipo;
                    document.getElementById('edit_doc').value = this.dataset.doc;
                    document.getElementById('edit_nombre').value = this.dataset.nombre;
                    document.getElementById('edit_crp').value = this.dataset.crp;
                    document.getElementById('edit_cuenta').value = this.dataset.cuenta;
                    document.getElementById('edit_entidad').value = this.dataset.entidad;
                    document.getElementById('edit_tipocuenta').value = this.dataset.tipocuenta;
                    document.getElementById('edit_valcrp').value = this.dataset.valcrp;
                    document.getElementById('edit_pagosacum').value = this.dataset.pagosacum;
                    document.getElementById('edit_cuota').value = this.dataset.cuota;
                    document.getElementById('edit_saldopagar').value = this.dataset.saldopagar;
                });
            });
        });
        </script>
    <?php endif; ?>
</div>

<?php require_once '../../includes/footer.php'; ?>