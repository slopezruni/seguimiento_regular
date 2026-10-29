<?php
require_once '../../config/database.php';
require_once '../../includes/header.php';

$convocatoria_id = $_GET['convocatoria_id'] ?? null;
$mes_id = $_GET['mes_id'] ?? null;

// Identificar el rol del usuario actual desde la sesión
$es_visualizador = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'visualizador');

if (!$convocatoria_id) {
    echo "<div class='container-fluid px-4 py-5'><div class='alert alert-danger fw-bold border-0 shadow-sm'><i class='bi bi-exclamation-triangle-fill me-2'></i> Selecciona una convocatoria en el menú lateral.</div></div>";
    require_once '../../includes/footer.php';
    exit;
}

// OBTENER DATOS DE LA CONVOCATORIA Y SU TIPO DE APOYO
$stmtConv = $pdo->prepare("
    SELECT c.nombre, c.cupos, c.apoyo_id, t.nombre AS apoyo_nombre 
    FROM convocatorias c 
    LEFT JOIN tipos_apoyo t ON c.apoyo_id = t.id 
    WHERE c.id = ?
");
$stmtConv->execute([$convocatoria_id]);
$convocatoria_data = $stmtConv->fetch(PDO::FETCH_ASSOC);
$convocatoria_nombre = $convocatoria_data['nombre'];
$cupos_totales = (int)$convocatoria_data['cupos'];

// DETECTAR SI ES EL APOYO DE TRANSPORTE PARA ACTIVAR EL CRUCE CON REGULAR
$es_transporte = (stripos($convocatoria_data['apoyo_nombre'], 'transporte') !== false);

// ====================================================================
// VISTA 1: GESTIÓN DE LAS CARPETAS (MESES)
// ====================================================================
if (!$mes_id) {
    
    if (!$es_visualizador && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_mes'])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO meses_seguimiento (convocatoria_id, mes) VALUES (?, ?)");
            $stmt->execute([$convocatoria_id, $_POST['mes_nuevo']]);
            $mensaje = "<div class='alert alert-success border-0 shadow-sm fw-bold mb-4'><i class='bi bi-check-circle-fill me-2'></i> Mes de seguimiento creado correctamente.</div>";
        } catch (PDOException $e) {
            $mensaje = "<div class='alert alert-warning border-0 shadow-sm fw-bold mb-4'><i class='bi bi-exclamation-circle-fill me-2'></i> Ese mes ya existe en esta convocatoria.</div>";
        }
    }

    $stmtMeses = $pdo->prepare("SELECT * FROM meses_seguimiento WHERE convocatoria_id = ? ORDER BY mes DESC");
    $stmtMeses->execute([$convocatoria_id]);
    $meses = $stmtMeses->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <div class="container-fluid px-4 py-3">
        <div class="mb-4">
            <h2 class="fw-extrabold text-dark mb-1"><i class="bi bi-folder2-open text-success me-2"></i> Carpetas de Seguimiento</h2>
            <p class="text-muted mb-0">Convocatoria: <span class="badge bg-success bg-opacity-10 text-white fw-bold px-2 py-1"><?= htmlspecialchars($convocatoria_nombre) ?></span></p>
        </div>
        
        <?= $mensaje ?? '' ?>

        <div class="row">
            <?php if (!$es_visualizador): ?>
            <div class="col-md-4">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-success text-white fw-bold py-3"><i class="bi bi-calendar-plus-fill me-2"></i> Aperturar Nuevo Mes</div>
                    <div class="card-body p-4">
                        <form method="POST">
                            <label class="form-label fw-bold small text-secondary">Selecciona Año y Mes</label>
                            <input type="month" name="mes_nuevo" class="form-control form-control-lg mb-3 border-success" required>
                            <button type="submit" name="crear_mes" class="btn btn-success w-100 fw-bold rounded-pill shadow-sm"><i class="bi bi-plus-lg me-1"></i> Crear Mes en Blanco</button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="<?= $es_visualizador ? 'col-md-12' : 'col-md-8' ?>">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-dark text-white fw-bold py-3 d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-journals me-2 text-success"></i> <?= $es_visualizador ? '1.' : '2.' ?> Meses Disponibles</span>
                        <span class="badge bg-light text-dark border"><?= count($meses) ?> Registros</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Periodo (Mes)</th>
                                        <th>Valor Reportado (Ejecución)</th>
                                        <th class="text-center pe-4">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(count($meses) === 0): ?>
                                        <tr><td colspan="3" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-2 d-block mb-2"></i> Aún no hay meses creados para esta convocatoria.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($meses as $m): ?>
                                        <tr>
                                            <td class="fw-bold fs-6 ps-4 text-uppercase"><i class="bi bi-calendar-event me-2 text-secondary"></i> <?= htmlspecialchars($m['mes']) ?></td>
                                            <td class="fw-bold <?= $m['valor_pagado'] > 0 ? 'text-success' : 'text-muted' ?>">
                                                <?= $m['valor_pagado'] > 0 ? '$'.number_format($m['valor_pagado'], 2, ',', '.') : '<span class="badge bg-warning text-dark">Pendiente de Reporte</span>' ?>
                                            </td>
                                            <td class="text-center pe-4">
                                                <a href="?convocatoria_id=<?= $convocatoria_id ?>&mes_id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-success fw-bold rounded-pill px-3 shadow-sm">
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

    // CONSULTA GLOBAL ANTIDUPLICADOS: Ahora bloquea solo si está en otra convocatoria DEL MISMO TIPO DE APOYO
    $stmtCheckGlobal = $pdo->prepare("
        SELECT c.nombre 
        FROM aprendices_mes am
        JOIN meses_seguimiento ms ON am.mes_id = ms.id
        JOIN convocatorias c ON ms.convocatoria_id = c.id
        WHERE TRIM(am.documento) = TRIM(?) AND ms.convocatoria_id != ? AND c.apoyo_id = ?
        LIMIT 1
    ");

    // ACCIONES PROTEGIDAS
    if (!$es_visualizador && $_SERVER['REQUEST_METHOD'] === 'POST') {

        // EVALUACIÓN RÁPIDA
        if (isset($_POST['evaluar_rapido'])) {
            $con = isset($_POST['contrato']) ? 'SI' : 'NO';
            $vin = isset($_POST['vinculo']) ? 'SI' : 'NO';
            $prod = isset($_POST['etapa_productiva']) ? 'SI' : 'NO';
            
            $stmtEval = $pdo->prepare("UPDATE aprendices_mes SET contrato_aprendizaje = ?, vinculo_laboral = ?, etapa_productiva = ? WHERE id = ?");
            $stmtEval->execute([$con, $vin, $prod, $_POST['aprendiz_id']]);
        }

        // REPORTE FINANCIERO
        if (isset($_POST['reportar_valor'])) {
            $stmtUpdateValor = $pdo->prepare("UPDATE meses_seguimiento SET valor_pagado = ? WHERE id = ?");
            $stmtUpdateValor->execute([$_POST['valor_ejecutado'], $mes_id]);
            echo "<div class='alert alert-success mt-2 fw-bold border-0 shadow-sm px-4'><i class='bi bi-check-circle-fill me-2'></i> Valor ejecutado reportado exitosamente.</div>";
            $datos_mes['valor_pagado'] = $_POST['valor_ejecutado']; 
        }

        // CARGA MANUAL
        if (isset($_POST['guardar_manual'])) {
            $doc_manual = preg_replace('/[^0-9]/', '', $_POST['documento']);
            $ficha_manual = preg_replace('/[^0-9]/', '', $_POST['ficha']);
            
            $stmtCheckGlobal->execute([$doc_manual, $convocatoria_id, $convocatoria_data['apoyo_id']]);
            $otraConv = $stmtCheckGlobal->fetch(PDO::FETCH_ASSOC);

            if ($otraConv) {
                echo "<div class='alert alert-danger mt-2 fw-bold border-0 shadow-sm px-4'><i class='bi bi-exclamation-triangle-fill me-2'></i> Error: El aprendiz con documento {$doc_manual} ya se encuentra registrado en la convocatoria '{$otraConv['nombre']}'.</div>";
            } elseif ($cupos_disponibles > 0) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO aprendices_mes (mes_id, tipo_documento, documento, nombre, ficha, estado_seguimiento) VALUES (?, ?, ?, ?, ?, 'Cumple')");
                    $stmt->execute([$mes_id, strtoupper(trim($_POST['tipo_doc'])), $doc_manual, strtoupper(trim($_POST['nombre'])), $ficha_manual]);
                    echo "<div class='alert alert-success mt-2 fw-bold border-0 shadow-sm px-4'><i class='bi bi-check-circle-fill me-2'></i> Aprendiz agregado correctamente.</div>";
                    $inscritos++; $cupos_disponibles--;
                } catch(PDOException $e) {
                    echo "<div class='alert alert-danger mt-2 fw-bold border-0 shadow-sm px-4'><i class='bi bi-exclamation-triangle-fill me-2'></i> ERROR: Documento duplicado en este mes.</div>";
                }
            } else {
                echo "<div class='alert alert-danger mt-2 fw-bold border-0 shadow-sm px-4'><i class='bi bi-shield-x-fill me-2'></i> Alerta: Cupo completo ($cupos_totales).</div>";
            }
        }

        // ELIMINAR APRENDIZ
        if (isset($_POST['eliminar_aprendiz'])) {
            $stmtDel = $pdo->prepare("DELETE FROM aprendices_mes WHERE id = ?");
            $stmtDel->execute([$_POST['id_eliminar']]);
            echo "<div class='alert alert-success mt-2 fw-bold border-0 shadow-sm px-4'><i class='bi bi-trash3-fill me-2'></i> Aprendiz eliminado. El cupo ha sido liberado.</div>";
            $inscritos--; 
            $cupos_disponibles++;
        }

        // CARGA MASIVA CSV DE APROBADOS
        if (isset($_POST['subir_csv'])) {
            if ($_FILES['archivo_csv']['error'] == 0) {
                if ($cupos_disponibles > 0) {
                    $archivo = $_FILES['archivo_csv']['tmp_name'];
                    $lineas = file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    $insertados = 0; $duplicados = 0; $bloqueados_otra_conv = 0; $limite_alcanzado = false; $lista_duplicados = [];
                    
                    $stmtCheck = $pdo->prepare("SELECT id FROM aprendices_mes WHERE mes_id = ? AND TRIM(documento) = TRIM(?)");
                    $stmtInsert = $pdo->prepare("INSERT INTO aprendices_mes (mes_id, tipo_documento, documento, nombre, ficha, estado_seguimiento) VALUES (?, ?, ?, ?, ?, 'Cumple')");
                    
                    foreach($lineas as $index => $linea) {
                        if ($index === 0) continue; 
                        if ($cupos_disponibles <= 0) {
                            $limite_alcanzado = true;
                            break; 
                        }
                        
                        $separador = strpos($linea, ';') !== false ? ';' : ',';
                        $datos = str_getcsv($linea, $separador);
                        
                        if (count($datos) >= 5) {
                            $tipo = strtoupper(trim(str_replace(['"', "'", "\xC2\xA0"], '', $datos[1])));
                            $documento = trim(preg_replace('/[^0-9]/', '', $datos[2]));
                            $nombre = strtoupper(trim(str_replace(['"', "'", "\xC2\xA0"], '', $datos[3])));
                            $ficha = trim(preg_replace('/[^0-9]/', '', $datos[4]));
                            
                            if (empty($nombre)) $nombre = 'SIN NOMBRE';
                            if (empty($ficha)) $ficha = 'SIN FICHA';
                            
                            if(!empty($documento) && is_numeric($documento)) {
                                $stmtCheckGlobal->execute([$documento, $convocatoria_id, $convocatoria_data['apoyo_id']]);
                                if ($stmtCheckGlobal->fetch()) {
                                    $bloqueados_otra_conv++;
                                    continue;
                                }

                                $stmtCheck->execute([$mes_id, $documento]);
                                if($stmtCheck->rowCount() == 0 && !in_array($documento, $lista_duplicados)) {
                                    try {
                                        $stmtInsert->execute([$mes_id, $tipo, $documento, $nombre, $ficha]);
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
                    if ($insertados > 0) echo "<div class='alert alert-success mt-2 fw-bold border-0 shadow-sm px-4'><i class='bi bi-cloud-check-fill me-2'></i> Carga procesada: $insertados aprendices agregados exitosamente.</div>";
                    if ($bloqueados_otra_conv > 0) echo "<div class='alert alert-danger mt-2 fw-bold border-0 shadow-sm px-4'><i class='bi bi-shield-x-fill me-2'></i> Se rechazaron $bloqueados_otra_conv aprendices porque ya están activos en otra convocatoria del mismo apoyo.</div>";
                    if ($duplicados > 0) echo "<div class='alert alert-warning mt-2 fw-bold border-0 shadow-sm px-4'><i class='bi bi-exclamation-circle-fill me-2'></i> Se bloquearon $duplicados duplicados (ya existían en este mes).</div>";
                    if ($limite_alcanzado) echo "<div class='alert alert-warning mt-2 fw-bold border-0 shadow-sm px-4'><i class='bi bi-stop-circle-fill me-2'></i> Se detuvo la carga por límite de cupo ($cupos_totales).</div>";
                } else {
                    echo "<div class='alert alert-danger mt-2 fw-bold border-0 shadow-sm px-4'><i class='bi bi-shield-x-fill me-2'></i> Alerta: Cupo completo ($cupos_totales).</div>";
                }
            }
        }
    }

    // CARGAR APRENDICES CRUZANDO CON:
    // 1. Fechas de Ficha
    // 2. Base FIC (Por separado)
    // 3. Regular (Si es transporte)
    // 4. DF-14 (Prioriza coincidencia exacta Documento + Ficha; si la ficha tenía variaciones, respalda por Documento)
    $sqlList = "SELECT a.*, 
                (SELECT f.fecha_fin_lectiva FROM distribucion_fichas f 
                 WHERE TRIM(REPLACE(REPLACE(f.numero_ficha, '\r', ''), '\n', '')) COLLATE utf8mb4_unicode_ci = TRIM(REPLACE(REPLACE(a.ficha, '\r', ''), '\n', '')) COLLATE utf8mb4_unicode_ci 
                 LIMIT 1) AS fecha_fin_lectiva,
                (CASE WHEN fic.documento IS NOT NULL THEN 'SI' ELSE 'NO' END) AS tiene_fic_global,
                (SELECT CASE WHEN COUNT(*) > 0 THEN 'SI' ELSE 'NO' END 
                 FROM aprendices_mes am2 
                 JOIN meses_seguimiento ms2 ON am2.mes_id = ms2.id 
                 JOIN convocatorias c2 ON ms2.convocatoria_id = c2.id 
                 JOIN tipos_apoyo t2 ON c2.apoyo_id = t2.id 
                 WHERE TRIM(am2.documento) COLLATE utf8mb4_unicode_ci = TRIM(a.documento) COLLATE utf8mb4_unicode_ci 
                 AND t2.nombre LIKE '%Regular%') AS activo_en_regular,
                (SELECT df.estado_aprendiz FROM base_df14 df 
                 WHERE TRIM(df.documento) COLLATE utf8mb4_unicode_ci = TRIM(REPLACE(REPLACE(a.documento, '\r', ''), '\n', '')) COLLATE utf8mb4_unicode_ci
                 ORDER BY (TRIM(df.ficha) COLLATE utf8mb4_unicode_ci = TRIM(REPLACE(REPLACE(a.ficha, '\r', ''), '\n', '')) COLLATE utf8mb4_unicode_ci) DESC, df.id DESC LIMIT 1) AS df_estado_aprendiz,
                (SELECT df.estado_ficha FROM base_df14 df 
                 WHERE TRIM(df.documento) COLLATE utf8mb4_unicode_ci = TRIM(REPLACE(REPLACE(a.documento, '\r', ''), '\n', '')) COLLATE utf8mb4_unicode_ci
                 ORDER BY (TRIM(df.ficha) COLLATE utf8mb4_unicode_ci = TRIM(REPLACE(REPLACE(a.ficha, '\r', ''), '\n', '')) COLLATE utf8mb4_unicode_ci) DESC, df.id DESC LIMIT 1) AS df_estado_ficha
                FROM aprendices_mes a 
                LEFT JOIN base_fic fic ON TRIM(a.documento) COLLATE utf8mb4_unicode_ci = TRIM(fic.documento) COLLATE utf8mb4_unicode_ci 
                WHERE a.mes_id = ?
                ORDER BY a.nombre ASC";

    $stmtList = $pdo->prepare($sqlList);
    $stmtList->execute([$mes_id]);
    $aprendices = $stmtList->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <div class="container-fluid px-4 py-3">
        <!-- CABECERA PLANILLA GOVTECH -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
            <div>
                <div class="d-inline-flex align-items-center gap-2 bg-success bg-opacity-10 border border-success border-opacity-25 text-success rounded-pill px-3 py-1 mb-2 small fw-bold">
                    <i class="bi bi-shield-check"></i> Planilla de Ejecución Oficial
                </div>
                <h2 class="fw-extrabold text-dark mb-1">Periodo: <?= htmlspecialchars($datos_mes['mes']) ?></h2>
                <div class="text-muted fw-bold small">
                    <i class="bi bi-folder2-open text-primary me-1"></i> Convocatoria: <?= htmlspecialchars($convocatoria_nombre) ?> &bull; 
                    <i class="bi bi-people-fill text-success ms-2 me-1"></i> Ocupación: <?= $inscritos ?> / <?= $cupos_totales ?>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <div class="dropdown">
                    <button class="btn btn-success fw-bold rounded-pill px-4 shadow-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-file-earmark-excel-fill me-1"></i> Reportes Excel
                    </button>
                    <ul class="dropdown-menu shadow border-0 mt-2">
                        <li><h6 class="dropdown-header text-uppercase fw-bold" style="font-size: 0.7rem;">Documentos Oficiales</h6></li>
                        <li><a class="dropdown-item fw-bold text-success" href="exportar_sena.php?mes_id=<?= $mes_id ?>"><i class="bi bi-file-earmark-spreadsheet-fill me-2"></i> Planilla de Pago SENA</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><h6 class="dropdown-header text-uppercase fw-bold" style="font-size: 0.7rem;">Análisis Interno</h6></li>
                        <li><a class="dropdown-item fw-bold text-dark" href="exportar.php?mes_id=<?= $mes_id ?>&tipo=todos"><i class="bi bi-people-fill text-secondary me-2"></i> Reporte General</a></li>
                        <li><a class="dropdown-item fw-bold text-primary" href="exportar.php?mes_id=<?= $mes_id ?>&tipo=cumple"><i class="bi bi-check-circle-fill me-2"></i> Aprobados (Cumplen)</a></li>
                        <li><a class="dropdown-item fw-bold text-danger" href="exportar.php?mes_id=<?= $mes_id ?>&tipo=cancelados"><i class="bi bi-x-circle-fill me-2"></i> Cancelados</a></li>
                    </ul>
                </div>

                <a href="?convocatoria_id=<?= $convocatoria_id ?>" class="btn btn-outline-secondary fw-bold rounded-pill px-4">
                    <i class="bi bi-arrow-left me-1"></i> Regresar
                </a>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <!-- PANEL DE REPORTE FINANCIERO -->
            <div class="col-lg-5">
                <div class="card shadow-sm bg-white border-0 h-100">
                    <div class="card-body d-flex flex-column justify-content-center border-start border-4 border-success rounded-end">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-uppercase fw-bold text-muted" style="font-size: 0.75rem;">Ejecución Financiera del Mes</span>
                            <i class="bi bi-cash-coin fs-4 text-success opacity-50"></i>
                        </div>
                        <?php if (!$es_visualizador): ?>
                        <form method="POST" class="d-flex gap-2">
                            <div class="input-group">
                                <span class="input-group-text bg-light text-dark fw-bold">$</span>
                                <input type="number" name="valor_ejecutado" class="form-control fw-bold text-dark" 
                                       value="<?= $datos_mes['valor_pagado'] > 0 ? $datos_mes['valor_pagado'] : '' ?>" placeholder="Monto total pagado..." required>
                            </div>
                            <button type="submit" name="reportar_valor" class="btn btn-success fw-bold text-nowrap px-3 shadow-sm">
                                <?= $datos_mes['valor_pagado'] > 0 ? 'Actualizar' : 'Reportar' ?>
                            </button>
                        </form>
                        <?php else: ?>
                        <div class="fw-extrabold fs-3 text-success">
                            <?= $datos_mes['valor_pagado'] > 0 ? '$'.number_format($datos_mes['valor_pagado'], 2, ',', '.') : '<span class="text-muted fs-5">Pendiente de Reporte</span>' ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- BARRA DE BÚSQUEDA Y FILTRO EN TIEMPO REAL -->
            <div class="col-lg-7">
                <div class="card shadow-sm bg-white border-0 h-100">
                    <div class="card-body d-flex flex-column justify-content-center">
                        <div class="row g-2 align-items-center">
                            <div class="col-md-7">
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                                    <input type="text" id="filtroTexto" class="form-control border-start-0" placeholder="Buscar por Nombre, Documento o Ficha..." autofocus>
                                </div>
                            </div>
                            <div class="col-md-5 d-flex gap-2">
                                <select id="filtroEstado" class="form-select fw-bold text-dark">
                                    <option value="todos">Todos los estados</option>
                                    <option value="Cumple">Aprobados</option>
                                    <option value="Cancelado">Cancelados</option>
                                </select>
                                <button type="button" id="btnLimpiarFiltros" class="btn btn-light border fw-bold text-secondary px-3" title="Limpiar filtros">
                                    <i class="bi bi-eraser-fill"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABLA DE APRENDICES -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white fw-bold d-flex flex-column flex-md-row justify-content-between align-items-md-center py-3">
                <div class="mb-2 mb-md-0">
                    <i class="bi bi-card-checklist text-success me-2"></i> Evaluación de Aprendices 
                    <span class="ms-2 badge bg-light text-dark opacity-75"><span id="conteoVisibles"><?= count($aprendices) ?></span> listados</span>
                </div>
                
                <?php if(!$es_visualizador): ?>
                    <?php if($cupos_disponibles > 0): ?>
                        <button class="btn btn-sm btn-success fw-bold rounded-pill px-3 shadow-sm border border-light border-opacity-25" onclick="document.getElementById('cajaAgregar').classList.toggle('d-none')">
                            <i class="bi bi-person-plus-fill me-1"></i> Añadir Aprendices (Faltan <?= $cupos_disponibles ?>)
                        </button>
                    <?php else: ?>
                        <span class="badge bg-danger fs-6 border border-light shadow-sm px-3 py-2 rounded-pill"><i class="bi bi-lock-fill me-1"></i> CUPO LLENO</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <?php if(!$es_visualizador && $cupos_disponibles > 0): ?>
            <div id="cajaAgregar" class="card-body bg-light border-bottom d-none p-4">
                <div class="row g-4">
                    <!-- Manual -->
                    <div class="col-md-5">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-white border-bottom fw-bold py-3 text-dark"><i class="bi bi-person-lines-fill text-primary me-2"></i> Registro Manual</div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="row g-2">
                                        <div class="col-4 mb-2"><input type="text" name="tipo_doc" class="form-control bg-light" placeholder="Tipo" required></div>
                                        <div class="col-8 mb-2"><input type="number" name="documento" class="form-control bg-light" placeholder="Documento" required></div>
                                        <div class="col-12 mb-2"><input type="text" name="nombre" class="form-control bg-light text-uppercase" placeholder="Nombre Completo" required></div>
                                        <div class="col-12 mb-3"><input type="text" name="ficha" class="form-control bg-light" placeholder="N° Ficha" required></div>
                                    </div>
                                    <button type="submit" name="guardar_manual" class="btn btn-primary w-100 fw-bold shadow-sm rounded-pill"><i class="bi bi-plus-lg me-1"></i> Agregar 1 Aprendiz</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <!-- CSV -->
                    <div class="col-md-7">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-white border-bottom fw-bold py-3 text-dark"><i class="bi bi-filetype-csv text-warning me-2"></i> Carga de "Aprobados" (CSV)</div>
                            <div class="card-body d-flex flex-column justify-content-center">
                                <div class="d-flex align-items-start mb-4">
                                    <i class="bi bi-info-circle-fill text-primary fs-4 me-3 mt-1"></i>
                                    <p class="small text-muted mb-0 lh-sm">
                                        Sube el archivo <b>CSV de Aprobados</b> que exportaste del mes anterior. El sistema extraerá automáticamente las cédulas, nombres y fichas.
                                    </p>
                                </div>
                                <form method="POST" enctype="multipart/form-data" class="d-flex gap-2">
                                    <input type="file" name="archivo_csv" class="form-control bg-light border-warning" accept=".csv" required>
                                    <button type="submit" name="subir_csv" class="btn btn-warning fw-bold text-nowrap shadow-sm px-4 rounded-pill text-dark"><i class="bi bi-cloud-arrow-up-fill me-1"></i> Subir y Cruzar CSV</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                    <table class="table table-hover table-sm align-middle mb-0" id="tablaAprendices" style="font-size: 0.85em; table-layout: fixed; width: 100%; min-width: 1120px;">
                        <colgroup>
                            <col style="width: 3%;">  <!-- # -->
                            <col style="width: 5%;">  <!-- Tipo Doc -->
                            <col style="width: 9%;">  <!-- Documento -->
                            <col style="width: 18%;"> <!-- Nombre Completo -->
                            <col style="width: 14%;"> <!-- Ficha Académica -->
                            <col style="width: 11%;"> <!-- Estado DF-14 -->
                            <col style="width: 5%;">  <!-- FIC (Separado) -->
                            <col style="width: 6%;">  <!-- CTO -->
                            <col style="width: 6%;">  <!-- V.LAB -->
                            <col style="width: 7%;">  <!-- E.PROD -->
                            <col style="width: 12%;"> <!-- Estado Final -->
                            <?php if(!$es_visualizador): ?><col style="width: 4%;"><?php endif; ?> <!-- Eliminar -->
                        </colgroup>
                        <thead class="table-light sticky-top shadow-sm" style="z-index: 10;">
                            <tr>
                                <th class="text-center text-muted px-1 py-3 border-bottom">#</th>
                                <th class="text-center px-1 py-3 border-bottom">Tipo</th>
                                <th class="py-3 border-bottom">Documento</th>
                                <th class="py-3 border-bottom">Nombre Completo</th>
                                <th class="py-3 border-bottom">Ficha y Tiempos</th>
                                <th class="text-center py-3 border-bottom" title="Estado del Aprendiz en el DF-14">ESTADO DF-14</th>
                                <th class="text-center text-nowrap px-1 py-3 border-bottom" title="Apoyo FIC Automático">FIC ⚡</th>
                                <th class="text-center text-nowrap px-1 py-3 border-bottom" title="¿Tiene Contrato?">CONTRATO</th>
                                <th class="text-center text-nowrap px-1 py-3 border-bottom" title="¿Tiene Vínculo Laboral?">V.LABORAL</th>
                                <th class="text-center text-nowrap px-1 text-success py-3 border-bottom" title="¿Ya inició Etapa Productiva?">E.PRODUC.</th>
                                <th class="px-2 py-3 border-bottom">Estado Final</th>
                                <?php if(!$es_visualizador): ?><th class="text-center px-1 py-3 border-bottom"><i class="bi bi-trash-fill text-danger"></i></th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="tbodyAprendices">
                            <?php if(count($aprendices) === 0): ?>
                                <tr id="filaSinDatos"><td colspan="<?= $es_visualizador ? '11' : '12' ?>" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-2 d-block mb-2"></i> No hay aprendices registrados en este mes.</td></tr>
                            <?php endif; ?>
                            
                            <?php 
                            $item = 1; 
                            $hoy = new DateTime(); 
                            $hoy->setTime(0,0,0);
                            
                            foreach ($aprendices as $a): 
                                
                                $estado_calculado = 'Cumple';
                                $novedad_texto = 'Sin novedad';
                                $alerta_fechas = '';

                                // Cálculos de Tiempo (30 días calendario)
                                if (!empty($a['fecha_fin_lectiva'])) {
                                    $fin_lectiva = new DateTime($a['fecha_fin_lectiva']);
                                    $fecha_limite = clone $fin_lectiva;
                                    $fecha_limite->modify('+30 days');
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
                                        $alerta_fechas = "<span class='text-white fw-bold bg-success bg-opacity-10 px-2 py-1 rounded'><i class='bi bi-shield-check'></i> Salvado por E.P.</span>";
                                    }
                                }

                                // VALIDACIÓN: ALERTA SOLO SI LA FICHA ESTÁ TERMINADA EN DF-14
                                $ficha_terminada = false;
                                if (!empty($a['df_estado_ficha']) && stripos($a['df_estado_ficha'], 'terminada') !== false) {
                                    $ficha_terminada = true;
                                }

                                // REGLAS EXCLUYENTES AUTOMÁTICAS
                                if ($a['tiene_fic_global'] == 'SI') {
                                    $estado_calculado = 'Cancelado';
                                    $novedad_texto = 'Cancelado por FIC Automático';
                                } elseif ($es_transporte && $a['activo_en_regular'] == 'SI') {
                                    $estado_calculado = 'Cancelado';
                                    $novedad_texto = 'Activo en Apoyo Regular';
                                } elseif ($a['contrato_aprendizaje'] == 'SI') {
                                    $estado_calculado = 'Cancelado';
                                    $novedad_texto = 'Cancelado por Contrato';
                                } elseif ($a['vinculo_laboral'] == 'SI') {
                                    $estado_calculado = 'Cancelado';
                                    $novedad_texto = 'Cancelado por Vínculo Laboral';
                                }

                                // Sincronización silenciosa con la BD
                                if (!$es_visualizador && $a['estado_seguimiento'] !== $estado_calculado) {
                                    $pdo->prepare("UPDATE aprendices_mes SET estado_seguimiento = ? WHERE id = ?")->execute([$estado_calculado, $a['id']]);
                                }
                            ?>
                            
                            <tr id="fila_<?= $a['id'] ?>" 
                                class="fila-aprendiz <?= $estado_calculado == 'Cancelado' ? 'table-danger bg-opacity-10' : '' ?>"
                                data-estado="<?= $estado_calculado ?>"
                                data-busqueda="<?= strtolower(htmlspecialchars($a['nombre'] . ' ' . $a['documento'] . ' ' . $a['ficha'])) ?>">

                                <td class="text-center fw-bold text-muted px-1 align-middle">
                                    <?= $item++ ?>
                                    <?php if(!$es_visualizador): ?>
                                    <form method="POST" id="form_<?= $a['id'] ?>" action="#fila_<?= $a['id'] ?>" style="display:none;">
                                        <input type="hidden" name="evaluar_rapido" value="1">
                                        <input type="hidden" name="aprendiz_id" value="<?= $a['id'] ?>">
                                    </form>
                                    <?php endif; ?>
                                </td>

                                <td class="text-center px-1 align-middle"><span class="badge bg-light text-dark border px-2 py-1"><?= htmlspecialchars($a['tipo_documento']) ?></span></td>

                                <td class="fw-bold text-nowrap text-dark align-middle"><?= htmlspecialchars($a['documento']) ?></td>

                                <td class="fw-bold text-dark lh-sm text-break align-middle" style="font-size: 0.95em;"><?= htmlspecialchars($a['nombre']) ?></td>
                                
                                <td class="lh-sm py-2 align-middle">
                                    <div class="d-flex flex-column gap-1">
                                        <span class="badge bg-secondary bg-opacity-10 text-dark border text-start w-100"><i class="bi bi-hash text-muted"></i> <?= htmlspecialchars($a['ficha']) ?></span>
                                        <?php if(!empty($a['fecha_fin_lectiva'])): ?>
                                            <span class="badge bg-info bg-opacity-10 text-dark fw-bold border text-start w-100"><i class="bi bi-flag-fill text-info me-1"></i> Fin: <?= htmlspecialchars($a['fecha_fin_lectiva']) ?></span>
                                            <div class="mt-1" style="font-size: 0.9em;"><?= $alerta_fechas ?></div>
                                        <?php else: ?>
                                            <span class="badge bg-warning bg-opacity-10 text-dark border text-start w-100"><i class="bi bi-exclamation-triangle-fill text-warning"></i> Sin Ficha</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                
                                <!-- COLUMNA ESTADO DF-14 (Muestra estado del aprendiz y alerta SOLO si la ficha está terminada) -->
                                <td class="text-center px-2 align-middle">
                                    <?php if (!empty($a['df_estado_aprendiz'])): ?>
                                        <span class="badge bg-light text-dark border fw-bold px-2 py-1" style="font-size: 0.75rem;">
                                            <?= htmlspecialchars($a['df_estado_aprendiz']) ?>
                                        </span>
                                        
                                        <?php if ($ficha_terminada): ?>
                                            <div class="badge bg-danger text-white fw-bold mt-1 p-1 shadow-sm w-100 text-wrap" style="font-size: 0.68rem; line-height: 1.1;">
                                                <i class="bi bi-exclamation-triangle-fill me-1"></i> Ficha Terminada
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted small" style="font-size: 0.72rem;">No encontrado</span>
                                    <?php endif; ?>
                                </td>

                                <!-- COLUMNA FIC AUTOMÁTICO (SEPARADA COMO ESTABA ORIGINALMENTE) -->
                                <td class="text-center px-1 align-middle">
                                    <input class="form-check-input <?= $a['tiene_fic_global'] == 'SI' ? 'bg-danger border-danger' : 'border-dark bg-secondary opacity-50' ?> shadow-sm m-0" 
                                           style="width:1.25rem; height:1.25rem; cursor:not-allowed;" 
                                           type="checkbox" 
                                           <?= $a['tiene_fic_global'] == 'SI' ? 'checked' : '' ?> 
                                           disabled>
                                    <?php if ($es_transporte && $a['activo_en_regular'] == 'SI'): ?>
                                        <span class="badge bg-danger shadow-sm border border-danger fw-bold mt-1 d-block" style="font-size: 0.62rem;" title="Activo en Apoyo Regular">REGULAR</span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-center px-1 align-middle">
                                    <div class="form-check d-flex justify-content-center m-0">
                                        <input class="form-check-input border-secondary shadow-sm" style="width:1.4rem; height:1.4rem; cursor:pointer;" type="checkbox" name="contrato" value="SI" form="form_<?= $a['id'] ?>" <?= $a['contrato_aprendizaje'] == 'SI' ? 'checked' : '' ?> <?= $es_visualizador ? 'disabled' : '' ?> onchange="document.getElementById('form_<?= $a['id'] ?>').submit();">
                                    </div>
                                </td>
                                <td class="text-center px-1 align-middle">
                                    <div class="form-check d-flex justify-content-center m-0">
                                        <input class="form-check-input border-secondary shadow-sm" style="width:1.4rem; height:1.4rem; cursor:pointer;" type="checkbox" name="vinculo" value="SI" form="form_<?= $a['id'] ?>" <?= $a['vinculo_laboral'] == 'SI' ? 'checked' : '' ?> <?= $es_visualizador ? 'disabled' : '' ?> onchange="document.getElementById('form_<?= $a['id'] ?>').submit();">
                                    </div>
                                </td>
                                <td class="text-center px-1 align-middle">
                                    <div class="form-check d-flex justify-content-center m-0">
                                        <input class="form-check-input border-success shadow-sm" style="width:1.4rem; height:1.4rem; cursor:pointer;" type="checkbox" name="etapa_productiva" value="SI" form="form_<?= $a['id'] ?>" <?= $a['etapa_productiva'] == 'SI' ? 'checked' : '' ?> <?= $es_visualizador ? 'disabled' : '' ?> onchange="document.getElementById('form_<?= $a['id'] ?>').submit();">
                                    </div>
                                </td>
                                
                                <td class="lh-sm px-2 align-middle">
                                    <?php if($estado_calculado == 'Cumple'): ?>
                                        <span class="badge bg-success shadow-sm mb-1 px-3 py-2 rounded-pill"><i class="bi bi-check-circle-fill me-1"></i> CUMPLE</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger shadow-sm mb-1 px-3 py-2 rounded-pill"><i class="bi bi-x-circle-fill me-1"></i> CANCELADO</span>
                                    <?php endif; ?>
                                    
                                    <div class="small fw-bold mt-1 <?= $estado_calculado == 'Cancelado' ? 'text-danger' : 'text-success' ?>" style="line-height: 1.15; font-size: 0.78rem;">
                                        <?= $novedad_texto ?>
                                    </div>
                                </td>

                                <?php if(!$es_visualizador): ?>
                                <td class="text-center px-1 align-middle">
                                    <form method="POST" class="m-0" onsubmit="return confirm('¿Estás seguro de eliminar a <?= addslashes($a['nombre']) ?>? Esto liberará su cupo en este mes.');">
                                        <input type="hidden" name="id_eliminar" value="<?= $a['id'] ?>">
                                        <button type="submit" name="eliminar_aprendiz" class="btn btn-sm btn-light border text-danger rounded-circle shadow-sm" style="width: 32px; height: 32px; padding: 0;" title="Eliminar y liberar cupo">
                                            <i class="bi bi-trash3-fill"></i>
                                        </button>
                                    </form>
                                </td>
                                <?php endif; ?>

                            </tr>
                            <?php endforeach; ?>
                            
                            <tr id="sinCoincidencias" style="display: none;">
                                <td colspan="<?= $es_visualizador ? '11' : '12' ?>" class="text-center py-5 text-muted fw-bold bg-light">
                                    <i class="bi bi-search fs-2 d-block mb-2 opacity-50"></i> No se encontraron aprendices que coincidan con los filtros.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const inputTexto = document.getElementById('filtroTexto');
        const selectEstado = document.getElementById('filtroEstado');
        const btnLimpiar = document.getElementById('btnLimpiarFiltros');
        const filas = document.querySelectorAll('.fila-aprendiz');
        const conteoSpan = document.getElementById('conteoVisibles');
        const filaSinCoincidencias = document.getElementById('sinCoincidencias');

        function filtrarTabla() {
            const texto = inputTexto.value.toLowerCase().trim();
            const selectVal = selectEstado.value;
            let visibles = 0;

            filas.forEach(function (fila) {
                const dataBusqueda = fila.getAttribute('data-busqueda') || '';
                const dataEstado = fila.getAttribute('data-estado') || '';

                const coincideTexto = texto === '' || dataBusqueda.includes(texto);
                const coincideEstado = (selectVal === 'todos' || dataEstado === selectVal);

                if (coincideTexto && coincideEstado) {
                    fila.style.display = '';
                    visibles++;
                } else {
                    fila.style.display = 'none';
                }
            });

            if (conteoSpan) {
                conteoSpan.textContent = visibles;
            }

            if (filaSinCoincidencias) {
                filaSinCoincidencias.style.display = (visibles === 0 && filas.length > 0) ? '' : 'none';
            }
        }

        inputTexto.addEventListener('input', filtrarTabla);
        selectEstado.addEventListener('change', filtrarTabla);

        btnLimpiar.addEventListener('click', function () {
            inputTexto.value = '';
            selectEstado.value = 'todos';
            filtrarTabla();
            inputTexto.focus();
        });
    });
    </script>

<?php 
}
require_once '../../includes/footer.php'; 
?>