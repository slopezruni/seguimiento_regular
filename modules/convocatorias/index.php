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
            $mensaje = "<div class='alert alert-success fw-bold border-0 shadow-sm'><i class='bi bi-check-circle-fill me-2'></i> Convocatoria creada correctamente con su presupuesto.</div>";
        } catch (PDOException $e) {
            $mensaje = "<div class='alert alert-danger fw-bold border-0 shadow-sm'><i class='bi bi-exclamation-triangle-fill me-2'></i> Error al crear la convocatoria: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        $mensaje = "<div class='alert alert-danger fw-bold border-0 shadow-sm'><i class='bi bi-shield-x-fill me-2'></i> Por favor completa los campos obligatorios correctamente.</div>";
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
            $mensaje = "<div class='alert alert-success fw-bold border-0 shadow-sm'><i class='bi bi-check-circle-fill me-2'></i> Convocatoria actualizada correctamente.</div>";
        } catch (PDOException $e) {
            $mensaje = "<div class='alert alert-danger fw-bold border-0 shadow-sm'><i class='bi bi-exclamation-triangle-fill me-2'></i> Error al actualizar la convocatoria: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        $mensaje = "<div class='alert alert-danger fw-bold border-0 shadow-sm'><i class='bi bi-shield-x-fill me-2'></i> Por favor completa los campos correctamente.</div>";
    }
}

// 3. ELIMINAR CONVOCATORIA EN CASCADA
if (!$es_visualizador && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_convocatoria'])) {
    $id_eliminar = (int)$_POST['convocatoria_id_eliminar'];
    
    if ($id_eliminar > 0) {
        try {
            $pdo->beginTransaction();
            
            // A. Eliminar aprendices asociados a los meses de esta convocatoria
            $stmtDelAprendices = $pdo->prepare("DELETE FROM aprendices_mes WHERE mes_id IN (SELECT id FROM meses_seguimiento WHERE convocatoria_id = ?)");
            $stmtDelAprendices->execute([$id_eliminar]);
            
            // B. Eliminar los meses de seguimiento de esta convocatoria
            $stmtDelMeses = $pdo->prepare("DELETE FROM meses_seguimiento WHERE convocatoria_id = ?");
            $stmtDelMeses->execute([$id_eliminar]);
            
            // C. Eliminar la convocatoria matriz
            $stmtDelConv = $pdo->prepare("DELETE FROM convocatorias WHERE id = ?");
            $stmtDelConv->execute([$id_eliminar]);
            
            $pdo->commit();
            $mensaje = "<div class='alert alert-success fw-bold border-0 shadow-sm'><i class='bi bi-trash3-fill me-2'></i> Convocatoria y todos sus registros asociados eliminados exitosamente.</div>";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $mensaje = "<div class='alert alert-danger fw-bold border-0 shadow-sm'><i class='bi bi-exclamation-triangle-fill me-2'></i> Error crítico al eliminar: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

// 4. OBTENER TIPOS DE APOYO
$stmtApoyosList = $pdo->query("SELECT id, nombre FROM tipos_apoyo WHERE estado = 'activo' ORDER BY id ASC");
$tipos_apoyo_list = $stmtApoyosList->fetchAll(PDO::FETCH_ASSOC);

// 5. OBTENER CONVOCATORIAS FILTRADAS
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
    <!-- CABECERA GOVTECH -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <div class="d-inline-flex align-items-center gap-2 bg-success bg-opacity-10 border border-success border-opacity-25 text-success rounded-pill px-3 py-1 mb-2 small fw-bold">
                <i class="bi bi-folder2-open"></i> Control de Aperturas
            </div>
            <h2 class="fw-extrabold text-dark mb-1">Gestión de Convocatorias</h2>
            <div class="text-muted fw-bold small">
                Administrando cupos y presupuesto para el apoyo: <span class="badge bg-dark ms-1"><?= htmlspecialchars($nombre_apoyo_actual) ?></span>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="/seguimiento-regular/index.php" class="btn btn-outline-secondary fw-bold rounded-pill px-4 shadow-sm">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>

    <?= $mensaje ?>

    <div class="row g-4">
        <?php if (!$es_visualizador): ?>
        <!-- Formulario de Creación -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-success text-white fw-bold py-3">
                    <i class="bi bi-plus-square-fill me-2"></i> Aperturar Convocatoria
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">Asignar a Programa Institucional</label>
                            <select name="apoyo_id" class="form-select bg-light border-success fw-bold text-dark" required>
                                <?php foreach ($tipos_apoyo_list as $ta): ?>
                                    <option value="<?= $ta['id'] ?>" <?= $ta['id'] == $apoyo_activo_id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($ta['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">Nombre de la Convocatoria</label>
                            <input type="text" name="nombre" class="form-control bg-light text-uppercase fw-bold" placeholder="Ej: Convocatoria 01 - 2026" required>
                        </div>

                        <div class="row g-2 mb-4">
                            <div class="col-6">
                                <label class="form-label fw-bold small text-secondary">Cupos Totales</label>
                                <input type="number" name="cupos" class="form-control bg-light fw-bold text-primary" placeholder="Ej: 100" min="1" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold small text-secondary">Presupuesto ($)</label>
                                <input type="number" step="any" name="presupuesto" class="form-control bg-light fw-bold text-success" placeholder="Monto total" required>
                            </div>
                        </div>

                        <button type="submit" name="crear_convocatoria" class="btn btn-success w-100 fw-bold rounded-pill shadow-sm">
                            <i class="bi bi-save-fill me-1"></i> Guardar Convocatoria
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Listado de Convocatorias -->
        <div class="<?= $es_visualizador ? 'col-lg-12' : 'col-lg-8' ?>">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-dark text-white fw-bold py-3 d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-list-ul text-success me-2"></i> Convocatorias Activas</span>
                    <span class="badge bg-light text-dark border"><?= count($convocatorias) ?> Registros</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 550px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.9em;">
                            <thead class="table-light sticky-top shadow-sm">
                                <tr>
                                    <th class="ps-4 py-3">ID</th>
                                    <th class="py-3">Nombre</th>
                                    <th class="py-3">Apoyo Asociado</th>
                                    <th class="text-center py-3">Cupos</th>
                                    <th class="text-end py-3">Presupuesto</th>
                                    <th class="text-center py-3 pe-4">Gestión</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($convocatorias) === 0): ?>
                                    <tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-2 d-block mb-2"></i> No hay convocatorias registradas para este apoyo.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($convocatorias as $c): ?>
                                    <tr>
                                        <td class="fw-bold text-muted ps-4">#<?= $c['id'] ?></td>
                                        <td class="fw-bold text-dark text-uppercase"><?= htmlspecialchars($c['nombre']) ?></td>
                                        <td><span class="badge bg-secondary bg-opacity-10 text-dark border px-2 py-1"><?= htmlspecialchars($c['nombre_apoyo'] ?? 'General') ?></span></td>
                                        <td class="text-center fw-bold"><i class="bi bi-people-fill text-muted me-1"></i> <?= $c['cupos'] ?></td>
                                        <td class="text-end fw-bold text-success"><?= !empty($c['presupuesto']) ? '$'.number_format($c['presupuesto'], 0, ',', '.') : '<span class="badge bg-warning text-dark">Sin Asignar</span>' ?></td>
                                        <td class="text-center pe-4">
                                            <div class="btn-group shadow-sm">
                                                <a href="/seguimiento-regular/modules/seguimiento/index.php?convocatoria_id=<?= $c['id'] ?>" class="btn btn-sm btn-success fw-bold" title="Abrir Planillas">
                                                    <i class="bi bi-folder2-open"></i>
                                                </a>
                                                <?php if (!$es_visualizador): ?>
                                                <button type="button" class="btn btn-sm btn-primary fw-bold btn-editar-conv" 
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
                                                <button type="button" class="btn btn-sm btn-danger fw-bold btn-eliminar-conv" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#modalEliminarConv"
                                                        data-id="<?= $c['id'] ?>"
                                                        data-nombre="<?= htmlspecialchars($c['nombre']) ?>"
                                                        title="Eliminar convocatoria irreversiblemente">
                                                    <i class="bi bi-trash3-fill"></i>
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
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST">
                <input type="hidden" name="convocatoria_id" id="edit_conv_id">
                <div class="modal-header bg-primary text-white border-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i> Editar Convocatoria</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">Tipo de Apoyo:</label>
                        <select name="apoyo_id" id="edit_conv_apoyo" class="form-select bg-light fw-bold text-dark" required>
                            <?php foreach ($tipos_apoyo_list as $ta): ?>
                                <option value="<?= $ta['id'] ?>"><?= htmlspecialchars($ta['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-secondary">Nombre de la Convocatoria</label>
                        <input type="text" name="nombre" id="edit_conv_nombre" class="form-control bg-light fw-bold text-uppercase" required>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label fw-bold small text-secondary">Cupos Totales</label>
                            <input type="number" name="cupos" id="edit_conv_cupos" class="form-control bg-light fw-bold text-primary" min="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-secondary">Presupuesto ($)</label>
                            <input type="number" step="any" name="presupuesto" id="edit_conv_presupuesto" class="form-control bg-light fw-bold text-success" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light py-3">
                    <button type="button" class="btn btn-secondary fw-bold px-4 rounded-pill shadow-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="editar_convocatoria" class="btn btn-primary fw-bold px-4 rounded-pill shadow-sm">
                        <i class="bi bi-check-circle-fill me-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL ESTRICTO: ELIMINAR CONVOCATORIA -->
<div class="modal fade" id="modalEliminarConv" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST">
                <input type="hidden" name="convocatoria_id_eliminar" id="delete_conv_id">
                <div class="modal-header bg-danger text-white border-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i> Advertencia de Seguridad</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <i class="bi bi-trash3-fill text-danger d-block mb-3" style="font-size: 3rem; opacity: 0.5;"></i>
                    <h5 class="fw-bold text-dark mb-3">¿Estás absolutamente seguro?</h5>
                    <p class="text-muted mb-2">Vas a eliminar la convocatoria: <strong class="text-dark" id="delete_conv_nombre"></strong></p>
                    <div class="alert alert-danger border-danger fw-bold small mt-3 px-3 py-2 text-start">
                        <ul class="mb-0 ps-3">
                            <li>Se eliminarán TODAS las carpetas de meses de esta convocatoria.</li>
                            <li>Se borrarán TODOS los aprendices asociados y sus seguimientos.</li>
                            <li>El presupuesto será restado del reporte general.</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light justify-content-center py-3">
                    <button type="button" class="btn btn-light border fw-bold px-4 rounded-pill shadow-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="eliminar_convocatoria" class="btn btn-danger fw-bold px-4 rounded-pill shadow-sm">
                        <i class="bi bi-trash3-fill me-1"></i> Sí, Eliminar Todo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Pasar datos al Modal de Edición
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

    // Pasar datos al Modal de Eliminación Estricta
    const botonesEliminar = document.querySelectorAll('.btn-eliminar-conv');
    botonesEliminar.forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('delete_conv_id').value = this.dataset.id;
            document.getElementById('delete_conv_nombre').textContent = this.dataset.nombre;
        });
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>