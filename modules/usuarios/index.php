<?php
require_once '../../config/database.php';
require_once '../../includes/header.php';

$rol_actual = $_SESSION['rol'] ?? 'visualizador';
if ($rol_actual !== 'admin') {
    echo "<div class='alert alert-danger m-4'>No tienes permisos para acceder a este módulo.</div>";
    require_once '../../includes/footer.php';
    exit;
}

$mensaje = "";

// 1. CREAR USUARIO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_usuario'])) {
    $nombre = strtoupper(trim($_POST['nombre']));
    $email = strtolower(trim($_POST['email']));
    $password = $_POST['password'];
    $rol = $_POST['rol'];
    $apoyo_id = ($rol === 'admin') ? null : (!empty($_POST['apoyo_id']) ? (int)$_POST['apoyo_id'] : null);

    // Generar el nombre de usuario corto usando la parte antes del @ del correo_sena
    $usuario_alias = explode('@', $email)[0];

    if (!empty($nombre) && !empty($email) && !empty($password)) {
        try {
            $pass_hash = password_hash($password, PASSWORD_BCRYPT);
            // Usamos las columnas exactas de tu base de datos: usuario, nombre_completo, correo_sena, password, rol, apoyo_id
            $stmt = $pdo->prepare("INSERT INTO usuarios (usuario, nombre_completo, correo_sena, password, rol, apoyo_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$usuario_alias, $nombre, $email, $pass_hash, $rol, $apoyo_id]);
            $mensaje = "<div class='alert alert-success fw-bold'><i class='bi bi-check-circle-fill'></i> Usuario creado exitosamente.</div>";
        } catch (PDOException $e) {
            $mensaje = "<div class='alert alert-danger fw-bold'>Error técnico al crear: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

// 2. EDITAR USUARIO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_usuario'])) {
    $id = (int)$_POST['usuario_id'];
    $nombre = strtoupper(trim($_POST['nombre']));
    $email = strtolower(trim($_POST['email']));
    $rol = $_POST['rol'];
    $apoyo_id = ($rol === 'admin') ? null : (!empty($_POST['apoyo_id']) ? (int)$_POST['apoyo_id'] : null);
    
    $usuario_alias = explode('@', $email)[0];

    try {
        if (!empty($_POST['password'])) {
            // Si escribió contraseña, la actualizamos
            $pass_hash = password_hash($_POST['password'], PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE usuarios SET usuario = ?, nombre_completo = ?, correo_sena = ?, password = ?, rol = ?, apoyo_id = ? WHERE id = ?");
            $stmt->execute([$usuario_alias, $nombre, $email, $pass_hash, $rol, $apoyo_id, $id]);
        } else {
            // Si dejó la contraseña en blanco, no la tocamos
            $stmt = $pdo->prepare("UPDATE usuarios SET usuario = ?, nombre_completo = ?, correo_sena = ?, rol = ?, apoyo_id = ? WHERE id = ?");
            $stmt->execute([$usuario_alias, $nombre, $email, $rol, $apoyo_id, $id]);
        }
        $mensaje = "<div class='alert alert-success fw-bold'><i class='bi bi-check-circle-fill'></i> Usuario actualizado exitosamente.</div>";
    } catch (PDOException $e) {
        $mensaje = "<div class='alert alert-danger fw-bold'>Error técnico al actualizar: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// 3. Listar tipos de apoyo disponibles
$apoyos_list = $pdo->query("SELECT id, nombre FROM tipos_apoyo WHERE estado = 'activo' ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

// 4. Listar usuarios con sus apoyos
$usuarios = $pdo->query("
    SELECT u.*, a.nombre AS nombre_apoyo 
    FROM usuarios u 
    LEFT JOIN tipos_apoyo a ON u.apoyo_id = a.id 
    ORDER BY u.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="bi bi-people-fill text-success"></i> Gestión de Usuarios y Responsables</h2>
            <p class="text-muted mb-0">Asigna gestores específicos a cada tipo de apoyo socioeconómico.</p>
        </div>
        <button class="btn btn-success fw-bold" data-bs-toggle="modal" data-bs-target="#modalNuevoUsuario">
            <i class="bi bi-person-plus-fill"></i> Crear Nuevo Usuario
        </button>
    </div>

    <?= $mensaje ?>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-dark text-white fw-bold">Usuarios del Sistema</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Usuario (Alias)</th>
                            <th>Nombre Completo</th>
                            <th>Correo SENA</th>
                            <th class="text-center">Rol</th>
                            <th>Apoyo Asignado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                            <tr>
                                <td class="text-muted fw-bold"><?= htmlspecialchars($u['usuario'] ?? '') ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($u['nombre_completo'] ?? '') ?></td>
                                <td><?= htmlspecialchars($u['correo_sena'] ?? '') ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?= ($u['rol'] ?? '') === 'admin' ? 'danger' : (($u['rol'] ?? '') === 'gestor' ? 'success' : 'warning text-dark') ?>">
                                        <?= strtoupper($u['rol'] ?? 'VISUALIZADOR') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (($u['rol'] ?? '') === 'admin'): ?>
                                        <span class="badge bg-dark">Todos los apoyos (Acceso Total)</span>
                                    <?php elseif (!empty($u['nombre_apoyo'])): ?>
                                        <span class="badge bg-primary fs-6"><?= htmlspecialchars($u['nombre_apoyo']) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Sin apoyo asignado</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary fw-bold btn-editar-user"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalEditarUsuario"
                                            data-id="<?= $u['id'] ?>"
                                            data-nombre="<?= htmlspecialchars($u['nombre_completo'] ?? '') ?>"
                                            data-email="<?= htmlspecialchars($u['correo_sena'] ?? '') ?>"
                                            data-rol="<?= htmlspecialchars($u['rol'] ?? 'visualizador') ?>"
                                            data-apoyo="<?= htmlspecialchars($u['apoyo_id'] ?? '') ?>">
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
</div>

<!-- MODAL: CREAR USUARIO -->
<div class="modal fade" id="modalNuevoUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill me-2"></i> Crear Usuario</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Nombre Completo</label>
                        <input type="text" name="nombre" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Correo SENA</label>
                        <input type="email" name="email" class="form-control" placeholder="ejemplo@sena.edu.co" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Contraseña</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Rol</label>
                        <select name="rol" id="crear_rol" class="form-select" required onchange="toggleApoyoSelect(this.value, 'caja_apoyo_nuevo')">
                            <option value="gestor">Gestor</option>
                            <option value="visualizador">Visualizador</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                    <div class="mb-2" id="caja_apoyo_nuevo">
                        <label class="form-label small fw-bold text-success">Apoyo Socioeconómico Asignado</label>
                        <select name="apoyo_id" class="form-select border-success">
                            <?php foreach ($apoyos_list as $ap): ?>
                                <option value="<?= $ap['id'] ?>"><?= htmlspecialchars($ap['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="crear_usuario" class="btn btn-success btn-sm fw-bold">Guardar Usuario</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: EDITAR USUARIO -->
<div class="modal fade" id="modalEditarUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="usuario_id" id="edit_user_id">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i> Editar Usuario</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Nombre Completo</label>
                        <input type="text" name="nombre" id="edit_user_nombre" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Correo SENA</label>
                        <input type="email" name="email" id="edit_user_email" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Nueva Contraseña (Opcional)</label>
                        <input type="password" name="password" class="form-control" placeholder="Dejar en blanco para conservar la actual">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Rol</label>
                        <select name="rol" id="edit_user_rol" class="form-select" required onchange="toggleApoyoSelect(this.value, 'caja_apoyo_edit')">
                            <option value="gestor">Gestor</option>
                            <option value="visualizador">Visualizador</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                    <div class="mb-2" id="caja_apoyo_edit">
                        <label class="form-label small fw-bold text-success">Apoyo Socioeconómico Asignado</label>
                        <select name="apoyo_id" id="edit_user_apoyo" class="form-select border-success">
                            <?php foreach ($apoyos_list as $ap): ?>
                                <option value="<?= $ap['id'] ?>"><?= htmlspecialchars($ap['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="editar_usuario" class="btn btn-success btn-sm fw-bold">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleApoyoSelect(rol, targetId) {
    const contenedor = document.getElementById(targetId);
    if (contenedor) {
        contenedor.style.display = (rol === 'admin') ? 'none' : 'block';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const botones = document.querySelectorAll('.btn-editar-user');
    botones.forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('edit_user_id').value = this.dataset.id;
            document.getElementById('edit_user_nombre').value = this.dataset.nombre;
            document.getElementById('edit_user_email').value = this.dataset.email;
            document.getElementById('edit_user_rol').value = this.dataset.rol;
            document.getElementById('edit_user_apoyo').value = this.dataset.apoyo;
            toggleApoyoSelect(this.dataset.rol, 'caja_apoyo_edit');
        });
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>