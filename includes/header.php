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
$uri_actual = $_SERVER['REQUEST_URI'] ?? '';

if (isset($pdo)) {
    // 1. Filtrado de apoyos según el rol (Misma lógica intacta)
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
        $apoyo_asignado = (int)($_SESSION['apoyo_usuario'] ?? 1);
        $stmtApoyos = $pdo->prepare("SELECT id, nombre FROM tipos_apoyo WHERE id = ? AND estado = 'activo'");
        $stmtApoyos->execute([$apoyo_asignado]);
        $lista_apoyos = $stmtApoyos->fetchAll(PDO::FETCH_ASSOC);

        $_SESSION['apoyo_activo_id'] = $apoyo_asignado;
        $apoyo_activo_id = $apoyo_asignado;
    }

    // Obtener nombre del apoyo activo para mostrar en el cintillo superior
    $nombre_apoyo_header = "Apoyo Socioeconómico";
    foreach ($lista_apoyos as $ap_item) {
        if ((int)$ap_item['id'] === (int)$apoyo_activo_id) {
            $nombre_apoyo_header = $ap_item['nombre'];
            break;
        }
    }

    // 2. Convocatorias filtradas estrictamente por el apoyo activo
    $stmtSidebar = $pdo->prepare("SELECT id, nombre FROM convocatorias WHERE apoyo_id = ? ORDER BY id DESC");
    $stmtSidebar->execute([$apoyo_activo_id]);
    $lista_convocatorias = $stmtSidebar->fetchAll(PDO::FETCH_ASSOC);
} else {
    $lista_apoyos = [];
    $lista_convocatorias = [];
    $apoyo_activo_id = 1;
    $nombre_apoyo_header = "Apoyo Socioeconómico";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SENA | Gestión de Apoyos Socioeconómicos</title>
    <link rel="icon" type="image/webp" href="/seguimiento-regular/assets/img/logo_sena.webp">
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <!-- Chart.js para gráficos interactivos en el Dashboard -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Estilos Institucionales Renovados -->
    <link rel="stylesheet" href="/seguimiento-regular/assets/css/sena.css?v=2.0">
</head>
<body>
<div class="d-flex" id="wrapper">
    <!-- SIDEBAR INSTITUCIONAL -->
    <div class="text-white shadow" id="sidebar-wrapper">
        
        <!-- CABECERA LOGO SENA -->
        <div class="sidebar-brand-box text-center">
            <div class="d-flex align-items-center justify-content-center gap-2">
                <div class="bg-white rounded-circle p-1 d-flex align-items-center justify-content-center shadow-sm" style="width: 42px; height: 42px;">
                    <img src="/seguimiento-regular/assets/img/logo_sena.webp" alt="Logo SENA" style="width: 30px; height: auto;">
                </div>
                <div class="text-start">
                    <div class="fw-bolder fs-5 text-white lh-1">SENA</div>
                    <span class="badge bg-success mt-1" style="font-size: 0.62rem; letter-spacing: 0.5px;">REGIONAL VALLE</span>
                </div>
            </div>
        </div>

        <!-- SELECTOR DE CONTEXTO DE APOYO -->
        <div class="apoyo-selector-box">
            <label class="form-label text-white-50 mb-1 d-flex align-items-center justify-content-between" style="font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.5px;">
                <span><i class="bi bi-layers-fill text-success me-1"></i> Programa Activo</span>
            </label>
            <?php if ($rol_actual === 'admin'): ?>
                <select class="form-select form-select-sm fw-bold bg-dark text-white border-secondary shadow-none" onchange="location.href='?apoyo_id='+this.value">
                    <?php foreach ($lista_apoyos as $ap): ?>
                        <option value="<?= $ap['id'] ?>" <?= $ap['id'] == $apoyo_activo_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ap['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <div class="p-2 bg-dark bg-opacity-50 rounded border border-secondary text-white fw-bold small text-truncate" title="<?= !empty($lista_apoyos) ? htmlspecialchars($lista_apoyos[0]['nombre']) : 'Sin Apoyo Asignado' ?>">
                    <i class="bi bi-check2-circle text-success me-1"></i>
                    <?= !empty($lista_apoyos) ? htmlspecialchars($lista_apoyos[0]['nombre']) : 'Sin Apoyo Asignado' ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- NAVEGACIÓN PRINCIPAL -->
        <div class="flex-grow-1 overflow-auto py-1">
            <div class="px-3 py-1 text-white-50 fw-bold" style="font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.8px;">
                Módulos Operativos
            </div>

            <a href="/seguimiento-regular/index.php" class="sidebar-link <?= (strpos($uri_actual, '/seguimiento-regular/index.php') !== false || $uri_actual === '/seguimiento-regular/') ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2-fill me-2 text-success"></i> Dashboard General
            </a>
            
            <a href="/seguimiento-regular/modules/convocatorias/index.php" class="sidebar-link <?= strpos($uri_actual, '/modules/convocatorias/') !== false ? 'active' : '' ?>">
                <i class="bi bi-folder2-open me-2 text-success"></i> Convocatorias
            </a>

            <!-- SUBMENÚ DESPLEGABLE DE SEGUIMIENTOS / PLANILLAS -->
            <div class="mt-1">
                <a href="#menuSeguimiento" data-bs-toggle="collapse" class="sidebar-link d-flex justify-content-between align-items-center <?= strpos($uri_actual, '/modules/seguimiento/') !== false ? 'active' : '' ?>">
                    <span><i class="bi bi-table me-2 text-success"></i> Seguimientos y Planillas</span>
                    <i class="bi bi-chevron-down small"></i>
                </a>
                <div class="collapse show" id="menuSeguimiento">
                    <?php if(count($lista_convocatorias) === 0): ?>
                        <span class="small text-white-50 px-4 py-1 d-block" style="font-size: 0.75rem;">Sin convocatorias creadas.</span>
                    <?php endif; ?>
                    <?php foreach ($lista_convocatorias as $c): 
                        $conv_activa = (strpos($uri_actual, '/modules/seguimiento/') !== false && isset($_GET['convocatoria_id']) && $_GET['convocatoria_id'] == $c['id']);
                    ?>
                        <a href="/seguimiento-regular/modules/seguimiento/index.php?convocatoria_id=<?= $c['id'] ?>" class="sidebar-link submenu-link <?= $conv_activa ? 'active' : '' ?>">
                            <i class="bi bi-arrow-return-right me-2 text-success"></i> 
                            <span class="text-truncate"><?= htmlspecialchars($c['nombre']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <a href="/seguimiento-regular/modules/fichas/index.php" class="sidebar-link <?= strpos($uri_actual, '/modules/fichas/') !== false ? 'active' : '' ?>">
                <i class="bi bi-diagram-3-fill me-2 text-success"></i> Distribución Fichas
            </a>

            <a href="/seguimiento-regular/modules/fic/index.php" class="sidebar-link <?= strpos($uri_actual, '/modules/fic/') !== false ? 'active' : '' ?>">
                <i class="bi bi-building-fill-gear me-2 text-success"></i> Base de Datos FIC
            </a>

            <a href="/seguimiento-regular/modules/ciberbanco/index.php" class="sidebar-link <?= strpos($uri_actual, '/modules/ciberbanco/') !== false ? 'active' : '' ?>">
                <i class="bi bi-bank2 me-2 text-success"></i> Ciberbanco
            </a>

            <?php if ($rol_actual === 'admin'): ?>
                <div class="px-3 pt-3 pb-1 text-white-50 fw-bold" style="font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.8px;">
                    Administración
                </div>
                <a href="/seguimiento-regular/modules/apoyos/index.php" class="sidebar-link <?= strpos($uri_actual, '/modules/apoyos/') !== false ? 'active' : '' ?>">
                    <i class="bi bi-collection-fill me-2 text-success"></i> Gestión de Apoyos
                </a>
                <a href="/seguimiento-regular/modules/usuarios/index.php" class="sidebar-link <?= strpos($uri_actual, '/modules/usuarios/') !== false ? 'active' : '' ?>">
                    <i class="bi bi-people-fill me-2 text-success"></i> Usuarios y Roles
                </a>
            <?php endif; ?>
        </div>

        <!-- PIE DEL SIDEBAR -->
        <div class="p-3 border-top border-secondary border-opacity-25 small text-white-50 text-center" style="font-size: 0.72rem;">
            Bienestar al Aprendiz &bull; CEAI
        </div>
    </div>
    
    <!-- CONTENIDO PRINCIPAL -->
    <div id="page-content-wrapper">
        <nav class="navbar navbar-expand-lg navbar-light top-navbar px-4 py-2 mb-3">
            <div class="container-fluid px-0 d-flex justify-content-between align-items-center">
                
                <!-- Botón Colapsar Menú + Indicador de Contexto -->
                <div class="d-flex align-items-center gap-3">
                    <button class="btn-toggle-sidebar shadow-sm" id="sidebarToggle" title="Mostrar / Ocultar menú lateral">
                        <i class="bi bi-layout-sidebar-inset fs-5"></i>
                    </button>
                    <div class="d-none d-sm-block">
                        <div class="text-muted" style="font-size: 0.72rem; font-weight: 600; text-transform: uppercase;">Contexto de Trabajo Actual</div>
                        <div class="fw-bold text-dark d-flex align-items-center gap-2">
                            <span class="badge bg-success rounded-pill px-2 py-1"><i class="bi bi-check-circle-fill me-1"></i><?= htmlspecialchars($nombre_apoyo_header) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Perfil del Usuario y Salida -->
                <div class="d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center bg-light border rounded-pill px-3 py-1">
                        <i class="bi bi-person-badge-fill fs-5 me-2 text-success"></i>
                        <div class="lh-1 me-2">
                            <div class="fw-bold text-dark" style="font-size: 0.8rem;"><?= htmlspecialchars($nombre_usuario) ?></div>
                        </div>
                        <span class="badge rounded-pill bg-<?= $rol_actual === 'admin' ? 'danger' : ($rol_actual === 'gestor' ? 'success' : 'warning text-dark') ?> text-uppercase" style="font-size: 0.65rem;">
                            <?= htmlspecialchars($rol_actual) ?>
                        </span>
                    </div>

                    <a href="/seguimiento-regular/logout.php" class="btn btn-outline-danger btn-sm fw-bold rounded-pill px-3">
                        <i class="bi bi-power"></i> <span class="d-none d-md-inline">Salir</span>
                    </a>
                </div>
            </div>
        </nav>

        <div class="container-fluid px-4 pb-4 flex-grow-1">