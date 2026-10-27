<?php
require_once '../../config/database.php';
require_once '../../includes/header.php';

$rol_actual = $_SESSION['rol'] ?? 'visualizador';
$apoyo_activo_id = $_SESSION['apoyo_activo_id'] ?? 1;

$es_visualizador = ($rol_actual === 'visualizador');

$mensaje = "";

// AUTO-VERIFICACIÓN DE LA COLUMNA PRESUPUESTO
try {
    $checkCol = $pdo->query("SHOW COLUMNS FROM convocatorias LIKE 'presupuesto'");
    if ($checkCol && $checkCol->rowCount() == 0) {
        $pdo->exec("ALTER TABLE convocatorias ADD COLUMN presupuesto DECIMAL(15, 2) DEFAULT 0 AFTER cupos");
    }
} catch (PDOException $e) {
    // Continuar
}

// 1. CREAR CONVOCATORIA
if (!$es_visualizador && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_convocatoria'])) {
    $nombre = trim($_POST['nombre']);
    $cupos = (int)$_POST['cupos'];
    $presupuesto = !empty($_POST['presupuesto']) ? floatval(str_replace(['$', '.', ','], ['', '', '.'], $_POST['presupuesto'])) : 0;
    $apoyo_id_seleccionado = (int)$_POST['apoyo_id'];

    if (!empty($nombre) && $cupos > 0) {
        try {
            $stmt = $pdo->prepare("INSERT INTO convocatorias (apoyo_id, nombre, cupos, presupuesto) VALUES (?, ?, ?, ?)");
            $stmt->execute([$apoyo_id_seleccionado, $nombre, $cupos, $presupuesto]);
            $mensaje = "<div class='alert alert-success fw-bold'><i class='bi bi-check-circle-fill'></i> Convocatoria creada correctamente con su presupuesto.</div>";
        } catch (PDOException $e) {
            $mensaje = "<div class='alert alert-danger fw-bold'>Error al crear la convocatoria: " . $e->getMessage() . "</div>";
        }
    } else {
        $mensaje = "<div class='alert alert-danger fw-bold'>Por favor completa los campos obligatorios correctamente.</div>";
    }
}

// 2. EDITAR CONVOCATORIA
if (!$es_visualizador && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_convocatoria'])) {
    $id = (int)$_POST['convocatoria_id'];
    $nombre = trim($_POST['nombre']);
    $cupos = (int)$_POST['cupos'];
    $presupuesto = !empty($_POST['presupuesto']) ? floatval(str_replace(['$', '.', ','], ['', '', '.'], $_POST['presupuesto'])) : 0;
    $apoyo_id_seleccionado = (int)$_POST['apoyo_id'];

    if (!empty($nombre) && $cupos > 0 && $id > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE convocatorias SET apoyo_id = ?, nombre = ?, cupos = ?, presupuesto = ? WHERE id = ?");
            $stmt->execute([$apoyo_id_seleccionado, $nombre, $cupos, $presupuesto, $id]);
            $mensaje = "<div class='alert alert-success fw-bold'><i class='bi bi-check-circle-fill'></i> Convocatoria actualizada correctamente.</div>";
        } catch (PDOException $e) {
            $mensaje = "<div class='alert alert-danger fw-bold'>Error al actualizar la convocatoria: " . $e->getMessage() . "</div>";
        }
    } else {
        $mensaje = "<div class='alert alert-danger fw-bold'>Por favor completa los campos correctamente.</div>";
    }
}

// 3. OBTENER TIPOS DE APOYO
$stmtApoyosList = $pdo->query("SELECT id, nombre FROM tipos_apoyo WHERE estado = 'activo' ORDER BY id ASC");
$tipos_apoyo_list = $stmtApoyosList->fetchAll(PDO::FETCH_ASSOC);

// 4. OBTENER CONVOCATORIAS FILTRADAS
$stmtConvs = $pdo->prepare("
    SELECT c.*, t.nombre as nombre_apoyo 
    FROM convocatorias c 
    LEFT JOIN tipos_apoyo t ON c.apoyo_id = t.id 
    WHERE c.apoyo_id = ? 
    ORDER BY c.id DESC
");
$stmtConvs->execute([$apoyo_activo_id]);
$convocatorias = $stmtConvs->fetchAll(PDO::FETCH_ASSOC);

$stmtApoyoNom = $pdo->prepare("SELECT nombre FROM tipos_apoyo WHERE id = ?");
$stmtApoyoNom->execute([$apoyo_activo_id]);
$nombre_apoyo_actual = $stmtApoyoNom->fetchColumn() ?: 'Apoyo';
?>

<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="bi bi-folder-fill text-success"></i> Gestión de Convocatorias</h2>
            <p class="text-muted mb-0">Administra las convocatorias activas para el apoyo: <span class="badge bg-success"><?= htmlspecialchars($nombre_apoyo_actual) ?></span></p>
        </div>
    </div>

    <?= $mensaje ?>

    <div class="row">
        <?php if (!$es_visualizador): ?>
        <!-- Formulario de Creación -->
        <div class="col-md-4">
            <div class="card shadow-sm border-success mb-4">
                <div class="card-header bg-success text-white fw-bold"><i class="bi bi-plus-square-fill"></i> Nueva Convocatoria</div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Asignar a Apoyo Socioeconómico:</label>
                            <select name="apoyo_id" class="form-select fw-bold border-success" required>
                                <?php foreach ($tipos_apoyo_list as $ta): ?>
                                    <option value="<?= $ta['id'] ?>" <?= $ta['id'] == $apoyo_activo_id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($ta['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Nombre de la Convocatoria</label>
                            <input type="text" name="nombre" class="form-control" placeholder="Ej: Convocatoria 01 - 2026" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Cupos Totales</label>
                            <input type="number" name="cupos" class="form-control" placeholder="Ej: 100" min="1" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Presupuesto Asignado</label>
                            <input type="number" step="any" name="presupuesto" class="form-control" placeholder="Ej: 50000000" required>
                        </div>

                        <button type="submit" name="crear_convocatoria" class="btn btn-success w-100 fw-bold">
                            <i class="bi bi-save"></i> Guardar Convocatoria
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Listado de Convocatorias -->
        <div class="<?= $es_visualizador ? 'col-md-12' : 'col-md-8' ?>">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white fw-bold">Convocatorias de <?= htmlspecialchars($nombre_apoyo_actual) ?> (<?= count($convocatorias) ?>)</div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 550px; overflow-y: auto;">
                        <table class="table table-striped mb-0 align-middle">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Apoyo Asociado</th>
                                    <th class="text-center">Cupos</th>
                                    <th class="text-end">Presupuesto</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($convocatorias) === 0): ?>
                                    <tr><td colspan="6" class="text-center py-4 text-muted">No hay convocatorias registradas para este apoyo.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($convocatorias as $c): ?>
                                    <tr>
                                        <td class="fw-bold"><?= $c['id'] ?></td>
                                        <td class="fw-bold text-success fs-6"><?= htmlspecialchars($c['nombre']) ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($c['nombre_apoyo'] ?? 'General') ?></span></td>
                                        <td class="text-center fw-bold"><?= $c['cupos'] ?></td>
                                        <td class="text-end fw-bold text-success"><?= !empty($c['presupuesto']) ? '$'.number_format($c['presupuesto'], 0, ',', '.') : '$0' ?></td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <a href="/seguimiento-regular/modules/seguimiento/index.php?convocatoria_id=<?= $c['id'] ?>" class="btn btn-primary fw-bold" title="Abrir planilla">
                                                    <i class="bi bi-eye-fill"></i>
                                                </a>
                                                <?php if (!$es_visualizador): ?>
                                                <button type="button" class="btn btn-warning fw-bold btn-editar-conv" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#modalEditarConv"
                                                        data-id="<?= $c['id'] ?>"
                                                        data-nombre="<?= htmlspecialchars($c['nombre']) ?>"
                                                        data-apoyoid="<?= $c['apoyo_id'] ?>"
                                                        data-cupos="<?= $c['cupos'] ?>"
                                                        data-presupuesto="<?= $c['presupuesto'] ?>"
                                                        title="Editar datos de la convocatoria">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: EDITAR CONVOCATORIA -->
<div class="modal fade" id="modalEditarConv" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="convocatoria_id" id="edit_conv_id">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i> Editar Convocatoria</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Tipo de Apoyo:</label>
                        <select name="apoyo_id" id="edit_conv_apoyo" class="form-select fw-bold" required>
                            <?php foreach ($tipos_apoyo_list as $ta): ?>
                                <option value="<?= $ta['id'] ?>"><?= htmlspecialchars($ta['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Nombre de la Convocatoria</label>
                        <input type="text" name="nombre" id="edit_conv_nombre" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Cupos Totales</label>
                        <input type="number" name="cupos" id="edit_conv_cupos" class="form-control" min="1" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Presupuesto Asignado</label>
                        <input type="number" step="any" name="presupuesto" id="edit_conv_presupuesto" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm fw-bold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="editar_convocatoria" class="btn btn-success btn-sm fw-bold">
                        <i class="bi bi-check-circle-fill"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const botonesEditar = document.querySelectorAll('.btn-editar-conv');
    botonesEditar.forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('edit_conv_id').value = this.dataset.id;
            document.getElementById('edit_conv_nombre').value = this.dataset.nombre;
            document.getElementById('edit_conv_apoyo').value = this.dataset.apoyoid;
            document.getElementById('edit_conv_cupos').value = this.dataset.cupos;
            document.getElementById('edit_conv_presupuesto').value = this.dataset.presupuesto;
        });
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>