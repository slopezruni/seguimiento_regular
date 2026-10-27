<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /seguimiento-regular/login.php');
    exit;
}

$rol_actual = $_SESSION['rol'] ?? 'visualizador';
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';

if (isset($pdo)) {
    // 1. Filtrado de apoyos según el rol
    if ($rol_actual === 'admin') {
        $stmtApoyos = $pdo->query("SELECT id, nombre FROM tipos_apoyo WHERE estado = 'activo' ORDER BY id ASC");
        $lista_apoyos = $stmtApoyos->fetchAll(PDO::FETCH_ASSOC);

        if (isset($_GET['apoyo_id'])) {
            $_SESSION['apoyo_activo_id'] = (int)$_GET['apoyo_id'];
        }
        if (!isset($_SESSION['apoyo_activo_id']) && count($lista_apoyos) > 0) {
            $_SESSION['apoyo_activo_id'] = $lista_apoyos[0]['id'];
        }
        $apoyo_activo_id = $_SESSION['apoyo_activo_id'] ?? 1;
    } else {
        // Para gestor o visualizador: fijado al apoyo asignado en base de datos
        $apoyo_asignado = (int)($_SESSION['apoyo_usuario'] ?? 1);
        $stmtApoyos = $pdo->prepare("SELECT id, nombre FROM tipos_apoyo WHERE id = ? AND estado = 'activo'");
        $stmtApoyos->execute([$apoyo_asignado]);
        $lista_apoyos = $stmtApoyos->fetchAll(PDO::FETCH_ASSOC);

        $_SESSION['apoyo_activo_id'] = $apoyo_asignado;
        $apoyo_activo_id = $apoyo_asignado;
    }

    // 2. Convocatorias filtradas estrictamente por el apoyo activo
    $stmtSidebar = $pdo->prepare("SELECT id, nombre FROM convocatorias WHERE apoyo_id = ? ORDER BY id DESC");
    $stmtSidebar->execute([$apoyo_activo_id]);
    $lista_convocatorias = $stmtSidebar->fetchAll(PDO::FETCH_ASSOC);
} else {
    $lista_apoyos = [];
    $lista_convocatorias = [];
    $apoyo_activo_id = 1;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seguimiento Apoyos Socioeconómicos SENA</title>
    <link rel="icon" type="image/webp" href="/seguimiento-regular/assets/img/logo_sena.webp">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/seguimiento-regular/assets/css/sena.css">
</head>
<body>
<div class="d-flex" id="wrapper">
    <div class="text-white shadow" id="sidebar-wrapper">
        
        <!-- LOGO INSTITUCIONAL LOCAL -->
        <div class="py-3 text-center border-bottom border-secondary mb-3 bg-black bg-opacity-25 px-2">
            <div class="d-flex align-items-center justify-content-center gap-2 mb-1">
                <img src="/seguimiento-regular/assets/img/logo_sena.webp" alt="Logo SENA" style="width: 35px; height: auto;">
                <span class="fw-bold fs-4 text-success m-0">SENA</span>
            </div>
            <div class="text-uppercase text-light small fw-bold" style="font-size: 0.65em; letter-spacing: 1px; opacity: 0.85;">
                Apoyo Socioeconómico
            </div>
        </div>

        <!-- SELECTOR DE APOYO -->
        <div class="px-3 mb-3">
            <label class="form-label text-success small fw-bold mb-1">
                <i class="bi bi-filter-circle"></i> <?= $rol_actual === 'admin' ? 'Cambiar Apoyo:' : 'Apoyo Asignado:' ?>
            </label>
            <?php if ($rol_actual === 'admin'): ?>
                <select class="form-select form-select-sm fw-bold bg-dark text-white border-secondary" onchange="location.href='?apoyo_id='+this.value">
                    <?php foreach ($lista_apoyos as $ap): ?>
                        <option value="<?= $ap['id'] ?>" <?= $ap['id'] == $apoyo_activo_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ap['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <div class="p-2 bg-dark rounded border border-secondary text-white fw-bold small text-truncate">
                    <?= !empty($lista_apoyos) ? htmlspecialchars($lista_apoyos[0]['nombre']) : 'Sin Apoyo Asignado' ?>
                </div>
            <?php endif; ?>
        </div>
        
        <a href="/seguimiento-regular/index.php" class="sidebar-link border-bottom border-secondary pb-3">
            <i class="bi bi-house-door-fill me-2 text-success"></i> Inicio (Dashboard)
        </a>
        
        <a href="/seguimiento-regular/modules/convocatorias/index.php" class="sidebar-link mt-2">
            <i class="bi bi-folder-fill me-2 text-success"></i> Convocatorias
        </a>

        <div class="mt-2">
            <a href="#menuSeguimiento" data-bs-toggle="collapse" class="sidebar-link d-flex justify-content-between align-items-center">
                <span><i class="bi bi-calendar-check-fill me-2 text-success"></i> Seguimientos</span>
                <i class="bi bi-chevron-down"></i>
            </a>
            <div class="collapse show" id="menuSeguimiento">
                <?php if(count($lista_convocatorias) === 0): ?>
                    <span class="small text-muted px-3 py-1 d-block">Sin convocatorias en este apoyo.</span>
                <?php endif; ?>
                <?php foreach ($lista_convocatorias as $c): ?>
                    <a href="/seguimiento-regular/modules/seguimiento/index.php?convocatoria_id=<?= $c['id'] ?>" class="sidebar-link submenu-link">
                        <i class="bi bi-caret-right-fill me-1 text-success"></i> <?= htmlspecialchars($c['nombre']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mt-2">
            <a href="/seguimiento-regular/modules/fichas/index.php" class="sidebar-link">
                <i class="bi bi-journal-text me-2 text-success"></i> Distribución Fichas
            </a>
        </div>

        <div class="mt-2">
            <a href="/seguimiento-regular/modules/fic/index.php" class="sidebar-link">
                <i class="bi bi-cone-striped me-2 text-success"></i> Base de Datos FIC
            </a>
        </div>

        <div class="mt-2">
            <a href="/seguimiento-regular/modules/ciberbanco/index.php" class="sidebar-link">
                <i class="bi bi-bank2 me-2 text-success"></i> Ciberbanco
            </a>
        </div>

        <?php if ($rol_actual === 'admin'): ?>
        <div class="mt-2">
            <a href="/seguimiento-regular/modules/apoyos/index.php" class="sidebar-link border-top border-secondary pt-3">
                <i class="bi bi-collection-fill me-2 text-success"></i> Gestión de Apoyos
            </a>
        </div>

        <div class="mt-2">
            <a href="/seguimiento-regular/modules/usuarios/index.php" class="sidebar-link">
                <i class="bi bi-people-fill me-2 text-success"></i> Gestión de Usuarios
            </a>
        </div>
        <?php endif; ?>
    </div>
    
    <div id="page-content-wrapper" class="w-100 bg-light">
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom px-4 py-2 shadow-sm mb-4">
            <div class="container-fluid d-flex justify-content-between align-items-center">
                <div class="text-secondary small fw-bold">
                    <span class="text-success"><i class="bi bi-patch-check-fill"></i> Servicio Nacional de Aprendizaje</span> - Regional Valle
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="text-secondary small">
                        <i class="bi bi-person-circle fs-5 me-1 text-success"></i> 
                        <b><?= htmlspecialchars($nombre_usuario) ?></b> 
                        <span class="badge bg-<?= $rol_actual === 'admin' ? 'danger' : ($rol_actual === 'gestor' ? 'success' : 'warning text-dark') ?> text-uppercase ms-1">
                            <?= $rol_actual ?>
                        </span>
                    </span>
                    <a href="/seguimiento-regular/logout.php" class="btn btn-outline-danger btn-sm fw-bold">
                        <i class="bi bi-box-arrow-right"></i> Salir
                    </a>
                </div>
            </div>
        </nav>

        <div class="container-fluid px-4 py-2">