<?php
require_once '../../config/database.php';
require_once '../../includes/header.php';

$es_visualizador = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'visualizador');

if (!$es_visualizador && $_SERVER['REQUEST_METHOD'] === 'POST') {

    // A) CARGA MANUAL FIC
    if (isset($_POST['guardar_fic_manual'])) {
        $tipo = strtoupper(trim($_POST['tipo_documento']));
        $documento = trim($_POST['documento']);
        $nombre = strtoupper(trim($_POST['nombre']));

        if (!empty($documento) && !empty($nombre)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO base_fic (tipo_documento, documento, nombre) VALUES (?, ?, ?)");
                $stmt->execute([$tipo, $documento, $nombre]);
                echo "<div class='alert alert-success mt-2 fw-bold'>Beneficiario FIC agregado correctamente.</div>";
            } catch (PDOException $e) {
                // Intento alternativo si la columna se llama tipo_doc
                try {
                    $stmt = $pdo->prepare("INSERT INTO base_fic (tipo_doc, documento, nombre) VALUES (?, ?, ?)");
                    $stmt->execute([$tipo, $documento, $nombre]);
                    echo "<div class='alert alert-success mt-2 fw-bold'>Beneficiario FIC agregado correctamente.</div>";
                } catch (PDOException $ex) {
                    echo "<div class='alert alert-danger mt-2 fw-bold'>Error: El documento ya existe en la Base FIC.</div>";
                }
            }
        }
    }

    // B) CARGA MASIVA CSV FIC
    if (isset($_POST['subir_fic_csv'])) {
        if ($_FILES['archivo_csv']['error'] == 0) {
            $archivo = $_FILES['archivo_csv']['tmp_name'];
            $lineas = file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $insertados = 0; $duplicados = 0; $lista_duplicados = [];

            $stmtCheck = $pdo->prepare("SELECT id FROM base_fic WHERE documento = ?");
            $stmtInsert = $pdo->prepare("INSERT INTO base_fic (tipo_documento, documento, nombre) VALUES (?, ?, ?)");

            foreach ($lineas as $index => $linea) {
                if ($index === 0) continue; // Saltar cabecera

                $separador = strpos($linea, ';') !== false ? ';' : ',';
                $datos = str_getcsv($linea, $separador);

                if (count($datos) >= 3) {
                    $tipo = strtoupper(trim(str_replace(['"', "'"], '', $datos[0])));
                    $documento = trim(str_replace(['"', "'"], '', $datos[1]));
                    $nombre = strtoupper(trim(str_replace(['"', "'"], '', $datos[2])));

                    if (!empty($documento) && is_numeric($documento)) {
                        $stmtCheck->execute([$documento]);
                        if ($stmtCheck->rowCount() == 0 && !in_array($documento, $lista_duplicados)) {
                            try {
                                $stmtInsert->execute([$tipo, $documento, empty($nombre) ? 'SIN NOMBRE' : $nombre]);
                                $insertados++;
                            } catch (PDOException $e) {
                                // Fallback por si la columna es tipo_doc
                                try {
                                    $stmtAlt = $pdo->prepare("INSERT INTO base_fic (tipo_doc, documento, nombre) VALUES (?, ?, ?)");
                                    $stmtAlt->execute([$tipo, $documento, empty($nombre) ? 'SIN NOMBRE' : $nombre]);
                                    $insertados++;
                                } catch (PDOException $ex) {
                                    $duplicados++;
                                    if (!in_array($documento, $lista_duplicados)) $lista_duplicados[] = $documento;
                                }
                            }
                        } else {
                            $duplicados++;
                            if (!in_array($documento, $lista_duplicados)) $lista_duplicados[] = $documento;
                        }
                    }
                }
            }
            if ($insertados > 0) echo "<div class='alert alert-success mt-2 fw-bold'>Carga FIC procesada: $insertados beneficiarios agregados.</div>";
            if ($duplicados > 0) echo "<div class='alert alert-warning mt-2 fw-bold'>Se omitieron $duplicados registros duplicados.</div>";
        }
    }

    // C) ELIMINAR BENEFICIARIO FIC
    if (isset($_POST['eliminar_fic_id'])) {
        $id_del = $_POST['id_eliminar'];
        $stmtDel = $pdo->prepare("DELETE FROM base_fic WHERE id = ?");
        $stmtDel->execute([$id_del]);
        echo "<div class='alert alert-success mt-2 fw-bold'>Beneficiario FIC eliminado correctamente.</div>";
    }
}

// Consultar todos los registros FIC
$stmtFic = $pdo->query("SELECT * FROM base_fic ORDER BY nombre ASC");
$lista_fic = $stmtFic->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0"><i class="bi bi-cone-striped text-danger"></i> Base de Datos FIC Global</h2>
        <p class="text-muted mb-0">Gestión de beneficiarios FIC para el cruce automático de cancelaciones en las convocatorias.</p>
    </div>
</div>

<?php if (!$es_visualizador): ?>
<div class="row mb-4">
    <!-- Carga Manual Arriba / Ancho Completo -->
    <div class="col-xl-6 mb-3 mb-xl-0">
        <div class="card shadow-sm border-primary h-100">
            <div class="card-header bg-primary text-white fw-bold py-2">Carga Manual FIC</div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <label class="small text-muted fw-bold">Tipo Doc</label>
                            <input type="text" name="tipo_documento" class="form-control form-control-sm" placeholder="CC" required>
                        </div>
                        <div class="col-md-8 mb-2">
                            <label class="small text-muted fw-bold">Documento</label>
                            <input type="number" name="documento" class="form-control form-control-sm" placeholder="N° de identidad" required>
                        </div>
                        <div class="col-12 mb-2">
                            <label class="small text-muted fw-bold">Nombre Completo</label>
                            <input type="text" name="nombre" class="form-control form-control-sm text-uppercase" placeholder="Nombre del beneficiario" required>
                        </div>
                    </div>
                    <button type="submit" name="guardar_fic_manual" class="btn btn-primary btn-sm w-100 fw-bold mt-2">Agregar a Base FIC</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Carga Masiva CSV -->
    <div class="col-xl-6">
        <div class="card shadow-sm border-warning h-100">
            <div class="card-header bg-warning text-dark fw-bold py-2">Carga Masiva (CSV) Base FIC</div>
            <div class="card-body d-flex flex-column justify-content-center">
                <p class="small text-muted mb-2">Sube un archivo CSV con las columnas: <code>Tipo_Doc, Documento, Nombre</code> para actualizar la base global de FIC.</p>
                <form method="POST" enctype="multipart/form-data" class="d-flex gap-2">
                    <input type="file" name="archivo_csv" class="form-control form-control-sm" accept=".csv" required>
                    <button type="submit" name="subir_fic_csv" class="btn btn-warning btn-sm fw-bold text-nowrap">Subir CSV FIC</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Listado FIC a Ancho Completo (col-12) -->
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0 shadow-lg">
            <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between align-items-center">
                <span>Beneficiarios FIC Registrados</span>
                <span class="badge bg-danger fs-6 rounded-pill"><?= count($lista_fic) ?> Total</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width: 5%;">#</th>
                                <th class="text-center" style="width: 15%;">Tipo Doc</th>
                                <th style="width: 25%;">Documento</th>
                                <th>Nombre Completo</th>
                                <?php if (!$es_visualizador): ?><th class="text-center" style="width: 15%;">Acción</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($lista_fic) === 0): ?>
                                <tr><td colspan="<?= $es_visualizador ? '4' : '5' ?>" class="text-center py-4 text-muted">No hay registros en la Base FIC.</td></tr>
                            <?php endif; ?>

                            <?php $item = 1; foreach ($lista_fic as $fic): 
                                // Soporte para ambos nombres de columna posibles en BD
                                $tipo_doc_val = $fic['tipo_documento'] ?? ($fic['tipo_doc'] ?? 'CC');
                            ?>
                            <tr>
                                <td class="text-center fw-bold text-muted"><?= $item++ ?></td>
                                <td class="text-center"><span class="badge bg-light text-dark border"><?= htmlspecialchars($tipo_doc_val) ?></span></td>
                                <td class="fw-bold"><?= htmlspecialchars($fic['documento']) ?></td>
                                <td class="fw-bold text-uppercase text-dark"><?= htmlspecialchars($fic['nombre']) ?></td>
                                
                                <?php if (!$es_visualizador): ?>
                                <td class="text-center">
                                    <form method="POST" class="m-0" onsubmit="return confirm('¿Estás seguro de eliminar a <?= addslashes($fic['nombre']) ?> de la Base FIC?');">
                                        <input type="hidden" name="id_eliminar" value="<?= $fic['id'] ?>">
                                        <button type="submit" name="eliminar_fic_id" class="btn btn-sm btn-outline-danger border-0" title="Eliminar registro">
                                            <i class="bi bi-trash3-fill fs-6"></i>
                                        </button>
                                    </form>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>