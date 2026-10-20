<?php
require_once '../../config/database.php';
require_once '../../includes/header.php';

$convocatoria_id = $_GET['convocatoria_id'] ?? null;

if (!$convocatoria_id) {
    echo "<div class='alert alert-danger m-4'>Selecciona una convocatoria.</div>";
    require_once '../../includes/footer.php';
    exit;
}

$stmtConv = $pdo->prepare("SELECT nombre FROM convocatorias WHERE id = ?");
$stmtConv->execute([$convocatoria_id]);
$convocatoria_nombre = $stmtConv->fetchColumn();

$mensaje = "";

// Procesar carga masiva mediante CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_banco_csv'])) {
    if (isset($_FILES['archivo_csv']) && $_FILES['archivo_csv']['error'] == 0) {
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
        $mensaje = "<div class='alert alert-success fw-bold'>¡Carga exitosa! Se actualizaron los datos financieros de $registrados aprendices.</div>";
    } else {
        $mensaje = "<div class='alert alert-danger fw-bold'>Error al subir el archivo CSV.</div>";
    }
}

// Obtener lista de aprendices vinculados en la convocatoria con sus datos bancarios
$stmtList = $pdo->prepare("
    SELECT am.documento, am.tipo_documento, am.nombre, am.ficha,
           db.crp, db.entidad_bancaria, db.no_cuentas_bancarias, db.tipo_cuenta_bancaria, db.valor_crp, db.pagos_acumulados, db.cuota
    FROM aprendices_mes am
    JOIN meses_seguimiento ms ON am.mes_id = ms.id
    LEFT JOIN datos_bancarios_convocatoria db ON ms.convocatoria_id = db.convocatoria_id AND TRIM(am.documento) = TRIM(db.documento)
    WHERE ms.convocatoria_id = ?
    GROUP BY am.documento
    ORDER BY am.nombre ASC
");
$stmtList->execute([$convocatoria_id]);
$aprendices_banco = $stmtList->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2>Gestión de Datos Bancarios y Financieros</h2>
            <small class="text-muted fw-bold">Convocatoria: <?= htmlspecialchars($convocatoria_nombre) ?></small>
        </div>
        <a href="index.php?convocatoria_id=<?= $convocatoria_id ?>" class="btn btn-outline-secondary fw-bold">
            <i class="bi bi-arrow-left"></i> Volver al Seguimiento
        </a>
    </div>

    <?= $mensaje ?>

    <!-- Tarjeta de Carga Masiva -->
    <div class="card shadow-sm border-success mb-4">
        <div class="card-header bg-success text-white fw-bold"><i class="bi bi-cloud-arrow-up-fill"></i> Carga Masiva (CSV)</div>
        <div class="card-body">
            <p class="text-muted small">
                Sube tu archivo CSV con el siguiente orden estricto de 10 columnas (la primera fila es la cabecera):<br>
                <b>Tipo Doc ; Documento ; Nombre ; CRP ; N° Cuenta ; Entidad Bancaria ; Tipo Cuenta ; Valor CRP ; Pagos Acumulados ; Cuota</b>
            </p>
            <form method="POST" enctype="multipart/form-data" class="row g-2 align-items-center">
                <div class="col-md-6">
                    <input type="file" name="archivo_csv" class="form-control" accept=".csv" required>
                </div>
                <div class="col-md-3">
                    <button type="submit" name="subir_banco_csv" class="btn btn-success fw-bold w-100"><i class="bi bi-upload"></i> Subir y Actualizar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla Resumen -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-dark text-white fw-bold">Datos Bancarios Registrados en la Convocatoria</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 align-middle" style="font-size: 0.85em;">
                    <thead class="table-light">
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
                        <?php if (count($aprendices_banco) === 0): ?>
                            <tr><td colspan="10" class="text-center py-4 text-muted">No hay aprendices registrados en esta convocatoria todavía.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($aprendices_banco as $ab): ?>
                            <tr>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($ab['tipo_documento']) ?></span></td>
                                <td class="fw-bold"><?= htmlspecialchars($ab['documento']) ?></td>
                                <td class="fw-bold text-uppercase"><?= htmlspecialchars($ab['nombre']) ?></td>
                                <td><?= htmlspecialchars($ab['crp'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($ab['no_cuentas_bancarias'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($ab['entidad_bancaria'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($ab['tipo_cuenta_bancaria'] ?? '-') ?></td>
                                <td class="text-end fw-bold text-success"><?= !empty($ab['valor_crp']) ? '$'.number_format($ab['valor_crp'], 0, ',', '.') : '-' ?></td>
                                <td class="text-end fw-bold text-secondary"><?= !empty($ab['pagos_acumulados']) ? '$'.number_format($ab['pagos_acumulados'], 0, ',', '.') : '$0' ?></td>
                                <td class="text-end fw-bold text-primary"><?= !empty($ab['cuota']) ? $ab['cuota'] : '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>