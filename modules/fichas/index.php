<?php
require_once '../../config/database.php';
require_once '../../includes/header.php';

$es_visualizador = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'visualizador');

$columna_programa = 'nombre_programa';
try {
    $stmtCols = $pdo->query("SHOW COLUMNS FROM distribucion_fichas");
    $columnas_bd = $stmtCols->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('programa', $columnas_bd)) {
        $columna_programa = 'programa';
    } elseif (in_array('nombre', $columnas_bd)) {
        $columna_programa = 'nombre';
    } elseif (in_array('programa_formacion', $columnas_bd)) {
        $columna_programa = 'programa_formacion';
    } elseif (in_array('nombre_programa', $columnas_bd)) {
        $columna_programa = 'nombre_programa';
    } else {
        foreach ($columnas_bd as $col) {
            if (!in_array($col, ['id', 'numero_ficha', 'fecha_fin_lectiva', 'created_at', 'updated_at'])) {
                $columna_programa = $col;
                break;
            }
        }
    }
} catch (Exception $e) {
    $columna_programa = 'nombre_programa';
}

if (!$es_visualizador && $_SERVER['REQUEST_METHOD'] === 'POST') {

    // A) CARGA MANUAL FICHA
    if (isset($_POST['guardar_ficha_manual'])) {
        $numero = trim($_POST['numero_ficha']);
        $programa = strtoupper(trim($_POST['nombre_programa']));
        $fecha_fin = trim($_POST['fecha_fin_lectiva']);

        if (!empty($numero) && !empty($fecha_fin)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO distribucion_fichas (numero_ficha, {$columna_programa}, fecha_fin_lectiva) VALUES (?, ?, ?)");
                $stmt->execute([$numero, $programa, $fecha_fin]);
                echo "<div class='alert alert-success mt-2 fw-bold'>Ficha registrada correctamente.</div>";
            } catch (PDOException $e) {
                echo "<div class='alert alert-danger mt-2 fw-bold'>Error: El número de ficha ya existe o hay un problema en la BD.</div>";
            }
        }
    }

    // B) CARGA MASIVA CSV FICHAS
    if (isset($_POST['subir_fichas_csv'])) {
        if ($_FILES['archivo_csv']['error'] == 0) {
            $archivo = $_FILES['archivo_csv']['tmp_name'];
            $lineas = file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $insertados = 0; $duplicados = 0;

            $stmtCheck = $pdo->prepare("SELECT id FROM distribucion_fichas WHERE numero_ficha = ?");
            $stmtInsert = $pdo->prepare("INSERT INTO distribucion_fichas (numero_ficha, {$columna_programa}, fecha_fin_lectiva) VALUES (?, ?, ?)");
            $stmtUpd = $pdo->prepare("UPDATE distribucion_fichas SET {$columna_programa} = ?, fecha_fin_lectiva = ? WHERE numero_ficha = ?");

            foreach ($lineas as $index => $linea) {
                if ($index === 0) continue; // Saltar cabecera

                $separador = strpos($linea, ';') !== false ? ';' : ',';
                $datos = str_getcsv($linea, $separador);

                if (count($datos) >= 3) {
                    $numero = trim(str_replace(['"', "'"], '', $datos[0]));
                    $programa = strtoupper(trim(str_replace(['"', "'"], '', $datos[1])));
                    $fecha_fin = trim(str_replace(['"', "'"], '', $datos[2]));

                    if (!empty($numero) && !empty($fecha_fin)) {
                        $stmtCheck->execute([$numero]);
                        if ($stmtCheck->rowCount() == 0) {
                            try {
                                $stmtInsert->execute([$numero, $programa, $fecha_fin]);
                                $insertados++;
                            } catch (PDOException $e) {
                                $duplicados++;
                            }
                        } else {
                            try {
                                $stmtUpd->execute([$programa, $fecha_fin, $numero]);
                                $insertados++;
                            } catch (PDOException $e) {
                                $duplicados++;
                            }
                        }
                    }
                }
            }
            if ($insertados > 0) echo "<div class='alert alert-success mt-2 fw-bold'>Carga de fichas procesada: $insertados fichas actualizadas/agregadas.</div>";
        }
    }

    // C) ELIMINAR FICHA
    if (isset($_POST['eliminar_ficha_id'])) {
        $id_del = $_POST['id_eliminar'];
        $stmtDel = $pdo->prepare("DELETE FROM distribucion_fichas WHERE id = ?");
        $stmtDel->execute([$id_del]);
        echo "<div class='alert alert-success mt-2 fw-bold'>Ficha eliminada correctamente.</div>";
    }
}

// Consultar todas las fichas
$stmtFichas = $pdo->query("SELECT * FROM distribucion_fichas ORDER BY numero_ficha ASC");
$lista_fichas = $stmtFichas->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0"><i class="bi bi-journal-text text-info"></i> Distribución de Fichas (Fin de Lectiva)</h2>
        <p class="text-muted mb-0">Control de fechas límites de etapa lectiva para el cálculo automático de cancelaciones.</p>
    </div>
</div>

<?php if (!$es_visualizador): ?>
<div class="row mb-4">

    <div class="col-xl-6 mb-3 mb-xl-0">
        <div class="card shadow-sm border-info h-100">
            <div class="card-header bg-info text-dark fw-bold py-2">Registrar Ficha Manual</div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <label class="small text-muted fw-bold">Número de Ficha</label>
                            <input type="text" name="numero_ficha" class="form-control form-control-sm" placeholder="Ej: 2671234" required>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="small text-muted fw-bold">Fecha Fin Lectiva</label>
                            <input type="date" name="fecha_fin_lectiva" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-12 mb-2">
                            <label class="small text-muted fw-bold">Nombre del Programa</label>
                            <input type="text" name="nombre_programa" class="form-control form-control-sm text-uppercase" placeholder="Ej: Análisis y Desarrollo de Software" required>
                        </div>
                    </div>
                    <button type="submit" name="guardar_ficha_manual" class="btn btn-info btn-sm w-100 fw-bold text-dark mt-2">Guardar Ficha</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Carga Masiva CSV -->
    <div class="col-xl-6">
        <div class="card shadow-sm border-warning h-100">
            <div class="card-header bg-warning text-dark fw-bold py-2">Carga Masiva (CSV) Fichas</div>
            <div class="card-body d-flex flex-column justify-content-center">
                <p class="small text-muted mb-2">Sube un archivo CSV con las columnas: <code>Numero_Ficha, Nombre_Programa, Fecha_Fin_Lectiva (YYYY-MM-DD)</code>.</p>
                <form method="POST" enctype="multipart/form-data" class="d-flex gap-2">
                    <input type="file" name="archivo_csv" class="form-control form-control-sm" accept=".csv" required>
                    <button type="submit" name="subir_fichas_csv" class="btn btn-warning btn-sm fw-bold text-nowrap">Subir CSV Fichas</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0 shadow-lg">
            <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between align-items-center">
                <span>Fichas Registradas en el Sistema</span>
                <span class="badge bg-info text-dark fs-6 rounded-pill"><?= count($lista_fichas) ?> Total</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width: 5%;">#</th>
                                <th style="width: 20%;">N° Ficha</th>
                                <th>Programa de Formación</th>
                                <th class="text-center" style="width: 20%;">Fecha Fin Lectiva</th>
                                <?php if (!$es_visualizador): ?><th class="text-center" style="width: 15%;">Acción</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($lista_fichas) === 0): ?>
                                <tr><td colspan="<?= $es_visualizador ? '4' : '5' ?>" class="text-center py-4 text-muted">No hay fichas registradas.</td></tr>
                            <?php endif; ?>

                            <?php $item = 1; foreach ($lista_fichas as $f): 
                                // Extraer el valor usando la columna detectada o cualquier alternativa existente
                                $programa_val = $f[$columna_programa] ?? ($f['nombre_programa'] ?? ($f['programa'] ?? ($f['nombre'] ?? 'SIN PROGRAMA')));
                            ?>
                            <tr>
                                <td class="text-center fw-bold text-muted"><?= $item++ ?></td>
                                <td class="fw-bold text-dark"><span class="badge bg-secondary fs-6"><?= htmlspecialchars($f['numero_ficha']) ?></span></td>
                                <td class="fw-bold text-uppercase text-secondary"><?= htmlspecialchars($programa_val) ?></td>
                                <td class="text-center fw-bold text-danger"><?= htmlspecialchars($f['fecha_fin_lectiva']) ?></td>
                                
                                <?php if (!$es_visualizador): ?>
                                <td class="text-center">
                                    <form method="POST" class="m-0" onsubmit="return confirm('¿Estás seguro de eliminar la ficha <?= $f['numero_ficha'] ?>?');">
                                        <input type="hidden" name="id_eliminar" value="<?= $f['id'] ?>">
                                        <button type="submit" name="eliminar_ficha_id" class="btn btn-sm btn-outline-danger border-0" title="Eliminar ficha">
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
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>