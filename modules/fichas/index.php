<?php
require_once '../../config/database.php';
require_once '../../includes/header.php';

$mensaje = "";

// 1. Carga Manual Individual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_ficha_manual'])) {
    $ficha = trim(str_replace([' ', '-', "'", '"'], '', $_POST['numero_ficha']));
    $programa = strtoupper(trim($_POST['programa']));
    $f_fin = !empty($_POST['fecha_fin_lectiva']) ? $_POST['fecha_fin_lectiva'] : null;

    if (!empty($ficha)) {
        try {
            $stmtInsert = $pdo->prepare("
                INSERT INTO distribucion_fichas (numero_ficha, programa, fecha_fin_lectiva) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                programa = VALUES(programa),
                fecha_fin_lectiva = VALUES(fecha_fin_lectiva)
            ");
            $stmtInsert->execute([$ficha, !empty($programa) ? $programa : 'PROGRAMA NO ESPECIFICADO', $f_fin]);
            $mensaje = "<div class='alert alert-success fw-bold'><i class='bi bi-check-circle-fill'></i> Ficha $ficha guardada correctamente.</div>";
        } catch (PDOException $e) {
            $mensaje = "<div class='alert alert-danger fw-bold'>Error al guardar: " . $e->getMessage() . "</div>";
        }
    } else {
        $mensaje = "<div class='alert alert-danger fw-bold'>El número de ficha no puede estar vacío.</div>";
    }
}

// 2. Carga Masiva CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_fichas_csv'])) {
    if (isset($_FILES['archivo_csv']) && $_FILES['archivo_csv']['error'] == 0) {
        $archivo = $_FILES['archivo_csv']['tmp_name'];
        $lineas = file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $registrados = 0;

        $stmtInsert = $pdo->prepare("
            INSERT INTO distribucion_fichas (numero_ficha, programa, fecha_fin_lectiva) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            programa = VALUES(programa),
            fecha_fin_lectiva = VALUES(fecha_fin_lectiva)
        ");

        foreach ($lineas as $index => $linea) {
            if ($index === 0) continue;
            $separador = strpos($linea, ';') !== false ? ';' : ',';
            $datos = str_getcsv($linea, $separador);

            if (count($datos) >= 2) {
                $ficha = trim(str_replace(['"', "'", ' '], '', $datos[0]));
                $programa = isset($datos[1]) ? trim(str_replace(['"', "'"], '', $datos[1])) : 'Programa no especificado';
                $f_fin = !empty($datos[2]) ? date('Y-m-d', strtotime(str_replace('/', '-', trim($datos[2])))) : null;

                if (!empty($ficha)) {
                    $stmtInsert->execute([$ficha, strtoupper($programa), $f_fin]);
                    $registrados++;
                }
            }
        }
        $mensaje = "<div class='alert alert-success fw-bold'><i class='bi bi-check-circle-fill'></i> Se procesaron y actualizaron $registrados fichas técnicas.</div>";
    } else {
        $mensaje = "<div class='alert alert-danger fw-bold'>Error al subir el archivo CSV.</div>";
    }
}

$stmt = $pdo->query("SELECT id, numero_ficha, programa, fecha_fin_lectiva FROM distribucion_fichas ORDER BY numero_ficha ASC");
$fichas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2><i class="bi bi-journal-text text-success"></i> Distribución de Fichas</h2>
            <p class="text-muted mb-0">Gestión de calendarios, fichas formativas y fechas de finalización lectiva.</p>
        </div>
        <button class="btn btn-success fw-bold" data-bs-toggle="modal" data-bs-target="#modalNuevaFicha">
            <i class="bi bi-plus-circle-fill"></i> Agregar Ficha Manual
        </button>
    </div>

    <?= $mensaje ?>

    <!-- Acordeón para Carga Masiva CSV -->
    <div class="accordion mb-3" id="accordionFichas">
        <div class="accordion-item shadow-sm border-success">
            <h2 class="accordion-header" id="headingFichas">
                <button class="accordion-button collapsed py-2 bg-success bg-opacity-10 text-dark fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFichas" aria-expanded="false">
                    <i class="bi bi-file-earmark-spreadsheet-fill me-2 text-success"></i> ¿Deseas cargar o actualizar la Distribución de Fichas mediante CSV? (Clic aquí)
                </button>
            </h2>
            <div id="collapseFichas" class="accordion-collapse collapse" data-bs-parent="#accordionFichas">
                <div class="accordion-body bg-light">
                    <p class="text-muted small mb-2">
                        El archivo CSV debe tener las siguientes 3 columnas (la primera fila es la cabecera):<br>
                        <b>Número Ficha ; Programa ; Fecha Fin Lectiva (AAAA-MM-DD)</b>
                    </p>
                    <form method="POST" enctype="multipart/form-data" class="row g-2 align-items-center">
                        <div class="col-md-6">
                            <input type="file" name="archivo_csv" class="form-control form-control-sm" accept=".csv" required>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" name="subir_fichas_csv" class="btn btn-sm btn-success fw-bold w-100">
                                <i class="bi bi-upload"></i> Subir Fichas
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
                        <input type="text" id="filtroFichas" class="form-control" placeholder="Escribe para buscar por ficha o programa en tiempo real..." autofocus>
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="button" id="btnLimpiarFichas" class="btn btn-sm btn-outline-secondary w-100 fw-bold">
                        <i class="bi bi-eraser-fill"></i> Limpiar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla Resumen -->
    <div class="card shadow-sm border-0 shadow-lg">
        <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between align-items-center">
            <span>Fichas visibles: <span id="conteoFichas" class="badge bg-success fs-6"><?= count($fichas) ?></span> de <?= count($fichas) ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 550px; overflow-y: auto;">
                <table class="table table-striped table-hover mb-0 align-middle" style="font-size: 0.85em;">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th style="width: 8%;" class="text-center">#</th>
                            <th style="width: 22%;">N° Ficha</th>
                            <th style="width: 48%;">Programa de Formación</th>
                            <th style="width: 22%;" class="text-center">Fin Lectiva</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyFichas">
                        <?php if (count($fichas) === 0): ?>
                            <tr id="sinDatosFichas"><td colspan="4" class="text-center py-4 text-muted">No hay fichas registradas.</td></tr>
                        <?php endif; ?>
                        <?php $item = 1; foreach ($fichas as $f): ?>
                            <tr class="fila-ficha" data-busqueda="<?= strtolower(htmlspecialchars($f['numero_ficha'] . ' ' . $f['programa'])) ?>">
                                <td class="text-center fw-bold text-muted"><?= $item++ ?></td>
                                <td class="fw-bold text-primary fs-6"><?= htmlspecialchars($f['numero_ficha']) ?></td>
                                <td class="fw-bold text-uppercase"><?= htmlspecialchars($f['programa']) ?></td>
                                <td class="text-center">
                                    <?php if (!empty($f['fecha_fin_lectiva'])): ?>
                                        <span class="badge bg-info text-dark fw-bold border border-dark"><?= htmlspecialchars($f['fecha_fin_lectiva']) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Sin fecha</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr id="sinCoincidenciasFichas" style="display: none;">
                            <td colspan="4" class="text-center py-4 text-muted fw-bold">
                                <i class="bi bi-search me-1"></i> No se encontraron fichas que coincidan con la búsqueda.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: AGREGAR FICHA MANUAL -->
<div class="modal fade" id="modalNuevaFicha" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-journal-plus me-2"></i> Agregar Ficha Manual</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Número de Ficha</label>
                        <input type="text" name="numero_ficha" class="form-control form-control-sm" placeholder="Ej: 2999553" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Programa de Formación</label>
                        <input type="text" name="programa" class="form-control form-control-sm text-uppercase" placeholder="Ej: ELECTRICIDAD INDUSTRIAL" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Fecha Fin Lectiva</label>
                        <input type="date" name="fecha_fin_lectiva" class="form-control form-control-sm">
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm fw-bold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="crear_ficha_manual" class="btn btn-success btn-sm fw-bold"><i class="bi bi-save"></i> Guardar Ficha</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const inputFiltro = document.getElementById('filtroFichas');
    const btnLimpiar = document.getElementById('btnLimpiarFichas');
    const filas = document.querySelectorAll('.fila-ficha');
    const conteo = document.getElementById('conteoFichas');
    const sinCoincidencias = document.getElementById('sinCoincidenciasFichas');

    function filtrarFichas() {
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

    inputFiltro.addEventListener('input', filtrarFichas);

    btnLimpiar.addEventListener('click', function () {
        inputFiltro.value = '';
        filtrarFichas();
        inputFiltro.focus();
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>