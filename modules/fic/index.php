<?php
require_once '../../config/database.php';
require_once '../../includes/header.php';

$mensaje = "";

// 1. Carga Manual Individual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_fic_manual'])) {
    $tipo_doc = strtoupper(trim($_POST['tipo_documento']));
    $documento = trim(str_replace(['.', ' ', '-', "'", '"'], '', $_POST['documento']));
    $nombre = strtoupper(trim($_POST['nombre']));

    if (!empty($documento) && is_numeric($documento)) {
        try {
            $stmtInsert = $pdo->prepare("
                INSERT INTO base_fic (tipo_documento, documento, nombre) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                tipo_documento = VALUES(tipo_documento),
                nombre = VALUES(nombre)
            ");
            $stmtInsert->execute([$tipo_doc, $documento, !empty($nombre) ? $nombre : 'Sin Nombre']);
            $mensaje = "<div class='alert alert-success fw-bold'><i class='bi bi-check-circle-fill'></i> Aprendiz guardado correctamente en la Base FIC.</div>";
        } catch (PDOException $e) {
            $mensaje = "<div class='alert alert-danger fw-bold'>Error al guardar: " . $e->getMessage() . "</div>";
        }
    } else {
        $mensaje = "<div class='alert alert-danger fw-bold'>El número de documento no es válido.</div>";
    }
}

// 2. Carga Masiva CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_fic_csv'])) {
    if (isset($_FILES['archivo_csv']) && $_FILES['archivo_csv']['error'] == 0) {
        $archivo = $_FILES['archivo_csv']['tmp_name'];
        $lineas = file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $registrados = 0;

        $stmtInsert = $pdo->prepare("
            INSERT INTO base_fic (tipo_documento, documento, nombre) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            tipo_documento = VALUES(tipo_documento),
            nombre = VALUES(nombre)
        ");

        foreach ($lineas as $index => $linea) {
            if ($index === 0) continue;
            $separador = strpos($linea, ';') !== false ? ';' : ',';
            $datos = str_getcsv($linea, $separador);

            if (count($datos) >= 2) {
                $tipo_doc = trim(str_replace(['"', "'"], '', $datos[0]));
                $documento = trim(str_replace(['"', "'", '.', ' '], '', $datos[1]));
                $nombre = isset($datos[2]) ? trim(str_replace(['"', "'"], '', $datos[2])) : 'Sin Nombre';

                if (!empty($documento) && is_numeric($documento)) {
                    $stmtInsert->execute([strtoupper($tipo_doc), $documento, strtoupper($nombre)]);
                    $registrados++;
                }
            }
        }
        $mensaje = "<div class='alert alert-success fw-bold'><i class='bi bi-check-circle-fill'></i> Se procesaron y actualizaron $registrados registros en la Base FIC.</div>";
    } else {
        $mensaje = "<div class='alert alert-danger fw-bold'>Error al subir el archivo CSV.</div>";
    }
}

$stmt = $pdo->query("SELECT * FROM base_fic ORDER BY nombre ASC");
$aprendices_fic = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2><i class="bi bi-cone-striped text-warning"></i> Base de Datos FIC</h2>
            <p class="text-muted mb-0">Base maestra de aprendices con apoyo FIC (cancelación automática en seguimientos).</p>
        </div>
        <button class="btn btn-warning fw-bold text-dark" data-bs-toggle="modal" data-bs-target="#modalNuevoFic">
            <i class="bi bi-person-plus-fill"></i> Agregar FIC Manual
        </button>
    </div>

    <?= $mensaje ?>

    <!-- Acordeón para Carga Masiva CSV -->
    <div class="accordion mb-3" id="accordionFIC">
        <div class="accordion-item shadow-sm border-warning">
            <h2 class="accordion-header" id="headingFIC">
                <button class="accordion-button collapsed py-2 bg-warning bg-opacity-10 text-dark fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFIC" aria-expanded="false">
                    <i class="bi bi-file-earmark-spreadsheet-fill me-2 text-warning"></i> ¿Deseas cargar o actualizar la Base FIC mediante CSV? (Clic aquí)
                </button>
            </h2>
            <div id="collapseFIC" class="accordion-collapse collapse" data-bs-parent="#accordionFIC">
                <div class="accordion-body bg-light">
                    <p class="text-muted small mb-2">
                        El archivo CSV debe tener las siguientes columnas (la primera fila es la cabecera):<br>
                        <b>Tipo Documento ; Número Documento ; Nombre Completo (Opcional)</b>
                    </p>
                    <form method="POST" enctype="multipart/form-data" class="row g-2 align-items-center">
                        <div class="col-md-6">
                            <input type="file" name="archivo_csv" class="form-control form-control-sm" accept=".csv" required>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" name="subir_fic_csv" class="btn btn-sm btn-warning fw-bold w-100">
                                <i class="bi bi-upload"></i> Subir Base FIC
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Barra de Búsqueda en Tiempo Real -->
    <div class="card shadow-sm mb-3 bg-light border-0">
        <div class="card-body py-2">
            <div class="row g-2 align-items-center">
                <div class="col-md-10">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" id="filtroFic" class="form-control" placeholder="Escribe para buscar por documento o nombre en tiempo real..." autofocus>
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="button" id="btnLimpiarFic" class="btn btn-sm btn-outline-secondary w-100 fw-bold">
                        <i class="bi bi-eraser-fill"></i> Limpiar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla Resumen -->
    <div class="card shadow-sm border-0 shadow-lg">
        <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between align-items-center">
            <span>Aprendices visibles: <span id="conteoFic" class="badge bg-warning text-dark fs-6"><?= count($aprendices_fic) ?></span> de <?= count($aprendices_fic) ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 550px; overflow-y: auto;">
                <table class="table table-striped table-hover mb-0 align-middle" style="font-size: 0.85em;">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th style="width: 5%;" class="text-center">#</th>
                            <th style="width: 15%;" class="text-center">Tipo Doc</th>
                            <th style="width: 25%;">Documento</th>
                            <th style="width: 55%;">Nombre Completo</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyFic">
                        <?php if (count($aprendices_fic) === 0): ?>
                            <tr id="sinDatosFic"><td colspan="4" class="text-center py-4 text-muted">No hay registros en la base de datos FIC.</td></tr>
                        <?php endif; ?>
                        <?php $item = 1; foreach ($aprendices_fic as $af): ?>
                            <tr class="fila-fic" data-busqueda="<?= strtolower(htmlspecialchars($af['documento'] . ' ' . $af['nombre'])) ?>">
                                <td class="text-center fw-bold text-muted"><?= $item++ ?></td>
                                <td class="text-center"><span class="badge bg-light text-dark border"><?= htmlspecialchars($af['tipo_documento'] ?? '-') ?></span></td>
                                <td class="fw-bold"><?= htmlspecialchars($af['documento']) ?></td>
                                <td class="fw-bold text-uppercase"><?= htmlspecialchars($af['nombre'] ?? 'Sin Nombre') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr id="sinCoincidenciasFic" style="display: none;">
                            <td colspan="4" class="text-center py-4 text-muted fw-bold">
                                <i class="bi bi-search me-1"></i> No se encontraron registros que coincidan con la búsqueda.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: AGREGAR FIC MANUAL -->
<div class="modal fade" id="modalNuevoFic" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill me-2"></i> Agregar Registro FIC Manual</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Tipo de Documento</label>
                        <input type="text" name="tipo_documento" class="form-control form-control-sm text-uppercase" placeholder="CC / TI / PEP" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Número de Documento</label>
                        <input type="number" name="documento" class="form-control form-control-sm" placeholder="Sin puntos ni comas" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Nombre Completo</label>
                        <input type="text" name="nombre" class="form-control form-control-sm text-uppercase" placeholder="Nombres y Apellidos">
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm fw-bold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="crear_fic_manual" class="btn btn-warning btn-sm fw-bold"><i class="bi bi-save"></i> Guardar en FIC</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const inputFiltro = document.getElementById('filtroFic');
    const btnLimpiar = document.getElementById('btnLimpiarFic');
    const filas = document.querySelectorAll('.fila-fic');
    const conteo = document.getElementById('conteoFic');
    const sinCoincidencias = document.getElementById('sinCoincidenciasFic');

    function filtrarFic() {
        const texto = inputFiltro.value.toLowerCase().trim();
        let visibles = 0;

        filas.forEach(function (fila) {
            const data = fila.getAttribute('data-busqueda') || '';
            if (texto === '' || data.includes(texto)) {
                fila.style.display = '';
                visibles++;
            } else {
                fila.style.display = 'none';
            }
        });

        if (conteo) conteo.textContent = visibles;
        if (sinCoincidencias) {
            sinCoincidencias.style.display = (visibles === 0 && filas.length > 0) ? '' : 'none';
        }
    }

    inputFiltro.addEventListener('input', filtrarFic);

    btnLimpiar.addEventListener('click', function () {
        inputFiltro.value = '';
        filtrarFic();
        inputFiltro.focus();
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>