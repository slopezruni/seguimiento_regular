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
    $stmtSidebar = $pdo->query("SELECT id, nombre FROM convocatorias ORDER BY id DESC");
    $lista_convocatorias = $stmtSidebar->fetchAll(PDO::FETCH_ASSOC);
} else {
    $lista_convocatorias = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seguimiento Apoyo Regular</title>
    <!-- Favicon Local WEBP -->
    <link rel="icon" type="image/webp" href="/seguimiento-regular/assets/img/logo_sena.webp">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- Estilos Institucionales SENA -->
    <link rel="stylesheet" href="/seguimiento-regular/assets/css/sena.css">
</head>
<body>
<div class="d-flex" id="wrapper">
    <div class="text-white shadow" id="sidebar-wrapper">
        
        <!-- LOGO INSTITUCIONAL LOCAL (.WEBP) -->
        <div class="py-3 text-center border-bottom border-secondary mb-3 bg-black bg-opacity-25 px-2">
            <div class="d-flex align-items-center justify-content-center gap-2 mb-1">
                <img src="/seguimiento-regular/assets/img/logo_sena.webp" alt="Logo SENA" style="width: 35px; height: auto;">
                <span class="fw-bold fs-4 text-success m-0">SENA</span>
            </div>
            <div class="text-uppercase text-light small fw-bold" style="font-size: 0.65em; letter-spacing: 1px; opacity: 0.85;">
                Apoyo de Sostenimiento
            </div>
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

        <!-- MÓDULO CIBERBANCO EN EL SIDEBAR -->
        <div class="mt-2">
            <a href="/seguimiento-regular/modules/ciberbanco/index.php" class="sidebar-link">
                <i class="bi bi-bank2 me-2 text-success"></i> Ciberbanco
            </a>
        </div>

        <?php if ($rol_actual === 'admin'): ?>
        <div class="mt-2">
            <a href="/seguimiento-regular/modules/usuarios/index.php" class="sidebar-link border-top border-secondary pt-3">
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