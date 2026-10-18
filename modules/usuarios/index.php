<?php
require_once '../../config/database.php';
require_once '../../includes/header.php';

$rol_actual = $_SESSION['rol'] ?? 'visualizador';

// BLOQUEO DE SEGURIDAD: Solo el Administrador puede gestionar usuarios
if ($rol_actual !== 'admin') {
    echo "<div class='alert alert-danger fw-bold m-4'>Acceso denegado: Esta sección es exclusiva para el Administrador del sistema. Los gestores no tienen permisos aquí.</div>";
    require_once '../../includes/footer.php';
    exit;
}

$edit_mode = false;
$edit_data = null;

if (isset($_GET['editar'])) {
    $id_editar = $_GET['editar'];
    $stmtEdit = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmtEdit->execute([$id_editar]);
    $edit_data = $stmtEdit->fetch(PDO::FETCH_ASSOC);
    if ($edit_data) {
        $edit_mode = true;
    }
}

// PROCESAR FORMULARIOS POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // A) CREAR NUEVO USUARIO
    if (isset($_POST['crear_usuario'])) {
        $usuario = trim($_POST['usuario']);
        $password = trim($_POST['password']);
        $nombre_completo = strtoupper(trim($_POST['nombre_completo']));
        $numero_identidad = trim($_POST['numero_identidad']);
        $correo_sena = trim($_POST['correo_sena']);
        $cargo = strtoupper(trim($_POST['cargo']));
        $rol = $_POST['rol']; // 'admin', 'gestor' o 'visualizador'

        if (!empty($usuario) && !empty($password) && !empty($numero_identidad)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO usuarios (usuario, password, numero_identidad, correo_sena, cargo, rol, nombre_completo) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$usuario, $password, $numero_identidad, $correo_sena, $cargo, $rol, $nombre_completo]);
                echo "<div class='alert alert-success mt-3 fw-bold'><i class='bi bi-person-check-fill'></i> Usuario creado exitosamente.</div>";
            } catch (PDOException $e) {
                echo "<div class='alert alert-warning mt-3 fw-bold'><i class='bi bi-exclamation-triangle-fill'></i> Error: El usuario o número de identidad ya existe.</div>";
            }
        } else {
            echo "<div class='alert alert-danger mt-3 fw-bold'>Por favor completa los campos obligatorios.</div>";
        }
    }

    // B) ACTUALIZAR USUARIO EXISTENTE
    if (isset($_POST['actualizar_usuario'])) {
        $id_usuario = $_POST['id_usuario'];
        $nombre_completo = strtoupper(trim($_POST['nombre_completo']));
        $numero_identidad = trim($_POST['numero_identidad']);
        $correo_sena = trim($_POST['correo_sena']);
        $cargo = strtoupper(trim($_POST['cargo']));
        $usuario = trim($_POST['usuario']);
        $rol = $_POST['rol'];
        $password = trim($_POST['password']);

        try {
            if (!empty($password)) {
                $stmtUpd = $pdo->prepare("UPDATE usuarios SET nombre_completo = ?, numero_identidad = ?, correo_sena = ?, cargo = ?, usuario = ?, rol = ?, password = ? WHERE id = ?");
                $stmtUpd->execute([$nombre_completo, $numero_identidad, $correo_sena, $cargo, $usuario, $rol, $password, $id_usuario]);
            } else {
                $stmtUpd = $pdo->prepare("UPDATE usuarios SET nombre_completo = ?, numero_identidad = ?, correo_sena = ?, cargo = ?, usuario = ?, rol = ? WHERE id = ?");
                $stmtUpd->execute([$nombre_completo, $numero_identidad, $correo_sena, $cargo, $usuario, $rol, $id_usuario]);
            }
            
            if ($id_usuario == $_SESSION['user_id']) {
                $_SESSION['nombre'] = $nombre_completo;
                $_SESSION['rol'] = $rol;
            }

            echo "<div class='alert alert-success mt-3 fw-bold'><i class='bi bi-check-circle-fill'></i> Usuario actualizado correctamente.</div>";
            $edit_mode = false;
            $edit_data = null;
        } catch (PDOException $e) {
            echo "<div class='alert alert-warning mt-3 fw-bold'><i class='bi bi-exclamation-triangle-fill'></i> Error al actualizar.</div>";
        }
    }

    // C) ELIMINAR USUARIO
    if (isset($_POST['eliminar_usuario_id'])) {
        $id_eliminar = $_POST['id_eliminar'];
        
        if ($id_eliminar == $_SESSION['user_id']) {
            echo "<div class='alert alert-danger mt-3 fw-bold'>No puedes eliminar tu propia cuenta de administrador.</div>";
        } else {
            $stmtDel = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmtDel->execute([$id_eliminar]);
            echo "<div class='alert alert-success mt-3 fw-bold'><i class='bi bi-trash3-fill'></i> Usuario eliminado correctamente.</div>";
        }
    }
}

$stmtUsers = $pdo->query("SELECT * FROM usuarios ORDER BY id ASC");
$usuarios_list = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0"><i class="bi bi-people-fill text-primary"></i> Gestión de Usuarios y Roles</h2>
        <p class="text-muted mb-0">Panel exclusivo del Administrador para control de credenciales.</p>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm <?= $edit_mode ? 'border-warning' : 'border-primary' ?> h-100">
            <div class="card-header <?= $edit_mode ? 'bg-warning text-dark' : 'bg-primary text-white' ?> fw-bold d-flex justify-content-between align-items-center">
                <span><?= $edit_mode ? 'Editar Usuario' : 'Registrar Nuevo Usuario' ?></span>
                <?php if ($edit_mode): ?>
                    <a href="index.php" class="btn btn-sm btn-dark py-0 px-2" style="font-size: 0.75em;">Cancelar</a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="id_usuario" value="<?= $edit_data['id'] ?>">
                    <?php endif; ?>

                    <div class="mb-2">
                        <label class="small text-muted fw-bold mb-1">Nombre Completo</label>
                        <input type="text" name="nombre_completo" class="form-control form-control-sm text-uppercase" placeholder="Ej: Juan Pérez" value="<?= $edit_mode ? htmlspecialchars($edit_data['nombre_completo']) : '' ?>" required autocomplete="off">
                    </div>
                    
                    <div class="mb-2">
                        <label class="small text-muted fw-bold mb-1">Número de Identidad</label>
                        <input type="number" name="numero_identidad" class="form-control form-control-sm" placeholder="Ej: 1147955081" value="<?= $edit_mode ? htmlspecialchars($edit_data['numero_identidad']) : '' ?>" required>
                    </div>

                    <div class="mb-2">
                        <label class="small text-muted fw-bold mb-1">Correo SENA</label>
                        <input type="email" name="correo_sena" class="form-control form-control-sm" placeholder="ejemplo@sena.edu.co" value="<?= $edit_mode ? htmlspecialchars($edit_data['correo_sena']) : '' ?>" required>
                    </div>

                    <div class="mb-2">
                        <label class="small text-muted fw-bold mb-1">Cargo Institucional</label>
                        <input type="text" name="cargo" class="form-control form-control-sm text-uppercase" placeholder="Ej: Apoyo Administrativo" value="<?= $edit_mode ? htmlspecialchars($edit_data['cargo']) : '' ?>" required>
                    </div>

                    <div class="row">
                        <div class="col-6 mb-2">
                            <label class="small text-muted fw-bold mb-1">Usuario</label>
                            <input type="text" name="usuario" class="form-control form-control-sm" placeholder="user_sena" value="<?= $edit_mode ? htmlspecialchars($edit_data['usuario']) : '' ?>" required autocomplete="off">
                        </div>
                        <div class="col-6 mb-2">
                            <label class="small text-muted fw-bold mb-1">Contraseña</label>
                            <input type="password" name="password" class="form-control form-control-sm" placeholder="<?= $edit_mode ? 'Dejar en blanco' : '••••••' ?>" <?= $edit_mode ? '' : 'required' ?>>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="small text-muted fw-bold mb-1">Rol Asignado</label>
                        <select name="rol" class="form-select form-select-sm fw-bold text-success" required>
                            <option value="visualizador" <?= ($edit_mode && $edit_data['rol'] === 'visualizador') ? 'selected' : '' ?>>Visualizador (Solo lectura)</option>
                            <option value="gestor" <?= ($edit_mode && $edit_data['rol'] === 'gestor') ? 'selected' : '' ?>>Gestor (Operación total sin usuarios)</option>
                            <option value="admin" <?= ($edit_mode && $edit_data['rol'] === 'admin') ? 'selected' : '' ?>>Administrador (Control total + Usuarios)</option>
                        </select>
                    </div>

                    <?php if ($edit_mode): ?>
                        <button type="submit" name="actualizar_usuario" class="btn btn-warning btn-sm w-100 fw-bold text-dark"><i class="bi bi-pencil-square"></i> Guardar Cambios</button>
                    <?php else: ?>
                        <button type="submit" name="crear_usuario" class="btn btn-primary btn-sm w-100 fw-bold"><i class="bi bi-person-plus-fill"></i> Crear Usuario</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- Listado de Usuarios -->
    <div class="col-md-8">
        <div class="card shadow-sm border-0 shadow-lg h-100">
            <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between align-items-center">
                <span>Usuarios Registrados en el Sistema</span>
                <span class="badge bg-primary fs-6 rounded-pill"><?= count($usuarios_list) ?> Total</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                    <table class="table table-hover table-striped mb-0" style="font-size: 0.9em;">
                        <thead class="table-light sticky-top shadow-sm">
                            <tr>
                                <th class="text-center text-muted" style="width: 5%;">#</th>
                                <th>Nombre y Cargo</th>
                                <th>Identidad / Correo</th>
                                <th class="text-center">Rol</th>
                                <th class="text-center" style="width: 15%;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($usuarios_list) === 0): ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted">No hay usuarios registrados.</td></tr>
                            <?php endif; ?>
                            
                            <?php $item = 1; foreach ($usuarios_list as $u): ?>
                            <tr>
                                <td class="text-center fw-bold text-muted align-middle"><?= $item++ ?></td>
                                <td class="align-middle">
                                    <div class="fw-bold text-uppercase text-dark"><?= htmlspecialchars($u['nombre_completo']) ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($u['cargo']) ?></div>
                                    <div class="text-primary small">User: <b><?= htmlspecialchars($u['usuario']) ?></b></div>
                                </td>
                                <td class="align-middle">
                                    <div class="fw-bold"><i class="bi bi-person-badge"></i> <?= htmlspecialchars($u['numero_identidad']) ?></div>
                                    <div class="text-muted small"><i class="bi bi-envelope"></i> <?= htmlspecialchars($u['correo_sena']) ?></div>
                                </td>
                                <td class="text-center align-middle">
                                    <?php if ($u['rol'] === 'admin'): ?>
                                        <span class="badge bg-danger px-2 py-1">ADMIN</span>
                                    <?php elseif ($u['rol'] === 'gestor'): ?>
                                        <span class="badge bg-primary px-2 py-1">GESTOR</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark px-2 py-1">VISUALIZADOR</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center align-middle">
                                    <div class="d-flex justify-content-center gap-1">
                                        <a href="?editar=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary border-0" title="Editar usuario">
                                            <i class="bi bi-pencil-square fs-6"></i>
                                        </a>

                                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                        <form method="POST" class="m-0" onsubmit="return confirm('¿Estás seguro de eliminar al usuario <?= addslashes($u['nombre_completo']) ?>?');">
                                            <input type="hidden" name="id_eliminar" value="<?= $u['id'] ?>">
                                            <button type="submit" name="eliminar_usuario_id" class="btn btn-sm btn-outline-danger border-0" title="Eliminar usuario">
                                                <i class="bi bi-trash3-fill fs-6"></i>
                                            </button>
                                        </form>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border align-self-center">Actual</span>
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

<?php require_once '../../includes/footer.php'; ?>