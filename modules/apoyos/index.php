<?php
require_once '../../config/database.php';
require_once '../../includes/header.php';

// Validar permisos: Solo el Administrador puede gestionar los Tipos de Apoyo
$rol_actual = $_SESSION['rol'] ?? 'visualizador';
if ($rol_actual !== 'admin') {
    echo "<div class='container-fluid px-4 py-5'>
            <div class='alert alert-danger border-0 shadow-sm fw-bold'>
                <i class='bi bi-shield-lock-fill me-2 fs-5 align-middle'></i> No tienes permisos de administrador para acceder a este módulo.
            </div>
          </div>";
    require_once '../../includes/footer.php';
    exit;
}

$mensaje = "";

// 1. CREAR NUEVO APOYO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_apoyo'])) {
    $nombre = trim($_POST['nombre']);
    $estado = $_POST['estado'] ?? 'activo';

    if (!empty($nombre)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO tipos_apoyo (nombre, estado) VALUES (?, ?)");
            $stmt->execute([$nombre, $estado]);
            $mensaje = "<div class='alert alert-success border-0 shadow-sm fw-bold mb-4'><i class='bi bi-check-circle-fill me-2'></i> Tipo de apoyo creado exitosamente.</div>";
        } catch (PDOException $e) {
            $mensaje = "<div class='alert alert-danger border-0 shadow-sm fw-bold mb-4'><i class='bi bi-exclamation-triangle-fill me-2'></i> Error técnico al crear: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

// 2. EDITAR APOYO EXISTENTE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_apoyo'])) {
    $id = (int)$_POST['apoyo_id'];
    $nombre = trim($_POST['nombre']);
    $estado = $_POST['estado'] ?? 'activo';

    if ($id > 0 && !empty($nombre)) {
        try {
            $stmt = $pdo->prepare("UPDATE tipos_apoyo SET nombre = ?, estado = ? WHERE id = ?");
            $stmt->execute([$nombre, $estado, $id]);
            
            // Forzar recarga visual del nombre si es el apoyo que tenemos activo
            if (isset($_SESSION['apoyo_activo_id']) && (int)$_SESSION['apoyo_activo_id'] === $id) {
                // Se actualizará en el header tras refrescar
            }

            $mensaje = "<div class='alert alert-success border-0 shadow-sm fw-bold mb-4'><i class='bi bi-check-circle-fill me-2'></i> Tipo de apoyo actualizado exitosamente.</div>";
        } catch (PDOException $e) {
            $mensaje = "<div class='alert alert-danger border-0 shadow-sm fw-bold mb-4'><i class='bi bi-exclamation-triangle-fill me-2'></i> Error técnico al actualizar: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

// 3. OBTENER LISTADO DE APOYOS
$stmtApoyos = $pdo->query("SELECT * FROM tipos_apoyo ORDER BY id ASC");
$apoyos = $stmtApoyos->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid px-4 py-3">
    <!-- Encabezado del Módulo -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-extrabold text-dark mb-1"><i class="bi bi-collection-fill text-success me-2"></i> Gestión de Tipos de Apoyo</h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">Crea, edita y administra los programas socioeconómicos base de la institución.</p>
        </div>
        <button class="btn btn-success fw-bold rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoApoyo">
            <i class="bi bi-plus-circle-fill me-1"></i> Crear Nuevo Programa
        </button>
    </div>

    <?= $mensaje ?>

    <!-- Tarjeta de la Tabla -->
    <div class="card bg-white border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <div class="fw-bold text-dark">
                <i class="bi bi-list-ul text-success me-2"></i> Programas Registrados en el Sistema
            </div>
            <span class="badge bg-light text-dark border px-2 py-1">Total: <?= count($apoyos) ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 10%;">ID</th>
                            <th style="width: 50%;">Nombre del Apoyo Socioeconómico</th>
                            <th class="text-center" style="width: 20%;">Estado Operativo</th>
                            <th class="text-center pe-4" style="width: 20%;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($apoyos) === 0): ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-2 d-block mb-2"></i> No hay tipos de apoyo registrados.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($apoyos as $ap): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-muted">#<?= $ap['id'] ?></td>
                                <td class="fw-bold text-dark fs-6"><?= htmlspecialchars($ap['nombre']) ?></td>
                                <td class="text-center">
                                    <?php if (($ap['estado'] ?? 'activo') === 'activo'): ?>
                                        <span class="badge bg-success text-white fw-bold px-3 py-2 rounded-pill shadow-sm">
                                            <i class="bi bi-check-circle-fill me-1 text-white"></i> Activo
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger text-white fw-bold px-3 py-2 rounded-pill shadow-sm">
                                            <i class="bi bi-x-circle-fill me-1 text-white"></i> Inactivo
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center pe-4">
                                    <button type="button" class="btn btn-sm btn-outline-primary fw-bold px-3 rounded-pill btn-editar-apoyo"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalEditarApoyo"
                                            data-id="<?= $ap['id'] ?>"
                                            data-nombre="<?= htmlspecialchars($ap['nombre']) ?>"
                                            data-estado="<?= htmlspecialchars($ap['estado'] ?? 'activo') ?>"
                                            title="Editar detalles del apoyo">
                                        <i class="bi bi-pencil-square me-1"></i> Editar
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: CREAR APOYO -->
<div class="modal fade" id="modalNuevoApoyo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST">
                <div class="modal-header bg-success text-white border-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle-fill me-2"></i> Crear Nuevo Apoyo</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Nombre del Programa</label>
                        <input type="text" name="nombre" class="form-control form-control-lg fs-6" placeholder="Ej: Apoyo de Transporte" required autofocus>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold text-secondary">Estado Inicial</label>
                        <select name="estado" class="form-select fw-bold text-dark border-secondary">
                            <option value="activo">Operativo (Activo)</option>
                            <option value="inactivo">Pausado (Inactivo)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3">
                    <button type="button" class="btn btn-light fw-bold text-muted px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="crear_apoyo" class="btn btn-success fw-bold px-4 shadow-sm">
                        <i class="bi bi-save-fill me-1"></i> Guardar Apoyo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: EDITAR APOYO -->
<div class="modal fade" id="modalEditarApoyo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <form method="POST">
                <input type="hidden" name="apoyo_id" id="edit_apoyo_id">
                <div class="modal-header bg-dark text-white border-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2 text-success"></i> Editar Tipo de Apoyo</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Nombre del Programa</label>
                        <input type="text" name="nombre" id="edit_apoyo_nombre" class="form-control form-control-lg fs-6 fw-bold text-dark" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold text-secondary">Estado Operativo</label>
                        <select name="estado" id="edit_apoyo_estado" class="form-select fw-bold text-dark border-secondary">
                            <option value="activo">Operativo (Activo)</option>
                            <option value="inactivo">Pausado (Inactivo)</option>
                        </select>
                        <div class="mt-2 text-secondary" style="font-size: 0.82rem; line-height: 1.3;">
                            <i class="bi bi-info-circle-fill text-primary me-1"></i> Si lo marcas como inactivo, los gestores no podrán verlo en sus menús ni asignar nuevas convocatorias a este apoyo.
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3">
                    <button type="button" class="btn btn-light fw-bold text-muted px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="editar_apoyo" class="btn btn-success fw-bold px-4 shadow-sm">
                        <i class="bi bi-check-circle-fill me-1"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Pasar los datos del botón seleccionado al modal de Edición
    const botonesEditar = document.querySelectorAll('.btn-editar-apoyo');
    botonesEditar.forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('edit_apoyo_id').value = this.dataset.id;
            document.getElementById('edit_apoyo_nombre').value = this.dataset.nombre;
            document.getElementById('edit_apoyo_estado').value = this.dataset.estado;
        });
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>