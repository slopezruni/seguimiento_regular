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

// Procesar creación de nuevo apoyo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_apoyo'])) {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);

    if (!empty($nombre)) {
        $stmt = $pdo->prepare("INSERT INTO tipos_apoyo (nombre, descripcion) VALUES (?, ?)");
        $stmt->execute([$nombre, $descripcion]);
        $mensaje = "<div class='alert alert-success fw-bold'>¡Apoyo socioeconómico creado con éxito!</div>";
    } else {
        $mensaje = "<div class='alert alert-danger fw-bold'>El nombre del apoyo es obligatorio.</div>";
    }
}

// Obtener lista de apoyos
$apoyos = $pdo->query("SELECT * FROM tipos_apoyo ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="bi bi-collection-fill text-success"></i> Gestión de Apoyos Socioeconómicos</h2>
            <p class="text-muted mb-0">Crea y administra los diferentes tipos de apoyos institucionales.</p>
        </div>
    </div>

    <?= $mensaje ?>

    <div class="row">
        <!-- Formulario de Creación -->
        <div class="col-md-4">
            <div class="card shadow-sm border-success mb-4">
                <div class="card-header bg-success text-white fw-bold">Crear Nuevo Apoyo</div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nombre del Apoyo</label>
                            <input type="text" name="nombre" class="form-control" placeholder="Ej: Apoyo de Transporte" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Descripción</label>
                            <textarea name="descripcion" class="form-control" rows="3" placeholder="Breve descripción de los requisitos o enfoque..."></textarea>
                        </div>
                        <button type="submit" name="crear_apoyo" class="btn btn-success w-100 fw-bold">
                            <i class="bi bi-plus-circle-fill"></i> Registrar Apoyo
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Listado de Apoyos Existentes -->
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white fw-bold">Apoyos Registrados en el Sistema</div>
                <div class="card-body p-0">
                    <table class="table table-striped mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Nombre del Apoyo</th>
                                <th>Descripción</th>
                                <th class="text-center">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($apoyos as $ap): ?>
                                <tr>
                                    <td class="fw-bold"><?= $ap['id'] ?></td>
                                    <td class="fw-bold text-success fs-6"><?= htmlspecialchars($ap['nombre']) ?></td>
                                    <td class="text-muted small"><?= htmlspecialchars($ap['descripcion'] ?? 'Sin descripción') ?></td>
                                    <td class="text-center"><span class="badge bg-success">Activo</span></td>
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