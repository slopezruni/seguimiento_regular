<?php
require_once '../../config/database.php';
require_once '../../includes/header.php';

$convocatoria_id = $_GET['convocatoria_id'] ?? null;
$mes_id = $_GET['mes_id'] ?? null;

// Identificar el rol del usuario actual desde la sesión
$es_visualizador = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'visualizador');

if (!$convocatoria_id) {
    echo "<div class='alert alert-danger'>Selecciona una convocatoria en el menú lateral.</div>";
    require_once '../../includes/footer.php';
    exit;
}

$stmtConv = $pdo->prepare("SELECT nombre, cupos FROM convocatorias WHERE id = ?");
$stmtConv->execute([$convocatoria_id]);
$convocatoria_data = $stmtConv->fetch(PDO::FETCH_ASSOC);
$convocatoria_nombre = $convocatoria_data['nombre'];
$cupos_totales = (int)$convocatoria_data['cupos'];

// ====================================================================
// VISTA 1: GESTIÓN DE LAS CARPETAS (MESES)
// ====================================================================
if (!$mes_id) {
    
    if (!$es_visualizador && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_mes'])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO meses_seguimiento (convocatoria_id, mes) VALUES (?, ?)");
            $stmt->execute([$convocatoria_id, $_POST['mes_nuevo']]);
            echo "<div class='alert alert-success'>Mes creado correctamente.</div>";
        } catch (PDOException $e) {
            echo "<div class='alert alert-warning'>Ese mes ya existe en esta convocatoria.</div>";
        }
    }

    $stmtMeses = $pdo->prepare("SELECT * FROM meses_seguimiento WHERE convocatoria_id = ? ORDER BY mes DESC");
    $stmtMeses->execute([$convocatoria_id]);
    $meses = $stmtMeses->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <h2 class="mb-4">Seguimientos: <?= htmlspecialchars($convocatoria_nombre) ?></h2>
    
    <div class="row">
        <?php if (!$es_visualizador): ?>
        <div class="col-md-4">
            <div class="card shadow-sm border-success mb-4">
                <div class="card-header bg-success text-white fw-bold">1. Crear Mes de Seguimiento</div>
                <div class="card-body">
                    <form method="POST">
                        <label class="fw-bold mb-1">Año y Mes</label>
                        <input type="month" name="mes_nuevo" class="form-control mb-3" required>
                        <button type="submit" name="crear_mes" class="btn btn-success w-100 fw-bold">Crear Mes en Blanco</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="<?= $es_visualizador ? 'col-md-12' : 'col-md-8' ?>">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white fw-bold"><?= $es_visualizador ? '1.' : '2.' ?> Meses Disponibles</div>
                <div class="card-body p-0">
                    <table class="table table-striped mb-0">
                        <thead class="table-light"><tr><th>Mes</th><th>Valor Reportado</th><th>Acción</th></tr></thead>
                        <tbody>
                            <?php foreach ($meses as $m): ?>
                                <tr>
                                    <td class="fw-bold fs-5 align-middle"><?= htmlspecialchars($m['mes']) ?></td>
                                    <td class="align-middle fw-bold <?= $m['valor_pagado'] > 0 ? 'text-success' : 'text-muted' ?>">
                                        <?= $m['valor_pagado'] > 0 ? '$'.number_format($m['valor_pagado'], 0, ',', '.') : 'Sin Reportar' ?>
                                    </td>
                                    <td><a href="?convocatoria_id=<?= $convocatoria_id ?>&mes_id=<?= $m['id'] ?>" class="btn btn-primary fw-bold">Abrir Planilla <i class="bi bi-arrow-right"></i></a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php
// ====================================================================
// VISTA 2: PLANILLA DE APRENDICES DEL MES
// ====================================================================
} else {
    
    $stmtMes = $pdo->prepare("SELECT mes, valor_pagado FROM meses_seguimiento WHERE id = ?");
    $stmtMes->execute([$mes_id]);
    $datos_mes = $stmtMes->fetch(PDO::FETCH_ASSOC);

    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM aprendices_mes WHERE mes_id = ?");
    $stmtCount->execute([$mes_id]);
    $inscritos = (int)$stmtCount->fetchColumn();
    $cupos_disponibles = $cupos_totales - $inscritos;

    // PREPARAR CONSULTA GLOBAL ANTIDUPLICADOS ENTRE CONVOCATORIAS
    $stmtCheckGlobal = $pdo->prepare("
        SELECT c.nombre 
        FROM aprendices_mes am
        JOIN meses_seguimiento ms ON am.mes_id = ms.id
        JOIN convocatorias c ON ms.convocatoria_id = c.id
        WHERE am.documento = ? AND ms.convocatoria_id != ?
        LIMIT 1
    ");

    // BLOQUE DE ACCIONES PROTEGIDAS (Solo se ejecutan si NO es visualizador)
    if (!$es_visualizador && $_SERVER['REQUEST_METHOD'] === 'POST') {

        // A) EVALUACIÓN AUTOMÁTICA
        if (isset($_POST['evaluar_rapido'])) {
            $con = isset($_POST['contrato']) ? 'SI' : 'NO';
            $vin = isset($_POST['vinculo']) ? 'SI' : 'NO';
            $prod = isset($_POST['etapa_productiva']) ? 'SI' : 'NO';
            
            $stmtEval = $pdo->prepare("UPDATE aprendices_mes SET contrato_aprendizaje = ?, vinculo_laboral = ?, etapa_productiva = ? WHERE id = ?");
            $stmtEval->execute([$con, $vin, $prod, $_POST['aprendiz_id']]);
        }

        // B) REPORTE FINANCIERO
        if (isset($_POST['reportar_valor'])) {
            $stmtUpdateValor = $pdo->prepare("UPDATE meses_seguimiento SET valor_pagado = ? WHERE id = ?");
            $stmtUpdateValor->execute([$_POST['valor_ejecutado'], $mes_id]);
            echo "<div class='alert alert-success mt-2 fw-bold'><i class='bi bi-check-circle-fill'></i> Valor ejecutado reportado.</div>";
            $datos_mes['valor_pagado'] = $_POST['valor_ejecutado']; 
        }

        // C) CARGA MANUAL (Con validación antiduplicados global)
        if (isset($_POST['guardar_manual'])) {
            $doc_manual = trim($_POST['documento']);
            
            // Verificar si ya está en otra convocatoria
            $stmtCheckGlobal->execute([$doc_manual, $convocatoria_id]);
            $otraConv = $stmtCheckGlobal->fetch(PDO::FETCH_ASSOC);

            if ($otraConv) {
                echo "<div class='alert alert-danger mt-2 fw-bold'><i class='bi bi-exclamation-triangle-fill'></i> Error: El aprendiz con documento {$doc_manual} ya se encuentra registrado en la convocatoria '{$otraConv['nombre']}'. No puede estar en dos convocatorias simultáneamente.</div>";
            } elseif ($cupos_disponibles > 0) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO aprendices_mes (mes_id, tipo_documento, documento, nombre, ficha, estado_seguimiento) VALUES (?, ?, ?, ?, ?, 'Cumple')");
                    $stmt->execute([$mes_id, $_POST['tipo_doc'], $doc_manual, $_POST['nombre'], $_POST['ficha']]);
                    echo "<div class='alert alert-success mt-2 fw-bold'>Aprendiz agregado correctamente.</div>";
                    $inscritos++; $cupos_disponibles--;
                } catch(PDOException $e) {
                    echo "<div class='alert alert-danger mt-2 fw-bold'>ERROR: Documento duplicado en este mes.</div>";
                }
            } else {
                echo "<div class='alert alert-danger mt-2 fw-bold'>Alerta: Cupo completo ($cupos_totales).</div>";
            }
        }

        // D) ELIMINAR APRENDIZ Y LIBERAR CUPO
        if (isset($_POST['eliminar_aprendiz'])) {
            $stmtDel = $pdo->prepare("DELETE FROM aprendices_mes WHERE id = ?");
            $stmtDel->execute([$_POST['id_eliminar']]);
            echo "<div class='alert alert-success mt-2 fw-bold'><i class='bi bi-trash3-fill'></i> Aprendiz eliminado correctamente. El cupo ha sido liberado.</div>";
            $inscritos--; 
            $cupos_disponibles++;
        }

        // E) CARGA MASIVA CSV (Con validación antiduplicados global)
        if (isset($_POST['subir_csv'])) {
            if ($_FILES['archivo_csv']['error'] == 0) {
                if ($cupos_disponibles > 0) {
                    $archivo = $_FILES['archivo_csv']['tmp_name'];
                    $lineas = file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    $insertados = 0; $duplicados = 0; $bloqueados_otra_conv = 0; $limite_alcanzado = false; $lista_duplicados = [];
                    
                    $stmtCheck = $pdo->prepare("SELECT id FROM aprendices_mes WHERE mes_id = ? AND documento = ?");
                    $stmtInsert = $pdo->prepare("INSERT INTO aprendices_mes (mes_id, tipo_documento, documento, nombre, ficha, estado_seguimiento) VALUES (?, ?, ?, ?, ?, 'Cumple')");
                    
                    foreach($lineas as $index => $linea) {
                        if ($index === 0) continue; 
                        if ($cupos_disponibles <= 0) {
                            $limite_alcanzado = true;
                            break; 
                        }
                        
                        $separador = strpos($linea, ';') !== false ? ';' : ',';
                        $datos = str_getcsv($linea, $separador);
                        
                        if (count($datos) >= 6) {
                            $nombre = trim(str_replace(['"', "'"], '', $datos[1]));
                            $tipo = strtoupper(trim(str_replace(['"', "'"], '', $datos[2])));
                            $documento = trim(str_replace(['"', "'"], '', $datos[3]));
                            $ficha = trim(str_replace(['"', "'"], '', $datos[5]));
                            
                            if(!empty($documento) && is_numeric($documento)) {
                                // 1. Verificar si está en otra convocatoria
                                $stmtCheckGlobal->execute([$documento, $convocatoria_id]);
                                if ($stmtCheckGlobal->fetch()) {
                                    $bloqueados_otra_conv++;
                                    continue;
                                }

                                // 2. Verificar duplicados locales en el mes actual
                                $stmtCheck->execute([$mes_id, $documento]);
                                if($stmtCheck->rowCount() == 0 && !in_array($documento, $lista_duplicados)) {
                                    try {
                                        $stmtInsert->execute([$mes_id, $tipo, $documento, empty($nombre) ? 'Sin Nombre' : $nombre, $ficha]);
                                        $insertados++; $inscritos++; $cupos_disponibles--;
                                    } catch(PDOException $e) {
                                        $duplicados++; if(!in_array($documento, $lista_duplicados)) $lista_duplicados[] = $documento;
                                    }
                                } else {
                                    $duplicados++; if(!in_array($documento, $lista_duplicados)) $lista_duplicados[] = $documento;
                                }
                            }
                        }
                    }
                    if ($insertados > 0) echo "<div class='alert alert-success mt-2 fw-bold'>Carga procesada: $insertados aprendices agregados.</div>";
                    if ($bloqueados_otra_conv > 0) echo "<div class='alert alert-danger mt-2 fw-bold'>Se rechazaron $bloqueados_otra_conv aprendices porque ya están activos en otra convocatoria.</div>";
                    if ($duplicados > 0) echo "<div class='alert alert-warning mt-2 fw-bold'>Se bloquearon $duplicados duplicados en este mes.</div>";
                    if ($limite_alcanzado) echo "<div class='alert alert-warning mt-2 fw-bold'>Se detuvo la carga por límite de cupo ($cupos_totales).</div>";
                } else {
                    echo "<div class='alert alert-danger mt-2 fw-bold'>Alerta: Cupo completo ($cupos_totales). No puedes realizar cargas masivas.</div>";
                }
            }
        }
    }

    $sqlList = "SELECT a.*, f.fecha_fin_lectiva, (CASE WHEN fic.documento IS NOT NULL THEN 'SI' ELSE 'NO' END) AS tiene_fic_global 
                FROM aprendices_mes a 
                LEFT JOIN distribucion_fichas f ON a.ficha = f.numero_ficha 
                LEFT JOIN base_fic fic ON a.documento = fic.documento 
                WHERE a.mes_id = ? ORDER BY a.nombre ASC";
    $stmtList = $pdo->prepare($sqlList);
    $stmtList->execute([$mes_id]);
    $aprendices = $stmtList->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0">Planilla de Seguimiento: <?= htmlspecialchars($datos_mes['mes']) ?></h2>
            <small class="text-muted fw-bold">Convocatoria: <?= htmlspecialchars($convocatoria_nombre) ?> | Cupos: <?= $inscritos ?> / <?= $cupos_totales ?></small>
        </div>
        <div class="d-flex gap-2">
            <!-- Menú Desplegable de Reportes Excel -->
            <div class="dropdown">
                <button class="btn btn-success fw-bold dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-file-earmark-excel-fill"></i> Descargar Reportes
                </button>
                <ul class="dropdown-menu shadow">
                    <li><a class="dropdown-item fw-bold" href="exportar.php?mes_id=<?= $mes_id ?>&tipo=todos"><i class="bi bi-people-fill text-secondary me-2"></i> Reporte General (Todos)</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item fw-bold text-success" href="exportar.php?mes_id=<?= $mes_id ?>&tipo=cumple"><i class="bi bi-check-circle-fill me-2"></i> Solo los que Cumplen</a></li>
                    <li><a class="dropdown-item fw-bold text-danger" href="exportar.php?mes_id=<?= $mes_id ?>&tipo=cancelados"><i class="bi bi-x-circle-fill me-2"></i> Solo los Cancelados</a></li>
                </ul>
            </div>

            <a href="?convocatoria_id=<?= $convocatoria_id ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver a los meses
            </a>
        </div>
    </div>

    <!-- PANEL DE REPORTE FINANCIERO MEJORADO CON FONDO OSCURO Y TEXTO CLARO -->
    <div class="card shadow-sm bg-dark text-white border-start border-success border-4 mb-3">
        <div class="card-body d-flex justify-content-between align-items-center py-2">
            <div>
                <h6 class="mb-0 text-white fw-bold"><i class="bi bi-cash-stack text-success"></i> Valor Ejecutado (Pagado)</h6>
            </div>
            <?php if (!$es_visualizador): ?>
            <form method="POST" class="d-flex gap-2 w-50">
                <input type="number" name="valor_ejecutado" class="form-control form-control-sm fw-bold fs-6 text-end text-dark bg-light" 
                       value="<?= $datos_mes['valor_pagado'] > 0 ? $datos_mes['valor_pagado'] : '' ?>" placeholder="Ej: 15000000" required>
                <button type="submit" name="reportar_valor" class="btn btn-sm btn-success fw-bold text-nowrap">
                    <?= $datos_mes['valor_pagado'] > 0 ? 'Actualizar' : 'Guardar' ?>
                </button>
            </form>
            <?php else: ?>
            <div class="fw-bold fs-5 text-success">
                <?= $datos_mes['valor_pagado'] > 0 ? '$'.number_format($datos_mes['valor_pagado'], 0, ',', '.') : 'Sin Reportar' ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TABLA DE APRENDICES -->
    <div class="card shadow-sm border-0 shadow-lg">
        <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between align-items-center">
            <span>Listado de Aprendices (<?= $inscritos ?> / <?= $cupos_totales ?>)</span>
            
            <?php if(!$es_visualizador): ?>
                <?php if($cupos_disponibles > 0): ?>
                    <button class="btn btn-sm btn-outline-light fw-bold" onclick="document.getElementById('cajaAgregar').classList.toggle('d-none')">
                        + Añadir Aprendices (Faltan <?= $cupos_disponibles ?>)
                    </button>
                <?php else: ?>
                    <span class="badge bg-danger fs-6 border border-light shadow-sm">CUPO LLENO (<?= $cupos_totales ?>/<?= $cupos_totales ?>)</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <?php if(!$es_visualizador && $cupos_disponibles > 0): ?>
        <div id="cajaAgregar" class="card-body bg-light border-bottom d-none">
            <div class="row">
                <!-- Manual -->
                <div class="col-md-5">
                    <div class="card shadow-sm border-primary h-100">
                        <div class="card-header bg-primary text-white fw-bold py-2">Carga Manual</div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-4 mb-2"><input type="text" name="tipo_doc" class="form-control form-control-sm" placeholder="Tipo" required></div>
                                    <div class="col-8 mb-2"><input type="number" name="documento" class="form-control form-control-sm" placeholder="Documento" required></div>
                                    <div class="col-12 mb-2"><input type="text" name="nombre" class="form-control form-control-sm" placeholder="Nombre Completo" required></div>
                                    <div class="col-12 mb-2"><input type="text" name="ficha" class="form-control form-control-sm" placeholder="N° Ficha" required></div>
                                </div>
                                <button type="submit" name="guardar_manual" class="btn btn-primary btn-sm w-100 fw-bold">Agregar 1 Aprendiz</button>
                            </form>
                        </div>
                    </div>
                </div>
                <!-- CSV -->
                <div class="col-md-7">
                    <div class="card shadow-sm border-warning h-100">
                        <div class="card-header bg-warning text-dark fw-bold py-2">Carga Masiva (CSV)</div>
                        <div class="card-body d-flex flex-column justify-content-center">
                            <p class="small text-muted mb-3">Sube tu archivo CSV. El sistema procesará aprendices hasta llenar los <b><?= $cupos_disponibles ?> cupos</b> disponibles actualmente.</p>
                            <form method="POST" enctype="multipart/form-data" class="d-flex gap-2">
                                <input type="file" name="archivo_csv" class="form-control form-control-sm" accept=".csv" required>
                                <button type="submit" name="subir_csv" class="btn btn-warning btn-sm fw-bold text-nowrap">Subir CSV</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle border-bottom mb-0" style="font-size: 0.85em; table-layout: fixed; width: 100%; min-width: 950px;">
                    <colgroup>
                        <col style="width: 3%;">  <!-- # -->
                        <col style="width: 6%;">  <!-- Tipo Doc -->
                        <col style="width: 10%;"> <!-- Documento -->
                        <col style="width: 21%;"> <!-- Nombre Completo -->
                        <col style="width: 17%;"> <!-- Ficha y Tiempos -->
                        <col style="width: 6%;">  <!-- FIC -->
                        <col style="width: 6%;">  <!-- CTO -->
                        <col style="width: 6%;">  <!-- V.LAB -->
                        <col style="width: 7%;">  <!-- E.PROD -->
                        <col style="width: 13%;"> <!-- Estado Final -->
                        <?php if(!$es_visualizador): ?><col style="width: 5%;"><?php endif; ?> <!-- Eliminar -->
                    </colgroup>
                    <thead class="table-light">
                        <tr>
                            <th class="text-center text-muted px-1">#</th>
                            <th class="text-center px-1">Tipo Doc</th>
                            <th>Documento</th>
                            <th>Nombre Completo</th>
                            <th>Ficha y Tiempos</th>
                            <th class="text-center text-nowrap px-1" title="Apoyo FIC Automático">FIC ⚡</th>
                            <th class="text-center text-nowrap px-1" title="¿Tiene Contrato?">CONTRATO</th>
                            <th class="text-center text-nowrap px-1" title="¿Tiene Vínculo Laboral?">V.LABORAL</th>
                            <th class="text-center text-nowrap px-1 text-success" title="¿Ya inició Etapa Productiva?">E.PRODUCTIVA</th>
                            <th class="px-2">Estado Final</th>
                            <?php if(!$es_visualizador): ?><th class="text-center px-1"><i class="bi bi-trash text-danger"></i></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($aprendices) === 0): ?>
                            <tr><td colspan="<?= $es_visualizador ? '10' : '11' ?>" class="text-center py-5 text-muted">Aún no hay aprendices en este mes.</td></tr>
                        <?php endif; ?>
                        
                        <?php 
                        $item = 1; 
                        $hoy = new DateTime(); 
                        $hoy->setTime(0,0,0);
                        
                        foreach ($aprendices as $a): 
                            
                            $estado_calculado = 'Cumple';
                            $novedad_texto = 'Sin novedad';
                            $alerta_fechas = '';

                            // Cálculos de Tiempo
                            if (!empty($a['fecha_fin_lectiva'])) {
                                $fin_lectiva = new DateTime($a['fecha_fin_lectiva']);
                                $fecha_limite = clone $fin_lectiva;
                                $fecha_limite->modify('+20 days');
                                $fecha_limite->setTime(0,0,0);
                                
                                $dias_restantes = (int)$hoy->diff($fecha_limite)->format('%R%a');

                                if ($a['etapa_productiva'] == 'NO') {
                                    if ($dias_restantes < 0) {
                                        $estado_calculado = 'Cancelado';
                                        $novedad_texto = 'Fin de lectiva sin E. Productiva';
                                        $alerta_fechas = "<span class='text-danger fw-bold'><i class='bi bi-alarm-fill'></i> Vencido hace ".abs($dias_restantes)." días</span>";
                                    } elseif ($dias_restantes <= 10) {
                                        $alerta_fechas = "<span class='text-danger fw-bold'><i class='bi bi-clock-history'></i> Faltan $dias_restantes días</span>";
                                    } else {
                                        $alerta_fechas = "<span class='text-muted'><i class='bi bi-calendar'></i> Faltan $dias_restantes días</span>";
                                    }
                                } else {
                                    $alerta_fechas = "<span class='text-success fw-bold'><i class='bi bi-shield-check'></i> Salvado por E.P.</span>";
                                }
                            }

                            // Reglas de Oro
                            if ($a['tiene_fic_global'] == 'SI') {
                                $estado_calculado = 'Cancelado';
                                $novedad_texto = 'Cancelado por FIC Automático';
                            } elseif ($a['contrato_aprendizaje'] == 'SI') {
                                $estado_calculado = 'Cancelado';
                                $novedad_texto = 'Cancelado por Contrato';
                            } elseif ($a['vinculo_laboral'] == 'SI') {
                                $estado_calculado = 'Cancelado';
                                $novedad_texto = 'Cancelado por Vínculo Laboral';
                            }

                            // Actualización silenciosa de estado (Solo si es gestor)
                            if (!$es_visualizador && $a['estado_seguimiento'] !== $estado_calculado) {
                                $pdo->prepare("UPDATE aprendices_mes SET estado_seguimiento = ? WHERE id = ?")->execute([$estado_calculado, $a['id']]);
                            }
                        ?>
                        
                        <tr id="fila_<?= $a['id'] ?>" class="<?= $estado_calculado == 'Cancelado' ? 'table-danger bg-opacity-25' : '' ?>">

                            <td class="text-center fw-bold text-muted px-1">
                                <?= $item++ ?>
                                <?php if(!$es_visualizador): ?>
                                <form method="POST" id="form_<?= $a['id'] ?>" action="#fila_<?= $a['id'] ?>" style="display:none;">
                                    <input type="hidden" name="evaluar_rapido" value="1">
                                    <input type="hidden" name="aprendiz_id" value="<?= $a['id'] ?>">
                                </form>
                                <?php endif; ?>
                            </td>

                            <td class="text-center px-1"><span class="badge bg-light text-dark border"><?= htmlspecialchars($a['tipo_documento']) ?></span></td>

                            <td class="fw-bold text-nowrap"><?= htmlspecialchars($a['documento']) ?></td>

                            <td class="fw-bold text-dark text-uppercase lh-sm text-break"><?= htmlspecialchars($a['nombre']) ?></td>
                            
                            <td class="lh-sm py-2">
                                <div class="d-flex flex-wrap gap-1 mb-1">
                                    <span class="badge bg-secondary">Ficha: <?= htmlspecialchars($a['ficha']) ?></span>
                                    <?php if(!empty($a['fecha_fin_lectiva'])): ?>
                                        <span class="badge bg-info text-dark fw-bold border border-dark">Fin: <?= htmlspecialchars($a['fecha_fin_lectiva']) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Sin Ficha</span>
                                    <?php endif; ?>
                                </div>
                                <?php if(!empty($a['fecha_fin_lectiva'])): ?>
                                    <div style="font-size: 0.9em;"><?= $alerta_fechas ?></div>
                                <?php endif; ?>
                            </td>
                            
                            <!-- FIC Automático -->
                            <td class="text-center px-1">
                                <input class="form-check-input <?= $a['tiene_fic_global'] == 'SI' ? 'bg-danger border-danger' : 'border-dark bg-secondary opacity-50' ?> shadow-sm m-0" 
                                       style="width:1.25rem; height:1.25rem; cursor:not-allowed;" 
                                       type="checkbox" 
                                       <?= $a['tiene_fic_global'] == 'SI' ? 'checked' : '' ?> 
                                       disabled>
                            </td>

                            <td class="text-center px-1">
                                <input class="form-check-input border-dark shadow-sm m-0" style="width:1.25rem; height:1.25rem; cursor:pointer;" type="checkbox" name="contrato" value="SI" form="form_<?= $a['id'] ?>" <?= $a['contrato_aprendizaje'] == 'SI' ? 'checked' : '' ?> <?= $es_visualizador ? 'disabled' : '' ?> onchange="document.getElementById('form_<?= $a['id'] ?>').submit();">
                            </td>
                            <td class="text-center px-1">
                                <input class="form-check-input border-dark shadow-sm m-0" style="width:1.25rem; height:1.25rem; cursor:pointer;" type="checkbox" name="vinculo" value="SI" form="form_<?= $a['id'] ?>" <?= $a['vinculo_laboral'] == 'SI' ? 'checked' : '' ?> <?= $es_visualizador ? 'disabled' : '' ?> onchange="document.getElementById('form_<?= $a['id'] ?>').submit();">
                            </td>
                            <td class="text-center px-1">
                                <input class="form-check-input border-success shadow-sm m-0" style="width:1.25rem; height:1.25rem; cursor:pointer;" type="checkbox" name="etapa_productiva" value="SI" form="form_<?= $a['id'] ?>" <?= $a['etapa_productiva'] == 'SI' ? 'checked' : '' ?> <?= $es_visualizador ? 'disabled' : '' ?> onchange="document.getElementById('form_<?= $a['id'] ?>').submit();">
                            </td>
                            
                            <td class="lh-sm px-2">
                                <?php if($estado_calculado == 'Cumple'): ?>
                                    <span class="badge bg-success shadow-sm mb-1 px-2 py-1"><i class="bi bi-check-circle"></i> CUMPLE</span>
                                <?php else: ?>
                                    <span class="badge bg-danger shadow-sm mb-1 px-2 py-1"><i class="bi bi-x-circle"></i> CANCELADO</span>
                                <?php endif; ?>
                                
                                <div class="small fw-bold <?= $estado_calculado == 'Cancelado' ? 'text-danger' : 'text-success' ?>" style="line-height: 1.1;">
                                    <?= $novedad_texto ?>
                                </div>
                            </td>

                            <!-- Eliminar (Solo para Gestor) -->
                            <?php if(!$es_visualizador): ?>
                            <td class="text-center px-1">
                                <form method="POST" class="m-0" onsubmit="return confirm('¿Estás seguro de eliminar a <?= addslashes($a['nombre']) ?>? Esto liberará su cupo en este mes.');">
                                    <input type="hidden" name="id_eliminar" value="<?= $a['id'] ?>">
                                    <button type="submit" name="eliminar_aprendiz" class="btn btn-sm btn-outline-danger border-0" title="Eliminar y liberar cupo">
                                        <i class="bi bi-trash3-fill fs-6"></i>
                                    </button>
                                </form>
                            </td>
                            <?php endif; ?>

                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php 
}
require_once '../../includes/footer.php'; 
?>