<?php
require_once 'config/database.php';
require_once 'includes/header.php';

// 1. Estadísticas globales
$totalConvocatorias = $pdo->query("SELECT COUNT(*) FROM convocatorias")->fetchColumn();
$totalFichas = $pdo->query("SELECT COUNT(*) FROM distribucion_fichas")->fetchColumn();
$totalFicGlobal = $pdo->query("SELECT COUNT(*) FROM base_fic")->fetchColumn();
$totalUsuarios = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();

// Total de aprendices beneficiarios únicos registrados en las convocatorias (meses de seguimiento)
$totalBeneficiariosConvocatorias = $pdo->query("SELECT COUNT(DISTINCT documento) FROM aprendices_mes")->fetchColumn();

// 2. Lógica financiera en tiempo real
$presupuestoTotal = $pdo->query("SELECT SUM(presupuesto) FROM convocatorias")->fetchColumn() ?: 0;
$ejecucionTotal = $pdo->query("SELECT SUM(valor_pagado) FROM meses_seguimiento")->fetchColumn() ?: 0;
$porcentajeEjecucion = ($presupuestoTotal > 0) ? min(100, round(($ejecucionTotal / $presupuestoTotal) * 100, 1)) : 0;

// Desglose financiero por convocatoria
$stmtFinanzas = $pdo->query("
    SELECT c.id, c.nombre, c.cupos, c.presupuesto, 
           COALESCE(SUM(m.valor_pagado), 0) AS total_ejecutado
    FROM convocatorias c
    LEFT JOIN meses_seguimiento m ON c.id = m.convocatoria_id
    GROUP BY c.id, c.nombre, c.cupos, c.presupuesto
    ORDER BY c.id DESC
");
$desgloseConvocatorias = $stmtFinanzas->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Sección Hero Institucional -->
<div class="hero mb-4">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1 class="fw-bold mb-2">¡Bienvenido, <?= htmlspecialchars($_SESSION['nombre']) ?>!</h1>
            <p class="lead mb-0 text-white-100">Sistema automatizado de control, seguimiento y gestión de apoyos de sostenimiento regular.</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <span class="badge bg-white text-success px-3 py-2 fs-6 fw-bold shadow-sm">
                <i class="bi bi-shield-fill-check me-1"></i> Rol: <?= strtoupper(htmlspecialchars($_SESSION['rol'])) ?>
            </span>
        </div>
    </div>
</div>

<!-- Tarjetas de Estadísticas Principales -->
<div class="row g-4 mb-4">
    <!-- Convocatorias -->
    <div class="col-xl-3 col-md-6">
        <div class="card bg-white border-start border-4 border-success h-100 py-2">
            <div class="card-body">
                <div class="text-uppercase fw-bold text-muted small mb-1">Convocatorias Activas</div>
                <div class="stat text-dark"><?= $totalConvocatorias ?></div>
                <div class="mt-2 text-success small fw-bold">
                    <i class="bi bi-folder-fill me-1"></i> Gestion de convocatoria
                </div>
            </div>
        </div>
    </div>

    <!-- Fichas -->
    <div class="col-xl-3 col-md-6">
        <div class="card bg-white border-start border-4 border-info h-100 py-2">
            <div class="card-body">
                <div class="text-uppercase fw-bold text-muted small mb-1">Fichas Registradas</div>
                <div class="stat text-dark"><?= $totalFichas ?></div>
                <div class="mt-2 text-info small fw-bold">
                    <i class="bi bi-journal-text me-1"></i> Fechas en lectiva
                </div>
            </div>
        </div>
    </div>

    <!-- Base FIC -->
    <div class="col-xl-3 col-md-6">
        <div class="card bg-white border-start border-4 border-danger h-100 py-2">
            <div class="card-body">
                <div class="text-uppercase fw-bold text-muted small mb-1">Beneficiarios FIC</div>
                <div class="stat text-dark"><?= $totalFicGlobal ?></div>
                <div class="mt-2 text-danger small fw-bold">
                    <i class="bi bi-cone-striped me-1"></i> Cruce automático
                </div>
            </div>
        </div>
    </div>

    <!-- Aprendices Beneficiarios en Convocatorias -->
    <div class="col-xl-3 col-md-6">
        <div class="card bg-white border-start border-4 border-warning h-100 py-2">
            <div class="card-body">
                <div class="text-uppercase fw-bold text-muted small mb-1">Beneficiarios en Convocatorias</div>
                <div class="stat text-dark"><?= $totalBeneficiariosConvocatorias ?></div>
                <div class="mt-2 text-warning text-dark fw-bold small">
                    <i class="bi bi-people-fill me-1"></i> Aprendices activos en seguimiento
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ================================================================= -->
<!-- PANEL INTERACTIVO DE EJECUCIÓN FINANCIERA EN VIVO -->
<!-- ================================================================= -->
<div class="card bg-white mb-4 shadow-sm">
    <div class="card-header bg-success text-white fw-bold py-3 d-flex justify-content-between align-items-center">
        <span><i class="bi bi-cash-stack me-2"></i> Ejecución presupuestal total en Vivo</span>
        <span class="badge bg-white text-success fs-6 fw-bold">Progreso: <?= $porcentajeEjecucion ?>%</span>
    </div>
    <div class="card-body">
        <div class="row align-items-center mb-4">
            <div class="col-md-4 text-center border-end">
                <span class="text-muted small fw-bold text-uppercase d-block mb-1">Presupuesto Total Asignado</span>
                <h3 class="fw-bold text-dark mb-0">$<?= number_format($presupuestoTotal, 2, ',', '.') ?></h3>
            </div>
            <div class="col-md-4 text-center border-end">
                <span class="text-muted small fw-bold text-uppercase d-block mb-1">Total Ejecutado / Pagado</span>
                <h3 class="fw-bold text-success mb-0">$<?= number_format($ejecucionTotal, 2, ',', '.') ?></h3>
            </div>
            <div class="col-md-4 text-center">
                <span class="text-muted small fw-bold text-uppercase d-block mb-1">Saldo por Ejecutar</span>
                <h3 class="fw-bold text-danger mb-0">$<?= number_format($presupuestoTotal - $ejecucionTotal, 2, ',', '.') ?></h3>
            </div>
        </div>

        <!-- Barra de Progreso Interactiva -->
        <div class="mb-2 d-flex justify-content-between small fw-bold text-muted">
            <span>0%</span>
            <span>Meta de Ejecución</span>
            <span>100%</span>
        </div>
        <div class="progress" style="height: 20px; border-radius: 10px;">
            <div class="progress-bar bg-success progress-bar-striped progress-bar-animated fw-bold" 
                 role="progressbar" 
                 style="width: <?= $porcentajeEjecucion ?>%;" 
                 aria-valuenow="<?= $porcentajeEjecucion ?>" 
                 aria-valuemin="0" 
                 aria-valuemax="100">
                <?= $porcentajeEjecucion ?>% Ejecutado
            </div>
        </div>
    </div>
</div>

<!-- Desglose Financiero por Convocatoria -->
<div class="card bg-white mb-4 shadow-sm">
    <div class="card-header bg-dark text-white fw-bold py-3">
        <i class="bi bi-bar-chart-fill me-2 text-success"></i>Ejecución por Convocatoria
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Convocatoria</th>
                        <th class="text-center">Cupos</th>
                        <th class="text-end">Presupuesto Asignado</th>
                        <th class="text-end">Total Ejecutado</th>
                        <th class="text-center" style="width: 25%;">% Ejecución</th>
                        <th class="text-center">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($desgloseConvocatorias) === 0): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No hay convocatorias registradas para mostrar ejecución.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($desgloseConvocatorias as $conv): 
                        $presup_conv = (float)$conv['presupuesto'];
                        $ejec_conv = (float)$conv['total_ejecutado'];
                        $porc_conv = ($presup_conv > 0) ? min(100, round(($ejec_conv / $presup_conv) * 100, 1)) : 0;
                    ?>
                    <tr>
                        <td class="ps-4 fw-bold text-uppercase"><?= htmlspecialchars($conv['nombre']) ?></td>
                        <td class="text-center"><span class="badge bg-secondary"><?= $conv['cupos'] ?></span></td>
                        <td class="text-end fw-bold">
                            <?= $presup_conv > 0 ? '$'.number_format($presup_conv, 2, ',', '.') : '<span class="text-muted fw-normal">Sin asignar</span>' ?>
                        </td>
                        <td class="text-end fw-bold text-success">$<?= number_format($ejec_conv, 2, ',', '.') ?></td>
                        <td class="text-center align-middle">
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress w-100" style="height: 10px;">
                                    <div class="progress-bar bg-<?= $porc_conv >= 100 ? 'success' : ($porc_conv > 50 ? 'info' : 'warning') ?>" 
                                         style="width: <?= $porc_conv ?>%;"></div>
                                </div>
                                <span class="small fw-bold text-muted"><?= $porc_conv ?>%</span>
                            </div>
                        </td>
                        <td class="text-center">
                            <a href="modules/seguimiento/index.php?convocatoria_id=<?= $conv['id'] ?>" class="btn btn-sm btn-outline-success fw-bold">
                                Ver Planilla <i class="bi bi-arrow-right"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>