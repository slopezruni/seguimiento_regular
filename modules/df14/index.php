<?php
require_once '../../config/database.php';
require_once '../../includes/header.php';

$rol_actual = $_SESSION['rol'] ?? 'visualizador';
$es_admin = ($rol_actual === 'admin');
$mensaje = "";

// 1. AUTO-CREACIÓN Y AJUSTE DE ÍNDICES DE LA TABLA (Permite mismo aprendiz en varias fichas)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS base_df14 (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipo_documento VARCHAR(20),
        documento VARCHAR(50) NOT NULL,
        nombre_completo VARCHAR(255),
        ficha VARCHAR(50) NOT NULL,
        estado_ficha VARCHAR(100),
        estado_aprendiz VARCHAR(100),
        fecha_carga TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY idx_doc_ficha (documento, ficha)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Si la tabla ya existía con el índice viejo (solo documento), lo actualizamos a (documento, ficha)
    $stmtIdx = $pdo->query("SHOW INDEX FROM base_df14 WHERE Key_name = 'documento'");
    if ($stmtIdx && $stmtIdx->rowCount() > 0) {
        $pdo->exec("ALTER TABLE base_df14 DROP INDEX documento");
        $pdo->exec("ALTER TABLE base_df14 ADD UNIQUE KEY idx_doc_ficha (documento, ficha)");
    }
} catch (PDOException $e) {
    // Continuar normalmente
}

// 2. ACCIONES EXCLUSIVAS DEL ADMINISTRADOR
if ($es_admin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // VACIAR BASE DE DATOS COMPLETA
    if (isset($_POST['vaciar_base'])) {
        try {
            $pdo->exec("TRUNCATE TABLE base_df14");
            $mensaje = "<div class='alert alert-warning border-0 shadow-sm fw-bold mb-4'><i class='bi bi-trash3-fill me-2'></i> La base de datos DF-14 ha sido completamente vaciada.</div>";
        } catch (PDOException $e) {
            $mensaje = "<div class='alert alert-danger border-0 shadow-sm fw-bold mb-4'><i class='bi bi-exclamation-triangle-fill me-2'></i> Error al vaciar la base: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }

    // CARGAR ARCHIVO DF-14
    if (isset($_POST['subir_df14'])) {
        if (isset($_FILES['archivo_csv']) && $_FILES['archivo_csv']['error'] == 0) {
            
            if (isset($_POST['reemplazar_base'])) {
                $pdo->exec("TRUNCATE TABLE base_df14");
            }

            $archivo = $_FILES['archivo_csv']['tmp_name'];
            $lineas = file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            $insertados = 0; $actualizados = 0; $errores = 0;
            
            $stmtInsert = $pdo->prepare("
                INSERT INTO base_df14 (tipo_documento, documento, nombre_completo, ficha, estado_ficha, estado_aprendiz) 
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                tipo_documento = VALUES(tipo_documento),
                nombre_completo = VALUES(nombre_completo), 
                estado_ficha = VALUES(estado_ficha), 
                estado_aprendiz = VALUES(estado_aprendiz)
            ");

            $cabeceras = [];
            foreach($lineas as $index => $linea) {
                // Eliminar BOM UTF-8 si existe en la primera línea
                if ($index === 0) {
                    $linea = preg_replace('/^\xEF\xBB\xBF/', '', $linea);
                }

                $separador = (substr_count($linea, ';') > substr_count($linea, ',')) ? ';' : ',';
                $datos = str_getcsv($linea, $separador);
                
                $datos = array_map(function($val) {
                    return trim(str_replace(['"', "'", "\xC2\xA0", "\r", "\n"], '', $val));
                }, $datos);

                if ($index === 0) {
                    $cabeceras = array_map('strtoupper', $datos);
                    continue;
                }
                
                if (count($datos) < 5) continue;

                $idx_tipo = array_search('TIPO_DOCUMENTO', $cabeceras);
                $idx_doc = array_search('NUMERO_DOCUMENTO', $cabeceras);
                $idx_nom = array_search('NOMBRE', $cabeceras);
                $idx_ape1 = array_search('PRIMER_APELLIDO', $cabeceras);
                $idx_ape2 = array_search('SEGUNDO_APELLIDO', $cabeceras);
                $idx_ficha = array_search('FICHA', $cabeceras);
                $idx_est_f = array_search('ESTADO_FICHA', $cabeceras);
                $idx_est_a = array_search('ESTADO_APRENDIZ', $cabeceras);

                $tipo = $idx_tipo !== false ? ($datos[$idx_tipo] ?? '') : ($datos[10] ?? '');
                $doc = $idx_doc !== false ? ($datos[$idx_doc] ?? '') : ($datos[11] ?? '');
                
                $nom = $idx_nom !== false ? ($datos[$idx_nom] ?? '') : ($datos[12] ?? '');
                $ape1 = $idx_ape1 !== false ? ($datos[$idx_ape1] ?? '') : ($datos[13] ?? '');
                $ape2 = $idx_ape2 !== false ? ($datos[$idx_ape2] ?? '') : ($datos[14] ?? '');
                $nombre_completo = preg_replace('/\s+/', ' ', trim($nom . ' ' . $ape1 . ' ' . $ape2));
                
                $ficha = $idx_ficha !== false ? ($datos[$idx_ficha] ?? '') : ($datos[4] ?? '');
                $estado_f = $idx_est_f !== false ? ($datos[$idx_est_f] ?? '') : ($datos[5] ?? '');
                $estado_a = $idx_est_a !== false ? ($datos[$idx_est_a] ?? '') : ($datos[15] ?? '');

                // Limpiar estrictamente números de documento y ficha para que crucen sin fallos
                $doc_clean = preg_replace('/[^0-9]/', '', $doc);
                $ficha_clean = preg_replace('/[^0-9]/', '', $ficha);

                if (!empty($doc_clean)) {
                    try {
                        $stmtInsert->execute([
                            strtoupper($tipo ?: 'CC'), 
                            $doc_clean, 
                            strtoupper($nombre_completo ?: 'SIN NOMBRE'), 
                            $ficha_clean ?: 'SIN FICHA', 
                            strtoupper($estado_f ?: 'EN EJECUCION'), 
                            strtoupper($estado_a ?: 'FORMACION')
                        ]);
                        if ($stmtInsert->rowCount() == 1) {
                            $insertados++;
                        } else {
                            $actualizados++;
                        }
                    } catch (PDOException $e) {
                        $errores++;
                    }
                }
            }
            $mensaje = "<div class='alert alert-success border-0 shadow-sm fw-bold mb-4'><i class='bi bi-cloud-check-fill me-2'></i> Carga exitosa. Registros guardados: $insertados | Actualizados: $actualizados | Errores: $errores</div>";
        } else {
            $mensaje = "<div class='alert alert-danger border-0 shadow-sm fw-bold mb-4'><i class='bi bi-exclamation-triangle-fill me-2'></i> Error al adjuntar el archivo CSV.</div>";
        }
    }
}

// 3. ESTADÍSTICAS KPI
$total_registros = $pdo->query("SELECT COUNT(*) FROM base_df14")->fetchColumn() ?: 0;
$total_formacion = $pdo->query("SELECT COUNT(*) FROM base_df14 WHERE estado_aprendiz LIKE '%FORMACION%'")->fetchColumn() ?: 0;
$total_fichas = $pdo->query("SELECT COUNT(DISTINCT ficha) FROM base_df14")->fetchColumn() ?: 0;

// 4. BÚSQUEDA Y LISTADO
$busqueda = trim($_GET['q'] ?? '');
$where = "";
$params = [];
if (!empty($busqueda)) {
    $where = "WHERE documento LIKE ? OR nombre_completo LIKE ? OR ficha LIKE ?";
    $params = ["%$busqueda%", "%$busqueda%", "%$busqueda%"];
}

$stmtData = $pdo->prepare("SELECT tipo_documento, documento, nombre_completo, ficha, estado_ficha, estado_aprendiz FROM base_df14 $where ORDER BY id DESC LIMIT 500");
$stmtData->execute($params);
$registros = $stmtData->fetchAll(PDO::FETCH_ASSOC);

function getBadgeFicha($estado) {
    if(stripos($estado, 'EJECUCION') !== false) return 'success';
    if(stripos($estado, 'TERMINADA') !== false) return 'warning text-dark';
    return 'secondary';
}
function getBadgeAprendiz($estado) {
    if(stripos($estado, 'FORMACION') !== false) return 'success';
    if(stripos($estado, 'CANCELADO') !== false || stripos($estado, 'RETIRO') !== false) return 'danger';
    if(stripos($estado, 'CONDICIONADO') !== false || stripos($estado, 'APLAZADO') !== false) return 'warning text-dark';
    if(stripos($estado, 'CERTIFICADO') !== false) return 'primary';
    return 'secondary';
}
?>

<div class="container-fluid px-4 py-3">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <div class="d-inline-flex align-items-center gap-2 bg-success bg-opacity-10 border border-success border-opacity-25 text-white rounded-pill px-3 py-1 mb-2 small fw-bold">
                <i class="bi bi-shield-check"></i> Módulo Oficial SofiaPlus
            </div>
            <h2 class="fw-extrabold text-dark mb-1">Base Maestra DF-14</h2>
            <div class="text-muted fw-bold small">
                Control mensual de estados académicos, matrículas y fichas activas.
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="/seguimiento-regular/index.php" class="btn btn-outline-secondary fw-bold rounded-pill px-4 shadow-sm">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>

    <?= $mensaje ?>

    <div class="row g-3 mb-4">
        <div class="col-xl-4 col-md-6">
            <div class="card bg-white border-0 shadow-sm border-start border-4 border-success h-100 py-2">
                <div class="card-body">
                    <div class="text-uppercase fw-bold text-muted small mb-1">Aprendices en Formación</div>
                    <div class="fs-2 fw-extrabold text-dark"><?= number_format($total_formacion, 0, ',', '.') ?></div>
                    <div class="mt-2 text-success small fw-bold"><i class="bi bi-people-fill me-1"></i> Estado Activo</div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="card bg-white border-0 shadow-sm border-start border-4 border-info h-100 py-2">
                <div class="card-body">
                    <div class="text-uppercase fw-bold text-muted small mb-1">Fichas Registradas</div>
                    <div class="fs-2 fw-extrabold text-dark"><?= number_format($total_fichas, 0, ',', '.') ?></div>
                    <div class="mt-2 text-info small fw-bold"><i class="bi bi-journal-bookmark-fill me-1"></i> Programas activos</div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="card bg-white border-0 shadow-sm border-start border-4 border-dark h-100 py-2">
                <div class="card-body">
                    <div class="text-uppercase fw-bold text-muted small mb-1">Total Registros en Base</div>
                    <div class="fs-2 fw-extrabold text-dark"><?= number_format($total_registros, 0, ',', '.') ?></div>
                    <div class="mt-2 text-muted small fw-bold"><i class="bi bi-database-fill me-1"></i> Almacenados en MySQL</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <?php if ($es_admin): ?>
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-dark text-white fw-bold py-3"><i class="bi bi-cloud-arrow-up-fill text-success me-2"></i> Cargar Reporte DF-14</div>
                <div class="card-body">
                    <p class="small text-muted mb-3">Sube el archivo CSV exportado desde SofiaPlus para actualizar los estados del mes.</p>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <input type="file" name="archivo_csv" class="form-control mb-3 bg-light border-dark" accept=".csv" required>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input border-secondary shadow-sm" type="checkbox" name="reemplazar_base" value="1" id="reemplazarBase" checked>
                            <label class="form-check-label small fw-bold text-dark" for="reemplazarBase">
                                Reemplazar base anterior (Recomendado mes a mes)
                            </label>
                        </div>

                        <button type="submit" name="subir_df14" class="btn btn-success w-100 fw-bold shadow-sm rounded-pill mb-2"><i class="bi bi-database-add me-1"></i> Procesar y Guardar</button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm border-0 border-start border-4 border-danger">
                <div class="card-body">
                    <h6 class="fw-bold text-danger mb-2"><i class="bi bi-exclamation-triangle-fill me-1"></i> Gestión Mensual (Limpieza)</h6>
                    <p class="small text-muted mb-3">Vacía completamente la base de datos antes de hacer una carga mensual nueva.</p>
                    
                    <form method="POST" onsubmit="return confirm('¿Estás 100% seguro de vaciar toda la base de datos DF-14?');">
                        <button type="submit" name="vaciar_base" class="btn btn-outline-danger btn-sm w-100 fw-bold rounded-pill">
                            <i class="bi bi-trash3-fill me-1"></i> Vaciar Base DF-14 Completa
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="<?= $es_admin ? 'col-lg-8' : 'col-lg-12' ?>">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div class="fw-bold text-dark">
                        <i class="bi bi-table text-success me-2"></i> Directorio y Estados DF-14
                    </div>
                    
                    <form method="GET" class="input-group" style="max-width: 350px;">
                        <input type="text" name="q" class="form-control bg-light border-end-0" placeholder="Buscar cédula, nombre o ficha..." value="<?= htmlspecialchars($busqueda) ?>">
                        <button type="submit" class="btn btn-light border-start-0 border text-success fw-bold"><i class="bi bi-search"></i></button>
                        <?php if(!empty($busqueda)): ?>
                            <a href="index.php" class="btn btn-light border text-danger" title="Limpiar"><i class="bi bi-x-lg"></i></a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 580px; overflow-y: auto;">
                        <table class="table table-hover table-sm align-middle mb-0" style="font-size: 0.85em; width: 100%; min-width: 850px;">
                            <thead class="table-light sticky-top shadow-sm">
                                <tr>
                                    <th class="ps-4 py-3 border-bottom text-center" style="width: 8%;">Tipo</th>
                                    <th class="py-3 border-bottom" style="width: 15%;">Número Documento</th>
                                    <th class="py-3 border-bottom" style="width: 32%;">Nombre Completo</th>
                                    <th class="text-center py-3 border-bottom" style="width: 12%;">Ficha</th>
                                    <th class="text-center py-3 border-bottom" style="width: 18%;">Estado de Ficha</th>
                                    <th class="text-center py-3 border-bottom pe-4" style="width: 15%;">Estado Aprendiz</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($registros) === 0): ?>
                                    <tr><td colspan="6" class="text-center py-5 text-muted fw-bold"><i class="bi bi-inbox fs-2 d-block mb-2 opacity-50"></i> No hay registros almacenados. Sube un archivo CSV para comenzar.</td></tr>
                                <?php endif; ?>

                                <?php foreach ($registros as $r): 
                                    $claseFicha = getBadgeFicha($r['estado_ficha']);
                                    $claseAprendiz = getBadgeAprendiz($r['estado_aprendiz']);
                                ?>
                                <tr>
                                    <td class="ps-4 text-center fw-bold text-muted align-middle"><?= htmlspecialchars($r['tipo_documento']) ?></td>
                                    <td class="fw-bold text-dark align-middle"><?= htmlspecialchars($r['documento']) ?></td>
                                    <td class="fw-bold text-dark lh-sm text-uppercase align-middle"><?= htmlspecialchars($r['nombre_completo']) ?></td>
                                    <td class="text-center align-middle">
                                        <span class="badge bg-light text-dark border px-2 py-1"><i class="bi bi-hash text-muted"></i> <?= htmlspecialchars($r['ficha']) ?></span>
                                    </td>
                                    <td class="text-center align-middle">
                                        <span class="badge bg-<?= $claseFicha ?> px-2 py-1">
                                            <?= htmlspecialchars($r['estado_ficha']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center align-middle pe-4">
                                        <span class="badge bg-<?= $claseAprendiz ?> shadow-sm px-2 py-1 rounded-pill w-100 text-truncate" title="<?= htmlspecialchars($r['estado_aprendiz']) ?>">
                                            <?= htmlspecialchars($r['estado_aprendiz']) ?>
                                        </span>
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

<?php require_once '../../includes/footer.php'; ?>