<?php
require_once 'config/database.php';
require_once 'includes/header.php';

// Contexto del apoyo activo seleccionado en el menú lateral
$apoyo_activo_id = $_SESSION['apoyo_activo_id'] ?? 1;

// 1. Estadísticas globales (Filtrando convocatorias por el apoyo activo)
$stmtTotalConv = $pdo->prepare("SELECT COUNT(*) FROM convocatorias WHERE apoyo_id = ?");
$stmtTotalConv->execute([$apoyo_activo_id]);
$totalConvocatorias = $stmtTotalConv->fetchColumn() ?: 0;

$totalFichas = $pdo->query("SELECT COUNT(*) FROM distribucion_fichas")->fetchColumn() ?: 0;
$totalFicGlobal = $pdo->query("SELECT COUNT(*) FROM base_fic")->fetchColumn() ?: 0;
$totalUsuarios = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn() ?: 0;

// Total de aprendices beneficiarios únicos registrados en las convocatorias (meses de seguimiento)
$totalBeneficiariosConvocatorias = $pdo->query("SELECT COUNT(DISTINCT documento) FROM aprendices_mes")->fetchColumn() ?: 0;

// 2. Lógica financiera en tiempo real (Filtrada por el apoyo activo para aislar presupuestos)
$stmtPresup = $pdo->prepare("SELECT SUM(presupuesto) FROM convocatorias WHERE apoyo_id = ?");
$stmtPresup->execute([$apoyo_activo_id]);
$presupuestoTotal = (float)($stmtPresup->fetchColumn() ?: 0);

$stmtEjec = $pdo->prepare("
    SELECT SUM(m.valor_pagado) 
    FROM meses_seguimiento m
    INNER JOIN convocatorias c ON m.convocatoria_id = c.id
    WHERE c.apoyo_id = ?
");
$stmtEjec->execute([$apoyo_activo_id]);
$ejecucionTotal = (float)($stmtEjec->fetchColumn() ?: 0);

$saldoTotalDisponible = $presupuestoTotal - $ejecucionTotal;
$porcentajeEjecucion = ($presupuestoTotal > 0) ? min(100, round(($ejecucionTotal / $presupuestoTotal) * 100, 1)) : 0;

// Desglose financiero por convocatoria para la tabla
$stmtFinanzas = $pdo->prepare("
    SELECT c.id, c.nombre, c.cupos, c.presupuesto, 
           COALESCE(SUM(m.valor_pagado), 0) AS total_ejecutado
    FROM convocatorias c
    LEFT JOIN meses_seguimiento m ON c.id = m.convocatoria_id
    WHERE c.apoyo_id = ?
    GROUP BY c.id, c.nombre, c.cupos, c.presupuesto
    ORDER BY c.id DESC
");
$stmtFinanzas->execute([$apoyo_activo_id]);
$desgloseConvocatorias = $stmtFinanzas->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    .hero-dashboard {
        background: linear-gradient(135deg, #00324D 0%, #004e75 55%, #39A900 100%);
        border-radius: 16px;
        padding: 1.8rem 2rem;
        color: #ffffff;
        position: relative;
        overflow: hidden;
        box-shadow: 0 10px 25px -5px rgba(0, 50, 77, 0.25);
    }
    .hero-dashboard::after {
        content: "";
        position: absolute;
        right: -40px;
        bottom: -60px;
        width: 240px;
        height: 240px;
        background: rgba(255, 255, 255, 0.06);
        border-radius: 50%;
        pointer-events: none;
    }
    .kpi-icon-box {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
    }
    .kpi-value {
        font-size: 1.85rem;
        font-weight: 800;
        line-height: 1.1;
        color: #0F172A;
    }
    .kpi-card-link {
        text-decoration: none;
        color: inherit;
        display: block;
    }
    .finance-stat-box {
        background: #F8FAFC;
        border: 1px solid #E2E8F0;
        border-radius: 12px;
        padding: 1rem 1.2rem;
        transition: all 0.2s ease;
    }
    .finance-stat-box:hover {
        background: #ffffff;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
    }
</style>

<!-- 1. SECCIÓN HERO INSTITUCIONAL -->
<div class="hero-dashboard mb-4">
    <div class="row align-items-center">
        <div class="col-lg-7">
            <div class="d-inline-flex align-items-center gap-2 bg-white bg-opacity-10 border border-light border-opacity-25 rounded-pill px-3 py-1 mb-2 small">
                <i class="bi bi-calendar3 text-warning"></i>
                <span>Panel de Control en Tiempo Real &bull; <?= date('d/m/Y') ?></span>
            </div>
            <h2 class="fw-extrabold mb-1">¡Hola, <?= htmlspecialchars($_SESSION['nombre']) ?>!</h2>
            <p class="mb-0 text-white-50" style="font-size: 0.95rem;">
                Monitoreo presupuestal, cruce de bases y seguimiento de aprendices de <strong class="text-white"><?= htmlspecialchars($nombre_apoyo_header ?? 'Apoyo Socioeconómico') ?></strong>.
            </p>
        </div>
        <div class="col-lg-5 text-lg-end mt-3 mt-lg-0">
            <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                <a href="/seguimiento-regular/modules/convocatorias/index.php" class="btn btn-light btn-sm fw-bold px-3 py-2 rounded-3 shadow-sm text-dark">
                    <i class="bi bi-plus-circle-fill text-success me-1"></i> Convocatorias
                </a>
                <a href="/seguimiento-regular/modules/ciberbanco/index.php" class="btn btn-success btn-sm fw-bold px-3 py-2 rounded-3 shadow-sm border border-light border-opacity-25">
                    <i class="bi bi-bank2 me-1"></i> Ciberbanco
                </a>
                <a href="/seguimiento-regular/modules/fic/index.php" class="btn btn-outline-light btn-sm fw-bold px-3 py-2 rounded-3">
                    <i class="bi bi-building-fill-gear me-1"></i> Base FIC
                </a>
            </div>
        </div>
    </div>
</div>

<!-- 2. TARJETAS KPI INTERACTIVAS -->
<div class="row g-3 mb-4">
    <!-- Convocatorias -->
    <div class="col-xl-3 col-md-6">
        <a href="/seguimiento-regular/modules/convocatorias/index.php" class="kpi-card-link h-100">
            <div class="card card-kpi bg-white h-100" style="border-left-color: #39A900;">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-uppercase fw-bold text-muted" style="font-size: 0.72rem; letter-spacing: 0.5px;">Convocatorias Activas</div>
                        <div class="kpi-value my-1"><?= number_format($totalConvocatorias, 0, ',', '.') ?></div>
                        <div class="text-success small fw-semibold" style="font-size: 0.78rem;">
                            <i class="bi bi-arrow-up-right-circle-fill me-1"></i> Administrar cupos
                        </div>
                    </div>
                    <div class="kpi-icon-box bg-success bg-opacity-10 text-success">
                        <i class="bi bi-folder2-open"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <!-- Aprendices Beneficiarios -->
    <div class="col-xl-3 col-md-6">
        <div class="card card-kpi bg-white h-100" style="border-left-color: #FF6B00;">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-uppercase fw-bold text-muted" style="font-size: 0.72rem; letter-spacing: 0.5px;">Aprendices en Seguimiento</div>
                    <div class="kpi-value my-1"><?= number_format($totalBeneficiariosConvocatorias, 0, ',', '.') ?></div>
                    <div class=" small fw-semibold" style="font-size: 0.78rem; color: #FF6B00;">
                        <i class="bi bi-people-fill me-1"></i> Beneficiarios únicos
                    </div>
                </div>
                <div class="kpi-icon-box" style="background: rgba(255, 107, 0, 0.12); color: #FF6B00;">
                    <i class="bi bi-person-check-fill"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Fichas Registradas -->
    <div class="col-xl-3 col-md-6">
        <a href="/seguimiento-regular/modules/fichas/index.php" class="kpi-card-link h-100">
            <div class="card card-kpi bg-white h-100" style="border-left-color: #0284C7;">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-uppercase fw-bold text-muted" style="font-size: 0.72rem; letter-spacing: 0.5px;">Fichas Registradas</div>
                        <div class="kpi-value my-1"><?= number_format($totalFichas, 0, ',', '.') ?></div>
                        <div class="text-info small fw-semibold" style="font-size: 0.78rem;">
                            <i class="bi bi-diagram-3-fill me-1"></i> Fechas etapa lectiva
                        </div>
                    </div>
                    <div class="kpi-icon-box bg-info bg-opacity-10 text-info">
                        <i class="bi bi-journal-bookmark-fill"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <!-- Base FIC -->
    <div class="col-xl-3 col-md-6">
        <a href="/seguimiento-regular/modules/fic/index.php" class="kpi-card-link h-100">
            <div class="card card-kpi bg-white h-100" style="border-left-color: #DC2626;">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-uppercase fw-bold text-muted" style="font-size: 0.72rem; letter-spacing: 0.5px;">Base de Datos FIC</div>
                        <div class="kpi-value my-1"><?= number_format($totalFicGlobal, 0, ',', '.') ?></div>
                        <div class="text-danger small fw-semibold" style="font-size: 0.78rem;">
                            <i class="bi bi-shield-exclamation me-1"></i> Cruce automático activo
                        </div>
                    </div>
                    <div class="kpi-icon-box bg-danger bg-opacity-10 text-danger">
                        <i class="bi bi-cone-striped"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- 3. CENTRO DE INTELIGENCIA FINANCIERA (FULL WIDTH) -->
<div class="card bg-white mb-4 shadow-sm border-0">
    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-3">
        <div class="fw-bold text-dark">
            <i class="bi bi-pie-chart-fill text-success me-2"></i>Balance Presupuestal en Vivo Global
        </div>
        <span class="badge bg-success bg-opacity-10 text-success fw-bold px-2 py-1">
            <?= $porcentajeEjecucion ?>% Ejecutado
        </span>
    </div>
    <div class="card-body py-4">
        <div class="row align-items-center">
            <!-- Panel Izquierdo: Cifras y Progreso -->
            <div class="col-lg-8">
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="finance-stat-box h-100 d-flex flex-column justify-content-center">
                            <span class="text-muted small fw-bold text-uppercase d-block mb-1" style="font-size: 0.72rem;">Presupuesto Total</span>
                            <h4 class="fw-extrabold text-dark mb-0">$<?= number_format($presupuestoTotal, 0, ',', '.') ?></h4>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="finance-stat-box h-100 border-start border-4 border-success d-flex flex-column justify-content-center">
                            <span class="text-muted small fw-bold text-uppercase d-block mb-1" style="font-size: 0.72rem;">Total Pagado</span>
                            <h4 class="fw-bold text-success mb-0">$<?= number_format($ejecucionTotal, 0, ',', '.') ?></h4>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="finance-stat-box h-100 border-start border-4 border-danger d-flex flex-column justify-content-center">
                            <span class="text-muted small fw-bold text-uppercase d-block mb-1" style="font-size: 0.72rem;">Saldo por Ejecutar</span>
                            <h4 class="fw-bold text-danger mb-0">$<?= number_format($saldoTotalDisponible, 0, ',', '.') ?></h4>
                        </div>
                    </div>
                </div>
                
                <!-- Barra de Progreso Global -->
                <div class="pe-lg-3">
                    <div class="d-flex justify-content-between small fw-bold text-muted mb-2" style="font-size: 0.8rem;">
                        <span>Avance de Meta Financiera Global</span>
                        <span class="text-success"><?= $porcentajeEjecucion ?>%</span>
                    </div>
                    <div class="progress shadow-sm" style="height: 16px; border-radius: 8px;">
                        <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" 
                             role="progressbar" 
                             style="width: <?= $porcentajeEjecucion ?>%;">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Panel Derecho: Gráfico de Dona -->
            <div class="col-lg-4 mt-4 mt-lg-0 text-center">
                <div class="position-relative mx-auto" style="height: 200px; max-width: 240px;">
                    <canvas id="chartDonaPresupuesto"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 4. TABLA DE DESGLOSE FINANCIERO POR CONVOCATORIA CON BUSCADOR -->
<div class="card bg-white mb-4 shadow-sm border-0">
    <div class="card-header bg-dark text-white d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 py-3">
        <div class="fw-bold">
            <i class="bi bi-table me-2 text-success"></i>Detalle de Ejecución y Planillas por Convocatoria
        </div>
        <div class="input-group input-group-sm" style="max-width: 320px;">
            <span class="input-group-text bg-white border-0"><i class="bi bi-search text-muted"></i></span>
            <input type="text" id="buscadorConvDashboard" class="form-control border-0 shadow-none" placeholder="Filtrar convocatoria en tiempo real...">
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="tablaConvDashboard">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Convocatoria</th>
                        <th class="text-center">Cupos</th>
                        <th class="text-end">Presupuesto Asignado</th>
                        <th class="text-end">Total Ejecutado</th>
                        <th class="text-end">Saldo Disponible</th>
                        <th class="text-center" style="width: 22%;">Progreso de Ejecución</th>
                        <th class="text-center pe-4">Acción Rápida</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($desgloseConvocatorias) === 0): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-folder-x fs-2 d-block mb-2"></i>
                                No hay convocatorias registradas para mostrar ejecución.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($desgloseConvocatorias as $conv): 
                        $presup_conv = (float)$conv['presupuesto'];
                        $ejec_conv = (float)$conv['total_ejecutado'];
                        $saldo_conv = $presup_conv - $ejec_conv;
                        $porc_conv = ($presup_conv > 0) ? min(100, round(($ejec_conv / $presup_conv) * 100, 1)) : 0;

                        $colorBarra = 'warning';
                        if ($porc_conv >= 90) $colorBarra = 'success';
                        elseif ($porc_conv >= 40) $colorBarra = 'info';
                    ?>
                    <tr class="fila-conv-dash">
                        <td class="ps-4">
                            <div class="fw-bold text-dark text-uppercase nombre-conv-item"><?= htmlspecialchars($conv['nombre']) ?></div>
                            <span class="text-muted" style="font-size: 0.72rem;">ID #<?= $conv['id'] ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-secondary bg-opacity-10 text-dark border px-2 py-1 fw-bold">
                                <i class="bi bi-people me-1 text-success"></i><?= $conv['cupos'] ?>
                            </span>
                        </td>
                        <td class="text-end fw-bold text-dark">
                            <?= $presup_conv > 0 ? '$'.number_format($presup_conv, 0, ',', '.') : '<span class="badge bg-warning text-dark">Sin asignar</span>' ?>
                        </td>
                        <td class="text-end fw-bold text-success">
                            $<?= number_format($ejec_conv, 0, ',', '.') ?>
                        </td>
                        <td class="text-end fw-bold <?= $saldo_conv < 0 ? 'text-danger' : 'text-secondary' ?>">
                            $<?= number_format($saldo_conv, 0, ',', '.') ?>
                        </td>
                        <td class="text-center align-middle px-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1 bg-light border" style="height: 10px; border-radius: 6px;">
                                    <div class="progress-bar bg-<?= $colorBarra ?>" style="width: <?= $porc_conv ?>%;"></div>
                                </div>
                                <span class="small fw-bold text-dark" style="min-width: 42px; font-size: 0.78rem;"><?= $porc_conv ?>%</span>
                            </div>
                        </td>
                        <td class="text-center pe-4">
                            <a href="modules/seguimiento/index.php?convocatoria_id=<?= $conv['id'] ?>" class="btn btn-sm btn-success fw-bold rounded-pill px-3 shadow-sm">
                                Abrir Planilla <i class="bi bi-arrow-right-short fs-6 align-middle"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- SCRIPTS DE GRÁFICOS E INTERACTIVIDAD -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Filtro rápido de la tabla de convocatorias
    const inputBuscador = document.getElementById('buscadorConvDashboard');
    if (inputBuscador) {
        inputBuscador.addEventListener('input', function() {
            const termino = this.value.toLowerCase().trim();
            document.querySelectorAll('.fila-conv-dash').forEach(function(fila) {
                const nombre = fila.querySelector('.nombre-conv-item').textContent.toLowerCase();
                fila.style.display = nombre.includes(termino) ? '' : 'none';
            });
        });
    }

    // 2. Gráfico de Dona (Presupuesto Ejecutado vs Disponible)
    const ctxDona = document.getElementById('chartDonaPresupuesto');
    if (ctxDona) {
        const ejecutado = <?= json_encode(max(0, $ejecucionTotal)) ?>;
        const disponible = <?= json_encode(max(0, $saldoTotalDisponible)) ?>;
        const sinPresupuesto = (ejecutado === 0 && disponible === 0) ? 1 : 0;

        new Chart(ctxDona, {
            type: 'doughnut',
            data: {
                labels: ['Ejecutado / Pagado', 'Saldo Disponible'],
                datasets: [{
                    data: sinPresupuesto ? [0, 1] : [ejecutado, disponible],
                    backgroundColor: sinPresupuesto ? ['#E2E8F0', '#E2E8F0'] : ['#39A900', '#00324D'],
                    borderWidth: 0,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, font: { size: 12, family: 'Inter', weight: '500' } }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                if (sinPresupuesto) return ' Sin presupuesto registrado';
                                return ' $' + Number(context.raw).toLocaleString('es-CO', {maximumFractionDigits: 0});
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>