<?php
require_once '../../config/database.php';
require_once '../../includes/header.php';

$es_visualizador = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'visualizador');

if ($es_visualizador && $_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<div class='alert alert-danger fw-bold'>Acceso denegado: El rol visualizador no puede modificar convocatorias.</div>";
} else {
    // A) CREAR CONVOCATORIA
    if (isset($_POST['crear_convocatoria'])) {
        $nombre = strtoupper(trim($_POST['nombre']));
        $cupos = (int)$_POST['cupos'];
        $presupuesto = floatval($_POST['presupuesto']);
        
        if (!empty($nombre) && $cupos > 0) {
            try {
                $stmt = $pdo->prepare("INSERT INTO convocatorias (nombre, cupos, presupuesto) VALUES (?, ?, ?)");
                $stmt->execute([$nombre, $cupos, $presupuesto]);
                echo "<div class='alert alert-success mt-3 fw-bold'><i class='bi bi-check-circle-fill'></i> Convocatoria creada exitosamente.</div>";
            } catch (PDOException $e) {
                echo "<div class='alert alert-warning mt-3 fw-bold'>Error: Ya existe una convocatoria con ese nombre.</div>";
            }
        }
    }

    // B) ACTUALIZAR CONVOCATORIA
    if (isset($_POST['actualizar_convocatoria'])) {
        $id = $_POST['id_convocatoria'];
        $nombre = strtoupper(trim($_POST['nombre']));
        $cupos = (int)$_POST['cupos'];
        $presupuesto = floatval($_POST['presupuesto']);

        if (!empty($nombre) && $cupos > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE convocatorias SET nombre = ?, cupos = ?, presupuesto = ? WHERE id = ?");
                $stmt->execute([$nombre, $cupos, $presupuesto, $id]);
                echo "<div class='alert alert-success mt-3 fw-bold'><i class='bi bi-check-circle-fill'></i> Convocatoria actualizada correctamente.</div>";
                // Limpiar modo edición tras guardar
                echo "<script>window.location.href = 'index.php';</script>";
                exit;
            } catch (PDOException $e) {
                echo "<div class='alert alert-warning mt-3 fw-bold'>Error: Ya existe otra convocatoria con ese nombre.</div>";
            }
        }
    }

    // C) ELIMINAR CONVOCATORIA
    if (isset($_POST['eliminar_convocatoria'])) {
        $id = $_POST['id_convocatoria'];
        try {
            $stmt = $pdo->prepare("DELETE FROM convocatorias WHERE id = ?");
            $stmt->execute([$id]);
            echo "<div class='alert alert-success mt-3 fw-bold'><i class='bi bi-trash3-fill'></i> Convocatoria eliminada correctamente.</div>";
        } catch (PDOException $e) {
            echo "<div class='alert alert-danger mt-3 fw-bold'>No se puede eliminar porque tiene meses o aprendices asociados.</div>";
        }
    }
}

// Cargar datos para edición si se solicitó
$edit_mode = false;
$edit_data = null;
if (isset($_GET['editar'])) {
    $id_editar = $_GET['editar'];
    $stmtEdit = $pdo->prepare("SELECT * FROM convocatorias WHERE id = ?");
    $stmtEdit->execute([$id_editar]);
    $edit_data = $stmtEdit->fetch(PDO::FETCH_ASSOC);
    if ($edit_data) {
        $edit_mode = true;
    }
}

// Consultar todas las convocatorias
$stmtList = $pdo->query("SELECT * FROM convocatorias ORDER BY id DESC");
$convocatorias = $stmtList->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0"><i class="bi bi-folder-fill text-success"></i> Gestión de Convocatorias</h2>
        <p class="text-muted mb-0">Administra los cupos y presupuestos asignado</p>
    </div>
</div>

<div class="row mb-4">
    <?php if (!$es_visualizador): ?>
    <!-- Formulario Dinámico: Registrar o Editar Convocatoria -->
    <div class="col-md-4">
        <div class="card shadow-sm <?= $edit_mode ? 'border-warning' : 'border-success' ?> h-100">
            <div class="card-header <?= $edit_mode ? 'bg-warning text-dark' : 'bg-success text-white' ?> fw-bold d-flex justify-content-between align-items-center">
                <span><?= $edit_mode ? 'Editar Convocatoria' : 'Crear Nueva Convocatoria' ?></span>
                <?php if ($edit_mode): ?>
                    <a href="index.php" class="btn btn-sm btn-dark py-0 px-2" style="font-size: 0.75em;">Cancelar</a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="id_convocatoria" value="<?= $edit_data['id'] ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Nombre de la Convocatoria</label>
                        <input type="text" name="nombre" class="form-control form-control-sm text-uppercase" placeholder="Ej: CONVOCATORIA 01 - 2026" value="<?= $edit_mode ? htmlspecialchars($edit_data['nombre']) : '' ?>" required autocomplete="off">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Cupos Totales</label>
                        <input type="number" name="cupos" class="form-control form-control-sm" placeholder="Ej: 50" value="<?= $edit_mode ? $edit_data['cupos'] : '' ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Presupuesto Asignado ($)</label>
                        <input type="number" step="0.01" name="presupuesto" class="form-control form-control-sm" placeholder="Ej: 15000000" value="<?= $edit_mode ? $edit_data['presupuesto'] : '' ?>">
                    </div>

                    <?php if ($edit_mode): ?>
                        <button type="submit" name="actualizar_convocatoria" class="btn btn-warning btn-sm w-100 fw-bold text-dark"><i class="bi bi-pencil-square"></i> Guardar Cambios</button>
                    <?php else: ?>
                        <button type="submit" name="crear_convocatoria" class="btn btn-success btn-sm w-100 fw-bold"><i class="bi bi-plus-circle-fill"></i> Guardar Convocatoria</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Listado de Convocatorias -->
    <div class="<?= $es_visualizador ? 'col-md-12' : 'col-md-8' ?>">
        <div class="card shadow-sm border-0 shadow-lg">
            <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between align-items-center">
                <span>Convocatorias Registradas</span>
                <span class="badge bg-success fs-6 rounded-pill"><?= count($convocatorias) ?> Total</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width: 5%;">#</th>
                                <th>Nombre</th>
                                <th class="text-center" style="width: 15%;">Cupos</th>
                                <th class="text-end" style="width: 25%;">Presupuesto</th>
                                <?php if (!$es_visualizador): ?><th class="text-center" style="width: 15%;">Acciones</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($convocatorias) === 0): ?>
                                <tr><td colspan="<?= $es_visualizador ? '4' : '5' ?>" class="text-center py-4 text-muted">No hay convocatorias creadas.</td></tr>
                            <?php endif; ?>

                            <?php $item = 1; foreach ($convocatorias as $c): ?>
                            <tr>
                                <td class="text-center fw-bold text-muted align-middle"><?= $item++ ?></td>
                                <td class="fw-bold align-middle text-uppercase"><?= htmlspecialchars($c['nombre']) ?></td>
                                <td class="text-center fw-bold align-middle"><span class="badge bg-secondary"><?= $c['cupos'] ?> Cupos</span></td>
                                <td class="text-end fw-bold text-success align-middle">
                                    <?= $c['presupuesto'] > 0 ? '$'.number_format($c['presupuesto'], 2, ',', '.') : '<span class="text-muted fw-normal">Sin asignar</span>' ?>
                                </td>
                                
                                <?php if (!$es_visualizador): ?>
                                <td class="text-center align-middle">
                                    <div class="d-flex justify-content-center gap-1">
                                        <!-- Botón Editar -->
                                        <a href="?editar=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary border-0" title="Editar convocatoria">
                                            <i class="bi bi-pencil-square fs-6"></i>
                                        </a>

                                        <!-- Botón Eliminar -->
                                        <form method="POST" class="m-0" onsubmit="return confirm('¿Estás seguro de eliminar esta convocatoria?');">
                                            <input type="hidden" name="id_convocatoria" value="<?= $c['id'] ?>">
                                            <button type="submit" name="eliminar_convocatoria" class="btn btn-sm btn-outline-danger border-0" title="Eliminar convocatoria">
                                                <i class="bi bi-trash3-fill fs-6"></i>
                                            </button>
                                        </form>
                                    </div>
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