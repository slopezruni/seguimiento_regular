<?php
require_once '../../config/database.php';
require_once '../../includes/header.php';

$mensaje = "";

// Mayúsculas seguras sin depender de la extensión mbstring de XAMPP
function aMayusculas($texto) {
    $texto = (string)$texto;
    if (function_exists('mb_strtoupper')) {
        return mb_strtoupper($texto, 'UTF-8');
    }
    $mapa = [
        'á' => 'Á', 'é' => 'É', 'í' => 'Í', 'ó' => 'Ó', 'ú' => 'Ú',
        'ñ' => 'Ñ', 'ü' => 'Ü'
    ];
    return strtoupper(strtr($texto, $mapa));
}

// Función segura para convertir fechas D/M/YYYY, DD/MM/YYYY o YYYY-MM-DD a YYYY-MM-DD
function normalizarFechaLectiva($valor, $valorVacio = null) {
    $limpio = trim(str_replace(['"', "'", "\xC2\xA0"], '', (string)$valor));
    if ($limpio === '') {
        return $valorVacio;
    }

    // Formato D/M/YYYY o DD/MM/YYYY (ej. 7/04/2026 o 29/12/2026)
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $limpio, $m)) {
        $dia = (int)$m[1];
        $mes = (int)$m[2];
        $anio = (int)$m[3];
        if (checkdate($mes, $dia, $anio) && $anio >= 2000) {
            return sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
        }
        return $valorVacio;
    }

    // Formato ISO YYYY-MM-DD o YYYY/MM/DD (ej. 2026-04-07)
    if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $limpio, $m)) {
        $anio = (int)$m[1];
        $mes = (int)$m[2];
        $dia = (int)$m[3];
        if (checkdate($mes, $dia, $anio) && $anio >= 2000) {
            return sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
        }
        return $valorVacio;
    }

    return $valorVacio;
}

// Detectar si fecha_fin_lectiva permite NULL en la tabla distribucion_fichas
$valorFechaVacia = null;
try {
    $colFecha = $pdo->query("SHOW COLUMNS FROM distribucion_fichas LIKE 'fecha_fin_lectiva'")->fetch(PDO::FETCH_ASSOC);
    if ($colFecha && strtoupper($colFecha['Null'] ?? 'YES') === 'NO') {
        $tipoCol = strtolower($colFecha['Type'] ?? '');
        $valorFechaVacia = (strpos($tipoCol, 'char') !== false || strpos($tipoCol, 'text') !== false) ? '' : '0000-00-00';
    }
} catch (Throwable $e) {
    $valorFechaVacia = null;
}

// 0. Vaciar Base de Fichas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vaciar_fichas'])) {
    try {
        $pdo->exec("DELETE FROM distribucion_fichas");
        $pdo->exec("ALTER TABLE distribucion_fichas AUTO_INCREMENT = 1");
        $mensaje = "<div class='alert alert-warning fw-bold'><i class='bi bi-trash3-fill me-1'></i> La tabla de distribución de fichas ha sido vaciada correctamente.</div>";
    } catch (Throwable $e) {
        $mensaje = "<div class='alert alert-danger fw-bold'>Error al vaciar la tabla: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// 1. Carga Manual Individual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_ficha_manual'])) {
    $ficha = trim(str_replace([' ', '-', "'", '"'], '', $_POST['numero_ficha']));
    $programa = aMayusculas(trim($_POST['programa']));
    $f_fin = normalizarFechaLectiva($_POST['fecha_fin_lectiva'] ?? '', $valorFechaVacia);

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
        } catch (Throwable $e) {
            $mensaje = "<div class='alert alert-danger fw-bold'>Error al guardar: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        $mensaje = "<div class='alert alert-danger fw-bold'>El número de ficha no puede estar vacío.</div>";
    }
}

// 2. Carga Masiva CSV (Compatible con reporte completo de 10 columnas y formato simple de 3 columnas)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_fichas_csv'])) {
    if (isset($_FILES['archivo_csv']) && $_FILES['archivo_csv']['error'] === UPLOAD_ERR_OK) {
        $archivo = $_FILES['archivo_csv']['tmp_name'];
        $lineas = file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $registrados = 0;

        // Índices por defecto para formato de 3 columnas: Ficha ; Programa ; Fecha
        $idxFicha = 0;
        $idxPrograma = 1;
        $idxFecha = 2;

        if (!empty($lineas)) {
            $primeraLinea = preg_replace('/^\xEF\xBB\xBF/', '', $lineas[0]);
            $sepHeader = strpos($primeraLinea, ';') !== false ? ';' : ',';
            $encabezados = str_getcsv($primeraLinea, $sepHeader);

            // Si es el reporte institucional completo (>= 8 columnas), mapear por nombre de encabezado
            if (count($encabezados) >= 8) {
                $idxFicha = 1;
                $idxPrograma = 7;
                $idxFecha = 4;

                foreach ($encabezados as $i => $colName) {
                    $colClean = strtoupper(trim(str_replace(['"', "'"], '', $colName)));
                    if (strpos($colClean, 'FICHA') !== false && strpos($colClean, 'ETAPA') === false) {
                        $idxFicha = $i;
                    } elseif (strpos($colClean, 'PROGRAMA') !== false) {
                        $idxPrograma = $i;
                    } elseif (strpos($colClean, 'LECTIVA') !== false || strpos($colClean, 'FECHA') !== false) {
                        $idxFecha = $i;
                    }
                }
            }
        }

        try {
            $pdo->beginTransaction();

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

                if (isset($datos[$idxFicha])) {
                    $ficha = preg_replace('/[^0-9]/', '', $datos[$idxFicha]);
                    $programaRaw = isset($datos[$idxPrograma]) ? trim(str_replace(['"', "'", "\xC2\xA0"], ' ', $datos[$idxPrograma])) : '';
                    $programa = preg_replace('/\s+/', ' ', $programaRaw);
                    if ($programa === '') {
                        $programa = 'PROGRAMA NO ESPECIFICADO';
                    }
                    $f_fin = isset($datos[$idxFecha]) ? normalizarFechaLectiva($datos[$idxFecha], $valorFechaVacia) : $valorFechaVacia;

                    if (!empty($ficha)) {
                        $stmtInsert->execute([$ficha, aMayusculas($programa), $f_fin]);
                        $registrados++;
                    }
                }
            }

            $pdo->commit();
            if ($registrados > 0) {
                $mensaje = "<div class='alert alert-success fw-bold'><i class='bi bi-check-circle-fill'></i> Se procesaron y actualizaron $registrados fichas técnicas correctamente.</div>";
            } else {
                $mensaje = "<div class='alert alert-warning fw-bold'><i class='bi bi-exclamation-triangle-fill'></i> El archivo se leyó pero no se encontraron números de ficha válidos para insertar.</div>";
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $mensaje = "<div class='alert alert-danger fw-bold'>Error al procesar el archivo: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        $codError = $_FILES['archivo_csv']['error'] ?? 'desconocido';
        $mensaje = "<div class='alert alert-danger fw-bold'>Error al subir el archivo CSV (Código: $codError).</div>";
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
                        Puedes subir directamente el archivo CSV completo de <b>Distribución de Fichas</b> (10 columnas) o el formato resumido de 3 columnas (<b>Número Ficha ; Programa ; Fecha Fin Lectiva</b>).
                    </p>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <form method="POST" enctype="multipart/form-data" class="row g-2 align-items-center flex-grow-1">
                            <div class="col-md-6">
                                <input type="file" name="archivo_csv" class="form-control form-control-sm" accept=".csv" required>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" name="subir_fichas_csv" class="btn btn-sm btn-success fw-bold w-100">
                                    <i class="bi bi-upload"></i> Subir Fichas
                                </button>
                            </div>
                        </form>

                        <?php if (count($fichas) > 0): ?>
                        <form method="POST" onsubmit="return confirm('¿Seguro que deseas vaciar toda la tabla de Distribución de Fichas?');">
                            <button type="submit" name="vaciar_fichas" class="btn btn-sm btn-outline-danger fw-bold">
                                <i class="bi bi-trash3-fill"></i> Vaciar Base
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
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
                                    <?php if (!empty($f['fecha_fin_lectiva']) && $f['fecha_fin_lectiva'] !== '0000-00-00'): ?>
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