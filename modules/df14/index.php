<?php
require_once '../../config/database.php';
require_once '../../includes/header.php';

$es_visualizador = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'visualizador');
$mensaje = "";

// 1. AUTO-CREACIÓN DE LA TABLA EN LA BASE DE DATOS (Si no existe)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS base_df14 (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipo_documento VARCHAR(20),
        documento VARCHAR(50) NOT NULL UNIQUE,
        nombre_completo VARCHAR(255),
        ficha VARCHAR(50),
        estado_ficha VARCHAR(100),
        estado_aprendiz VARCHAR(100),
        fecha_carga TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (PDOException $e) {
    // Ignorar si ya existe
}

// 2. LÓGICA DE CARGA MASIVA DEL ARCHIVO DF-14
if (!$es_visualizador && isset($_POST['subir_df14'])) {
    if (isset($_FILES['archivo_csv']) && $_FILES['archivo_csv']['error'] == 0) {
        
        // Si el usuario marcó reemplazar toda la base, limpiamos la tabla primero
        if (isset($_POST['reemplazar_base'])) {
            $pdo->exec("TRUNCATE TABLE base_df14");
        }

        $archivo = $_FILES['archivo_csv']['tmp_name'];
        $lineas = file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        $insertados = 0; $actualizados = 0; $errores = 0;
        
        // Preparar sentencia con UPDATE automático si la cédula ya existe
        $stmtInsert = $pdo->prepare("
            INSERT INTO base_df14 (tipo_documento, documento, nombre_completo, ficha, estado_ficha, estado_aprendiz) 
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            tipo_documento = VALUES(tipo_documento),
            nombre_completo = VALUES(nombre_completo), 
            estado_ficha = VALUES(estado_ficha), 
            estado_aprendiz = VALUES(estado_aprendiz),
            ficha = VALUES(ficha)
        ");

        $cabeceras = [];
        foreach($lineas as $index => $linea) {
            $separador = strpos($linea, ';') !== false ? ';' : ',';
            $datos = str_getcsv($linea, $separador);
            
            // Limpieza de caracteres extraños y comillas
            $datos = array_map(function($val) {
                return trim(str_replace(['"', "'", "\xC2\xA0"], '', $val));
            }, $datos);

            if ($index === 0) {
                $cabeceras = array_map('strtoupper', $datos);
                continue;
            }
            
            if (count($datos) < 15) continue;

            // Búsqueda inteligente de columnas sin importar el orden
            $idx_tipo = array_search('TIPO_DOCUMENTO', $cabeceras);
            $idx_doc = array_search('NUMERO_DOCUMENTO', $cabeceras);
            $idx_nom = array_search('NOMBRE', $cabeceras);
            $idx_ape1 = array_search('PRIMER_APELLIDO', $cabeceras);
            $idx_ape2 = array_search('SEGUNDO_APELLIDO', $cabeceras);
            $idx_ficha = array_search('FICHA', $cabeceras);
            $idx_est_f = array_search('ESTADO_FICHA', $cabeceras);
            $idx_est_a = array_search('ESTADO_APRENDIZ', $cabeceras);

            $tipo = $idx_tipo !== false ? $datos[$idx_tipo] : ($datos[10] ?? '');
            $doc = $idx_doc !== false ? $datos[$idx_doc] : ($datos[11] ?? '');
            
            // Unir nombres y apellidos
            $nom = $idx_nom !== false ? $datos[$idx_nom] : ($datos[12] ?? '');
            $ape1 = $idx_ape1 !== false ? $datos[$idx_ape1] : ($datos[13] ?? '');
            $ape2 = $idx_ape2 !== false ? $datos[$idx_ape2] : ($datos[14] ?? '');
            $nombre_completo = preg_replace('/\s+/', ' ', trim($nom . ' ' . $ape1 . ' ' . $ape2));
            
            $ficha = $idx_ficha !== false ? $datos[$idx_ficha] : ($datos[4] ?? '');
            $estado_f = $idx_est_f !== false ? $datos[$idx_est_f] : ($datos[5] ?? '');
            $estado_a = $idx_est_a !== false ? $datos[$idx_est_a] : ($datos[15] ?? '');

            $doc_clean = preg_replace('/[^0-9]/', '', $doc);

            if (!empty($doc_clean)) {
                try {
                    $stmtInsert->execute([
                        strtoupper($tipo), 
                        $doc_clean, 
                        strtoupper($nombre_completo), 
                        $ficha, 
                        strtoupper($estado_f), 
                        strtoupper($estado_a)
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
        $mensaje = "<div class='alert alert-success border-0 shadow-sm fw-bold mb-4'><i class='bi bi-cloud-check-fill me-2'></i> Archivo procesado con éxito. Nuevos: $insertados | Actualizados: $actualizados | Errores: $errores</div>";
    } else {
        $mensaje = "<div class='alert alert-danger border-0 shadow-sm fw-bold mb-4'><i class='bi bi-exclamation-triangle-fill me-2'></i> Error al subir el archivo DF-14. Verifica el formato.</div>";
    }
}

// 3. ESTADÍSTICAS KPI (Rápidas desde BD)
$total_registros = $pdo->query("SELECT COUNT(*) FROM base_df14")->fetchColumn() ?: 0;
$total_formacion = $pdo->query("SELECT COUNT(*) FROM base_df14 WHERE estado_aprendiz = 'FORMACION'")->fetchColumn() ?: 0;
$total_fichas = $pdo->query("SELECT COUNT(DISTINCT ficha) FROM base_df14")->fetchColumn() ?: 0;

// 4. LÓGICA DE BÚSQUEDA Y LISTADO
$busqueda = trim($_GET['q'] ?? '');
$where = "";
$params = [];
if (!empty($busqueda)) {
    $where = "WHERE documento LIKE ? OR nombre_completo LIKE ? OR ficha LIKE ?";
    $params = ["%$busqueda%", "%$busqueda%", "%$busqueda%"];
}

// Limitamos a 500 para proteger el servidor al cargar bases enormes, se fuerza a usar el buscador para los demás.
$stmtData = $pdo->prepare("SELECT * FROM base_df14 $where ORDER BY id DESC LIMIT 500");
$stmtData->execute($params);
$registros = $stmtData->fetchAll(PDO::FETCH_ASSOC);

// Funciones para colores de estado GovTech
function getBadgeFicha($estado) {
    if(strpos($estado, 'EJECUCION') !== false) return 'success';
    if(strpos($estado, 'TERMINADA') !== false) return 'warning';
    return 'secondary';
}
function getBadgeAprendiz($estado) {
    if(strpos($estado, 'FORMACION') !== false) return 'success';
    if(strpos($estado, 'CANCELADO') !== false || strpos($estado, 'RETIRO') !== false) return 'danger';
    if(strpos($estado, 'CONDICIONADO') !== false || strpos($estado, 'APLAZADO') !== false || strpos($estado, 'TRASLADADO') !== false) return 'warning text-dark';
    if(strpos($estado, 'CERTIFICADO') !== false) return 'primary';
    return 'secondary';
}
?>

<div class="container-fluid px-4 py-3">
    <!-- CABECERA DEL MÓDULO -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <div class="d-inline-flex align-items-center gap-2 bg-success bg-opacity-10 border border-success border-opacity-25 text-success rounded-pill px-3 py-1 mb-2 small fw-bold">
                <i class="bi bi-shield-check"></i> Origen: SofiaPlus
            </div>
            <h2 class="fw-extrabold text-dark mb-1">Base Maestra DF-14</h2>
            <div class="text-muted fw-bold small">
                Estado académico, matrículas y validación de aprendices.
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="/seguimiento-regular/index.php" class="btn btn-outline-secondary fw-bold rounded-pill px-4 shadow-sm">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>

    <?= $mensaje ?>

    <!-- TARJETAS KPI -->
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
                    <div class="text-uppercase fw-bold text-muted small mb-1">Fichas Diferentes</div>
                    <div class="fs-2 fw-extrabold text-dark"><?= number_format($total_fichas, 0, ',', '.') ?></div>
                    <div class="mt-2 text-info small fw-bold"><i class="bi bi-journal-bookmark-fill me-1"></i> Extraídas del reporte</div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="card bg-white border-0 shadow-sm border-start border-4 border-dark h-100 py-2">
                <div class="card-body">
                    <div class="text-uppercase fw-bold text-muted small mb-1">Total de Registros Cargados</div>
                    <div class="fs-2 fw-extrabold text-dark"><?= number_format($total_registros, 0, ',', '.') ?></div>
                    <div class="mt-2 text-muted small fw-bold"><i class="bi bi-database-fill me-1"></i> Base de datos local</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- ZONA DE CARGA DEL REPORTE -->
        <?php if (!$es_visualizador): ?>
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-dark text-white fw-bold py-3"><i class="bi bi-cloud-arrow-up-fill text-success me-2"></i> Subir Reporte DF-14</div>
                <div class="card-body d-flex flex-column justify-content-center">
                    <p class="small text-muted mb-4">Asegúrate de exportar el reporte en formato CSV (delimitado por comas o punto y coma) desde SofiaPlus.</p>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <input type="file" name="archivo_csv" class="form-control mb-3 bg-light border-dark" accept=".csv" required>
                        
                        <div class="form-check mb-4">
                            <input class="form-check-input border-secondary shadow-sm" type="checkbox" name="reemplazar_base" value="1" id="reemplazarBase" checked>
                            <label class="form-check-label small fw-bold text-dark" for="reemplazarBase">
                                Reemplazar toda la base existente
                            </label>
                            <div class="text-muted" style="font-size: 0.72rem;">Marca esta opción a inicio de mes para limpiar matrículas viejas. Si la desmarcas, solo actualizará y agregará registros.</div>
                        </div>

                        <button type="submit" name="subir_df14" class="btn btn-dark w-100 fw-bold shadow-sm rounded-pill"><i class="bi bi-database-add me-2 text-success"></i> Procesar Base DF-14</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ZONA DE BÚSQUEDA Y TABLA VISUAL -->
        <div class="<?= $es_visualizador ? 'col-lg-12' : 'col-lg-8' ?>">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div class="fw-bold text-dark">
                        <i class="bi bi-table text-success me-2"></i> Directorio DF-14
                    </div>
                    
                    <!-- Formulario de Búsqueda -->
                    <form method="GET" class="input-group" style="max-width: 350px;">
                        <input type="text" name="q" class="form-control bg-light border-end-0" placeholder="Buscar cédula, nombre o ficha..." value="<?= htmlspecialchars($busqueda) ?>">
                        <button type="submit" class="btn btn-light border-start-0 border text-success fw-bold"><i class="bi bi-search"></i></button>
                        <?php if(!empty($busqueda)): ?>
                            <a href="index.php" class="btn btn-light border text-danger" title="Limpiar"><i class="bi bi-x-lg"></i></a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <div class="card-body p-0">
                    <?php if (empty($busqueda)): ?>
                        <div class="alert alert-warning border-0 rounded-0 m-0 small fw-bold py-2 text-center text-dark">
                            <i class="bi bi-info-circle-fill me-1"></i> Mostrando los últimos <?= count($registros) ?> registros. Usa el buscador para encontrar un aprendiz específico.
                        </div>
                    <?php endif; ?>
                    
                    <div class="table-responsive" style="max-height: 550px; overflow-y: auto;">
                        <table class="table table-hover table-sm align-middle mb-0" style="font-size: 0.85em; width: 100%; min-width: 900px;">
                            <thead class="table-light sticky-top shadow-sm">
                                <tr>
                                    <th class="ps-4 py-3 border-bottom text-center">Tipo</th>
                                    <th class="py-3 border-bottom">Documento</th>
                                    <th class="py-3 border-bottom">Nombre Completo</th>
                                    <th class="text-center py-3 border-bottom">Ficha</th>
                                    <th class="text-center py-3 border-bottom">Estado de Ficha</th>
                                    <th class="text-center py-3 border-bottom pe-4">Estado del Aprendiz</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($registros) === 0): ?>
                                    <tr><td colspan="6" class="text-center py-5 text-muted fw-bold"><i class="bi bi-search fs-2 d-block mb-2 opacity-50"></i> No se encontraron resultados.</td></tr>
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
                                        <span class="badge bg-light text-dark border px-2 py-1 fs-6"><i class="bi bi-hash text-muted"></i> <?= htmlspecialchars($r['ficha']) ?></span>
                                    </td>
                                    <td class="text-center align-middle">
                                        <span class="badge bg-<?= $claseFicha ?> bg-opacity-10 text-<?= $claseFicha ?> border border-<?= $claseFicha ?> border-opacity-25 px-2 py-1">
                                            <?= htmlspecialchars($r['estado_ficha']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center align-middle pe-4">
                                        <span class="badge bg-<?= $claseAprendiz ?> shadow-sm px-3 py-2 rounded-pill w-100 text-truncate" style="max-width: 140px;" title="<?= htmlspecialchars($r['estado_aprendiz']) ?>">
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