<?php
require_once '../../config/database.php';
require_once '../../includes/header.php';

$convocatoria_id = $_GET['convocatoria_id'] ?? null;

if (!$convocatoria_id) {
    echo "<div class='container mt-4'>";
    echo "<div class='alert alert-danger'>Error: Entorno no aislado. Debes seleccionar una convocatoria primero.</div>";
    echo "</div>";
    require_once '../../includes/footer.php';
    exit;
}

$stmtConv = $pdo->prepare("SELECT * FROM convocatorias WHERE id = ?");
$stmtConv->execute([$convocatoria_id]);
$convocatoria = $stmtConv->fetch(PDO::FETCH_ASSOC);

// 1. CARGA MASIVA EXACTA (Solo toma las columnas B, C, D y F)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_csv'])) {
    if ($_FILES['archivo_csv']['error'] == 0) {
        $archivo = $_FILES['archivo_csv']['tmp_name'];
        $lineas = file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $insertados = 0;
        $duplicados = 0;
        
        $stmtCheck = $pdo->prepare("SELECT id FROM beneficiarios WHERE convocatoria_id = ? AND documento = ?");
        $stmtInsert = $pdo->prepare("INSERT INTO beneficiarios (tipo_documento, documento, nombre, ficha, convocatoria_id) VALUES (?, ?, ?, ?, ?)");
        
        foreach($lineas as $index => $linea) {
            if ($index === 0) continue; // Ignorar encabezados
            
            $separador = strpos($linea, ';') !== false ? ';' : ',';
            $datos = str_getcsv($linea, $separador);
            
            if (count($datos) >= 6) {
                // EXTRACCIÓN EXACTA:
                $nombre = trim(str_replace(['"', "'"], '', $datos[1]));    // Columna B
                $tipo = strtoupper(trim(str_replace(['"', "'"], '', $datos[2]))); // Columna C
                $documento = trim(str_replace(['"', "'"], '', $datos[3])); // Columna D
                $ficha = trim(str_replace(['"', "'"], '', $datos[5]));     // Columna F (Ignoramos la E)
                
                if(!empty($documento) && is_numeric($documento)) {
                    $stmtCheck->execute([$convocatoria_id, $documento]);
                    if($stmtCheck->rowCount() > 0) {
                        $duplicados++; 
                    } else {
                        $stmtInsert->execute([$tipo, $documento, empty($nombre) ? 'Sin Nombre' : $nombre, $ficha, $convocatoria_id]);
                        $insertados++;
                    }
                }
            }
        }
        
        echo "<div class='alert alert-info mt-3'>";
        echo "<ul>";
        echo "<li>Nuevos aprendices guardados: <b>$insertados</b></li>";
        echo "<li>Aprendices duplicados ignorados: <b>$duplicados</b></li>";
        echo "</ul>";
        echo "</div>";
    }
}

// 2. CARGA MANUAL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_manual'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO beneficiarios (tipo_documento, documento, nombre, ficha, convocatoria_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['tipo_doc'], $_POST['documento'], $_POST['nombre'], $_POST['ficha'], $convocatoria_id]);
        echo "<div class='alert alert-success mt-3'>Aprendiz agregado correctamente.</div>";
    } catch(PDOException $e) {
        echo "<div class='alert alert-warning mt-3'>Error: Documento ya registrado en esta convocatoria.</div>";
    }
}

// Listar SOLO beneficiarios
$stmtList = $pdo->prepare("SELECT * FROM beneficiarios WHERE convocatoria_id = ? ORDER BY id DESC");
$stmtList->execute([$convocatoria_id]);
$beneficiarios = $stmtList->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4 mt-3">
    <h2>Convocatoria: <?= htmlspecialchars($convocatoria['nombre']) ?></h2>
</div>

<div class="row mb-4">
    <!-- Carga Manual -->
    <div class="col-md-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-primary text-white">Agregar Aprendiz (Manual)</div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-4 mb-3"><input type="text" name="tipo_doc" class="form-control" placeholder="Tipo" required></div>
                        <div class="col-8 mb-3"><input type="text" name="documento" class="form-control" placeholder="Número Documento" required></div>
                        <div class="col-12 mb-3"><input type="text" name="nombre" class="form-control" placeholder="Nombre Completo" required></div>
                        <div class="col-12 mb-3"><input type="text" name="ficha" class="form-control" placeholder="N° Ficha"></div>
                    </div>
                    <button type="submit" name="guardar_manual" class="btn btn-primary w-100">Agregar Aprendiz</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Carga por Archivo -->
    <div class="col-md-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-warning text-dark">Carga Masiva (CSV)</div>
            <div class="card-body">
                <p class="small text-muted">Sube tu Excel guardado como <b>CSV (Delimitado por comas)</b>. El sistema extraerá solo el Nombre, Tipo, Documento y Ficha.</p>
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <input type="file" name="archivo_csv" class="form-control" accept=".csv" required>
                    </div>
                    <button type="submit" name="subir_csv" class="btn btn-warning w-100 fw-bold">CARGAR ARCHIVO CSV</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Tabla Aislada Limpia -->
<div class="card shadow-sm mt-4">
    <div class="card-header bg-dark text-white fw-bold">Listado de Aprendices (<?= count($beneficiarios) ?>)</div>
    <div class="card-body p-0">
        <table class="table table-striped table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Tipo Doc</th>
                    <th>Número Documento</th>
                    <th>Nombre del Aprendiz</th>
                    <th>N° Ficha</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($beneficiarios) === 0): ?>
                    <tr><td colspan="5" class="text-center py-4 text-muted">No hay aprendices en esta convocatoria.</td></tr>
                <?php endif; ?>
                
                <?php 
                $item = 1; 
                foreach ($beneficiarios as $b): 
                ?>
                <tr>
                    <td class="fw-bold text-muted"><?= $item++ ?></td>
                    <td><?= htmlspecialchars($b['tipo_documento']) ?></td>
                    <td class="fw-bold"><?= htmlspecialchars($b['documento']) ?></td>
                    <td><?= htmlspecialchars($b['nombre']) ?></td>
                    <td><?= htmlspecialchars($b['ficha']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>