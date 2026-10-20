<?php
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Obtener todas las convocatorias para el selector
$stmtConvs = $pdo->query("SELECT id, nombre FROM convocatorias ORDER BY id DESC");
$convocatorias = $stmtConvs->fetchAll(PDO::FETCH_ASSOC);

$convocatoria_id = $_GET['convocatoria_id'] ?? ($convocatorias[0]['id'] ?? null);
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
            (convocatoria_id, tipo_documento, documento, nombre, crp, no_cuentas_bancarias, entidad_bancaria, tipo_cuenta_bancaria, valor_crp, pagos_acumulados, cuota) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            tipo_documento = VALUES(tipo_documento),
            nombre = VALUES(nombre),
            crp = VALUES(crp),
            no_cuentas_bancarias = VALUES(no_cuentas_bancarias),
            entidad_bancaria = VALUES(entidad_bancaria),
            tipo_cuenta_bancaria = VALUES(tipo_cuenta_bancaria),
            valor_crp = VALUES(valor_crp),
            pagos_acumulados = VALUES(pagos_acumulados),
            cuota = VALUES(cuota)
        ");

        foreach ($lineas as $index => $linea) {
            if ($index === 0) continue; // Saltar cabecera
            
            $separador = strpos($linea, ';') !== false ? ';' : ',';
            $datos = str_getcsv($linea, $separador);
            
            if (count($datos) >= 10) {
                $tipo_doc = trim(str_replace(['"', "'"], '', $datos[0]));
                $documento = trim(str_replace(['"', "'"], '', $datos[1]));
                $nombre = trim(str_replace(['"', "'"], '', $datos[2]));
                $crp = trim(str_replace(['"', "'"], '', $datos[3]));
                $cuenta = trim(str_replace(['"', "'"], '', $datos[4]));
                $entidad = trim(str_replace(['"', "'"], '', $datos[5]));
                $tipo_cuenta = trim(str_replace(['"', "'"], '', $datos[6]));
                $val_crp = floatval(str_replace(['$', '.', ','], ['', '', '.'], $datos[7]));
                $pagos_acum = floatval(str_replace(['$', '.', ','], ['', '', '.'], $datos[8]));
                $cuota = floatval(str_replace(['$', '.', ','], ['', '', '.'], $datos[9]));

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
                        $cuota
                    ]);
                    $registrados++;
                }
            }
        }
        $mensaje = "<div class='alert alert-success fw-bold'>¡Carga exitosa! Se actualizaron los datos financieros de $registrados aprendices para esta convocatoria.</div>";
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
            <h2><i class="bi bi-bank2 text-primary"></i> Módulo Ciberbanco por Convocatoria</h2>
            <p class="text-muted mb-0">Selecciona la convocatoria a la que deseas cargar o revisar la base de datos bancaria y financiera.</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary fw-bold">
            <i class="bi bi-arrow-left"></i> Volver al Inicio
        </a>
    </div>

    <?= $mensaje ?>

    <!-- Selector de Convocatoria -->
    <div class="card shadow-sm mb-4 border-0 bg-light">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-md-4">
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
                    Sube tu archivo CSV con el siguiente orden estricto de 10 columnas (la primera fila es la cabecera):<br>
                    <b>Tipo Doc ; Documento ; Nombre ; CRP ; N° Cuenta ; Entidad Bancaria ; Tipo Cuenta ; Valor CRP ; Pagos Acumulados ; Cuota</b>
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
                                <th class="text-end">Cuota</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($registros) === 0): ?>
                                <tr><td colspan="10" class="text-center py-4 text-muted">No hay datos bancarios cargados para esta convocatoria todavía.</td></tr>
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
                                    <td class="text-end fw-bold text-primary"><?= !empty($reg['cuota']) ? $reg['cuota'] : '-' ?></td>
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