<?php
session_start();
if (!isset($_SESSION['id'])) { header("Location: login.php"); exit(); }

include 'layout/header.php';
include 'layout/nav.php';
include 'layout/sidebar.php';
require_once 'config/db.php';

$cita_id = $_GET['cita_id'] ?? '';

if (empty($cita_id)) {
    echo "<script>window.location='panel_medico.php';</script>";
    exit();
}

// 1. CONSULTA DE DATOS DE LA CITA E IDENTIFICACIÓN DE LA ESPECIALIDAD DEL MÉDICO
$stmt = $pdo->prepare("
    SELECT 
        cm.id AS cita_id, 
        cm.fecha_cita, 
        cm.hora_inicio, 
        p.id AS paciente_id, 
        p.nombre AS paciente, 
        con.nombre_consultorio,
        pd.id AS paciente_datos_id,
        pd.documento_identidad, 
        pd.fecha_nacimiento, 
        pd.genero, 
        pd.tipo_sangre, 
        pd.telefono_contacto, 
        pd.direccion, 
        pd.eps_id, 
        pd.contacto_emergencia_nombre, 
        pd.contacto_emergencia_telefono,
        esp.nombre
    FROM citas_medicas cm
    INNER JOIN usuarios_clinicas p ON cm.paciente_id = p.id
    INNER JOIN consultorios con ON cm.consultorio_id = con.id
    INNER JOIN pacientes_datos pd ON p.id = pd.usuario_id
    INNER JOIN datos_medicos dm ON cm.medico_id = dm.usuario_id
    INNER JOIN especialidades esp ON dm.especialidad = esp.id
    WHERE cm.id = ? AND cm.medico_id = ?
");
$stmt->execute([$cita_id, $_SESSION['id']]);
$datos = $stmt->fetch();

if (!$datos) {
    echo "<div class='content-wrapper'><p class='p-4 text-danger'>Cita no válida o no asignada a su usuario.</p></div>";
    include 'layout/footer.php';
    exit();
}

// Calcular edad del paciente
$cumpleanos = new DateTime($datos['fecha_nacimiento']);
$hoy = new DateTime();
$edad = $hoy->diff($cumpleanos)->y;

// Bandera de control: Verifica si la especialidad contiene la palabra "odontologia" u "odontologo"
$es_odontologo = (isset($datos['nombre']) && strripos($datos['nombre'], 'odontolog') !== false);

// NUEVO: Bandera de control para Oftalmología u Optometría
$es_optometra = (isset($datos['nombre']) && (strripos($datos['nombre'], 'oftalmolog') !== false || strripos($datos['nombre'], 'optometr') !== false));
?>

<link rel="stylesheet" href="plugins/select2/css/select2.min.css">
<link rel="stylesheet" href="plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
<style>
    /* Estilos personalizados para el Odontograma Geométrico (Mantenido de tu código) */
    .geometric-tooth {
        display: inline-block;
        background: #fff;
    }
    .cara-diente {
        transition: all 0.2s ease;
        font-weight: bold;
        user-select: none;
    }
    .cara-diente:hover {
        filter: brightness(0.9);
        box-shadow: inset 0 0 3px rgba(0,0,0,0.2);
    }

    /* === NUEVAS MEJORAS VISUALES PARA LAS PESTAÑAS (TABS) === */
    
    /* Forzar que las pestañas ocultas NO se muestren en bloque */
    .tab-content > .tab-pane {
        display: none;
    }
    .tab-content > .active {
        display: block !important;
    }

    /* Diseño moderno para la barra de pestañas */
    #hcTabs {
        border-bottom: none;
        padding: 0 10px;
        background-color: #f4f6f9; /* Fondo gris suave para resaltar las pestañas */
        border-top-left-radius: .25rem;
        border-top-right-radius: .25rem;
    }

    #hcTabs .nav-item {
        margin-bottom: -1px;
        margin-top: 5px;
    }

    #hcTabs .nav-link {
        border: none;
        color: #495057;
        font-weight: 600;
        padding: 12px 20px;
        border-radius: 8px 8px 0 0;
        transition: all 0.3s ease;
        margin-right: 4px;
    }

    /* Efecto al pasar el cursor por encima */
    #hcTabs .nav-link:hover {
        background-color: rgba(40, 167, 69, 0.1);
        color: #28a745;
    }

    /* Estilo de la pestaña Activa (Seleccionada) */
    #hcTabs .nav-link.active {
        background-color: #ffffff !important;
        color: #28a745 !important;
        border-top: 3px solid #28a745 !important;
        box-shadow: 0 -4px 10px rgba(0,0,0,0.05);
    }

    /* Separación y empaque del cuerpo de las pestañas */
    .card-body {
        padding: 1.5rem;
        background: #ffffff;
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-file-medical text-success"></i> Consulta Médica Activa</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="panel_medico.php" class="btn btn-secondary shadow-sm">
                        <i class="fas fa-arrow-left"></i> Salir sin guardar
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            
            <div class="card card-dark shadow-sm">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-id-card"></i> Datos del Paciente</h3>
                </div>
                <div class="card-body bg-light">
                    <div class="row">
                        <div class="col-md-4">
                            <strong><i class="fas fa-user mr-1"></i> Nombre:</strong> 
                            <span class="text-uppercase"><?= htmlspecialchars($datos['paciente']) ?></span>
                        </div>
                        <div class="col-md-2">
                            <strong><i class="fas fa-id-card mr-1"></i> Cédula:</strong> <?= htmlspecialchars($datos['documento_identidad']) ?>
                        </div>
                        <div class="col-md-2">
                            <strong><i class="fas fa-birthday-cake mr-1"></i> Edad:</strong> <?= $edad ?> años
                        </div>
                        <div class="col-md-2">
                            <strong><i class="fas fa-venus-mars mr-1"></i> Género:</strong> <span class="text-uppercase"><?= htmlspecialchars($datos['genero'] ?? 'No definido') ?></span>
                        </div>
                        <div class="col-md-2 text-right">
                            <span class="badge badge-indigo p-2"><i class="fas fa-clinic-medical"></i> <?= htmlspecialchars($datos['nombre_consultorio']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <form id="formHistoriaClinica">
                <input type="hidden" name="cita_id" value="<?= $datos['cita_id'] ?>">
                <input type="hidden" name="paciente_id" value="<?= $datos['paciente_id'] ?>">
                <input type="hidden" name="odontograma_json" id="odontograma_json" value="[]">

                <div class="card card-success card-tabs shadow">
                    <div class="card-header p-0 pt-1">
                        <ul class="nav nav-tabs" id="hcTabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="motivo-tab" data-toggle="pill" href="#tab-motivo" role="tab"><i class="fas fa-comment-medical"></i> Motivo y Anamnesis</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="signos-tab" data-toggle="pill" href="#tab-signos" role="tab"><i class="fas fa-heartbeat"></i> Signos Vitales</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="diagnostico-tab" data-toggle="pill" href="#tab-diagnostico" role="tab"><i class="fas fa-stethoscope"></i> Diagnóstico y Notas</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="tratamiento-tab" data-toggle="pill" href="#tab-prescription" role="tab"><i class="fas fa-pills"></i> Plan / Receta Médica</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="anexos-tab" data-toggle="pill" href="#tab-anexos" role="tab"><i class="fas fa-paperclip"></i> Anexos y Ayudas</a>
                            </li>
                            <?php if ($es_odontologo): ?>
                            <li class="nav-item">
                                <a class="nav-link" id="odontograma-tab" data-toggle="pill" href="#tab-odontograma" role="tab"><i class="fas fa-tooth text-warning"></i> Odontograma</a>
                            </li>
                            <?php endif; ?>
                            
                            <?php if ($es_optometra): ?>
                            <li class="nav-item">
                                <a class="nav-link" id="vision-tab" data-toggle="pill" href="#tab-vision" role="tab"><i class="fas fa-eye text-info"></i> Examen de la Vista</a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    
                    <div class="card-body">
                        <div class="tab-content" id="hcTabsContent">
                            
                            <div class="tab-pane fade show active" id="tab-motivo" role="tabpanel">
                                <div class="form-group">
                                    <label>Motivo de la Consulta <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="motivo_consulta" rows="3" placeholder="Ej. Paciente presenta dolor abdominal agudo desde hace 24 horas..." required></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Enfermedad Actual / Antecedentes Relevantes</label>
                                    <textarea class="form-control" name="enfermedad_actual" rows="4" placeholder="Detalle del desarrollo de los síntomas o antecedentes médicos..."></textarea>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="tab-signos" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-3 form-group">
                                        <label>Presión Arterial (PA)</label>
                                        <input type="text" class="form-control" name="presion_arterial" placeholder="120/80 mmHg">
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label>Frecuencia Cardíaca (FC)</label>
                                        <input type="text" class="form-control" name="frecuencia_cardiaca" placeholder="75 lpm">
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label>Temperatura (°C)</label>
                                        <input type="text" class="form-control" name="temperatura" placeholder="36.5 °C">
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label>Saturación de Oxígeno (O2)</label>
                                        <input type="text" class="form-control" name="saturacion_oxigeno" placeholder="98%">
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-4 form-group">
                                        <label>Peso (Kg)</label>
                                        <input type="number" step="0.01" class="form-control vital-calc" id="peso" name="peso" placeholder="70">
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label>Estatura (Metros)</label>
                                        <input type="number" step="0.01" class="form-control vital-calc" id="estatura" name="estatura" placeholder="1.70">
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label>Índice de Masa Corporal (IMC)</label>
                                        <input type="text" class="form-control" id="imc" name="imc" readonly style="background-color: #e9ecef;">
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="tab-diagnostico" role="tabpanel">
                                <div class="form-group">
                                    <label>Examen Físico / Hallazgos</label>
                                    <textarea class="form-control" name="examen_physical" rows="3" placeholder="Resultados de la exploración física..."></textarea>
                                </div>

                                <div class="form-group position-relative">
                                    <label>Diagnóstico Principal (Buscador CIE-10) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                                        </div>
                                        <input type="text" id="buscar_cie10" class="form-control" placeholder="Escriba el código o nombre del diagnóstico... (Ej: Gastroenteritis)" autocomplete="off" required>
                                    </div>
                                    <input type="hidden" id="diagnostico_id" name="diagnostico_id" value="" required>
                                    <div id="resultados_cie10" class="list-group position-absolute w-100 shadow" style="z-index: 1050; display: none; max-height: 250px; overflow-y: auto;"></div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="tab-prescription" role="tabpanel">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="card card-outline card-success shadow-sm">
                                            <div class="card-header">
                                                <h3 class="card-title"><i class="fas fa-pills text-success"></i> Componentes de la Fórmula Médica</h3>
                                            </div>
                                            <div class="card-body">
                                                <div class="row align-items-end mb-4 bg-light p-3 rounded border" style="margin-left:0; margin-right:0;">
                                                    <div class="col-md-6 form-group mb-0">
                                                        <label>Buscar Medicamento (Catálogo Clínico Activo)</label>
                                                        <select id="buscadorMedicamento_hc" class="form-control" style="width: 100%;"></select>
                                                    </div>
                                                    <div class="col-md-2 form-group mb-0">
                                                        <label>Cantidad</label>
                                                        <input type="number" id="tempCantidad_hc" class="form-control" min="1" value="1">
                                                    </div>
                                                    <div class="col-md-4 form-group mb-0">
                                                        <button type="button" id="btnAgregarMedicamento_hc" class="btn btn-success btn-block shadow-sm">
                                                            <i class="fas fa-plus-circle"></i> Agregar a la Fórmula
                                                        </button>
                                                    </div>
                                                </div>

                                                <div class="table-responsive">
                                                    <table id="tablaFormula_hc" class="table table-bordered table-striped minimal">
                                                        <thead class="bg-gray-light">
                                                            <tr>
                                                                <th>Medicamento</th>
                                                                <th style="width: 110px;">Cant.</th>
                                                                <th>Dosificación e Instrucciones Médicas *</th>
                                                                <th style="width: 60px;" class="text-center">Acción</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="cuerpoFormula_hc">
                                                            <tr id="filaVacia_hc">
                                                                <td colspan="4" class="text-center text-muted py-3">No se han recetado medicamentos en esta consulta aún.</td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>

                                                <div class="form-group mt-3">
                                                    <label><i class="fas fa-bars-staggered"></i> Observaciones Generales de la Fórmula / Cuidados</label>
                                                    <textarea name="observaciones_formula" class="form-control" rows="3" placeholder="Ej: Reposo por 3 días, tomar abundante líquido y alarmas..."></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="tab-anexos" role="tabpanel">
                                <div class="bg-light p-3 rounded mb-4 border">
                                    <div class="row">
                                        <div class="col-md-4 form-group">
                                            <label for="tipo_archivo">Tipo de Documento</label>
                                            <select id="tipo_archivo" class="form-control text-uppercase">
                                                <option value="Laboratorio">Laboratorio Clínico</option>
                                                <option value="Imagenologia">Imagenología (Rx, Tac, Eco)</option>
                                                <option value="Odontologia">Registro Odontológico</option>
                                                <option value="Otros" selected>Otros Documentos / Historia Vieja</option>
                                            </select>
                                        </div>
                                        <div class="col-md-5 form-group">
                                            <label for="nombre_personalizado">Nombre o Descripción del Examen</label>
                                            <input type="text" id="nombre_personalizado" class="form-control" placeholder="Ej: Hemograma completo, Radiografía de Tórax">
                                        </div>
                                        <div class="col-md-3 form-group">
                                            <label for="archivo_adjunto">Seleccionar Archivo</label>
                                            <div class="custom-file">
                                                <input type="file" id="archivo_adjunto" class="custom-file-input" accept=".pdf,.jpg,.jpeg,.png">
                                                <label class="custom-file-label" for="archivo_adjunto" data-browse="Buscar">Elegir...</label>
                                            </div>
                                            <small class="text-muted">Formatos: PDF, JPG, PNG. Máx: 5MB</small>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <button type="button" id="btnSubirArchivo" class="btn btn-primary shadow-sm bg-indigo border-0">
                                            <i class="fas fa-cloud-upload-alt"></i> Adjuntar Documento
                                        </button>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover m-0">
                                        <thead class="bg-dark text-white">
                                            <tr>
                                                <th>Fecha Registro</th>
                                                <th>Tipo</th>
                                                <th>Descripción del Documento</th>
                                                <th>Registrado Por</th>
                                                <th style="width: 120px;" class="text-center">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody id="cuerpoAdjuntos"></tbody>
                                    </table>
                                </div>
                            </div>

                            <?php if ($es_odontologo): ?>
                            <div class="tab-pane fade" id="tab-odontograma" role="tabpanel">
                                <div class="alert alert-info shadow-sm mb-4">
                                    <h5><i class="icon fas fa-tooth"></i> Panel de Odontograma Clínico</h5>
                                    Seleccione el estado patológico en la paleta inferior y haga clic sobre la cara correspondiente del diente.
                                </div>

                                <div class="card card-outline card-warning p-3 mb-4 bg-light shadow-sm">
                                    <div class="text-center font-weight-bold mb-3 text-secondary">1. SELECCIONE EL ESTADO DIAGNÓSTICO:</div>
                                    <div class="d-flex justify-content-center flex-wrap" style="gap: 15px;">
                                        <label class="btn btn-outline-danger px-3 py-2 active shadow-sm" style="cursor:pointer;">
                                            <input type="radio" name="dental_status" value="Caries" checked autocomplete="off"> 
                                            <i class="fas fa-circle text-danger mr-1"></i> Caries
                                        </label>
                                        <label class="btn btn-outline-primary px-3 py-2 shadow-sm" style="cursor:pointer;">
                                            <input type="radio" name="dental_status" value="Restaurado" autocomplete="off"> 
                                            <i class="fas fa-circle text-primary mr-1"></i> Restaurado
                                        </label>
                                        <label class="btn btn-outline-warning px-3 py-2 shadow-sm" style="cursor:pointer;">
                                            <input type="radio" name="dental_status" value="Corona" autocomplete="off"> 
                                            <i class="fas fa-circle text-warning mr-1"></i> Corona
                                        </label>
                                        <label class="btn btn-outline-secondary px-3 py-2 shadow-sm" style="cursor:pointer;">
                                            <input type="radio" name="dental_status" value="Ausente" autocomplete="off"> 
                                            <i class="fas fa-times text-secondary mr-1"></i> Ausente / Extraído
                                        </label>
                                        <label class="btn btn-outline-success px-3 py-2 shadow-sm" style="cursor:pointer;">
                                            <input type="radio" name="dental_status" value="Sano" autocomplete="off"> 
                                            <i class="fas fa-check text-success mr-1"></i> Limpiar / Sano
                                        </label>
                                    </div>
                                </div>

                                <div class="odontograma-visual p-4 border rounded bg-white text-center shadow-sm" style="overflow-x: auto; white-space: nowrap;">
                                    
                                    <h6 class="text-left text-muted font-weight-bold border-bottom pb-1"><i class="fas fa-arrow-up"></i> Arcada Superior (Cuadrantes 1 y 2)</h6>
                                    <div class="mb-5">
                                        <?php 
                                        $arcada_superior = [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28];
                                        foreach ($arcada_superior as $num_diente): 
                                        ?>
                                        <div class="diente-contenedor border rounded bg-light p-2 m-1 text-center" style="width: 75px; display: inline-block; vertical-align: top;">
                                            <span class="badge badge-dark d-block mb-2" style="font-size:11px;">#<?= $num_diente ?></span>
                                            <div class="d-flex flex-column align-items-center position-relative geometric-tooth" data-diente="<?= $num_diente ?>">
                                                <div class="cara-diente cara-v border text-center" data-cara="vestibular" style="width:28px; height:16px; cursor:pointer; font-size:10px; background:#f4f6f9;" title="Vestibular">V</div>
                                                <div class="d-flex justify-content-between my-1" style="width: 60px;">
                                                    <div class="cara-diente cara-m border text-center" data-cara="mesial" style="width:16px; height:22px; cursor:pointer; font-size:10px; background:#f4f6f9;" title="Mesial">M</div>
                                                    <div class="cara-diente cara-o border text-center" data-cara="oclusal" style="width:22px; height:22px; cursor:pointer; font-size:10px; background:#f4f6f9;" title="Oclusal">O</div>
                                                    <div class="cara-diente cara-d border text-center" data-cara="distal" style="width:16px; height:22px; cursor:pointer; font-size:10px; background:#f4f6f9;" title="Distal">D</div>
                                                </div>
                                                <div class="cara-diente cara-l border text-center" data-cara="palatina" style="width:28px; height:16px; cursor:pointer; font-size:10px; background:#f4f6f9;" title="Palatina/Lingual">P</div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <h6 class="text-left text-muted font-weight-bold border-bottom pb-1"><i class="fas fa-arrow-down"></i> Arcada Inferior (Cuadrantes 4 y 3)</h6>
                                    <div>
                                        <?php 
                                        $arcada_inferior = [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38];
                                        foreach ($arcada_inferior as $num_diente): 
                                        ?>
                                        <div class="diente-contenedor border rounded bg-light p-2 m-1 text-center" style="width: 75px; display: inline-block; vertical-align: top;">
                                            <span class="badge badge-dark d-block mb-2" style="font-size:11px;">#<?= $num_diente ?></span>
                                            <div class="d-flex flex-column align-items-center position-relative geometric-tooth" data-diente="<?= $num_diente ?>">
                                                <div class="cara-diente cara-l border text-center" data-cara="lingual" style="width:28px; height:16px; cursor:pointer; font-size:10px; background:#f4f6f9;" title="Lingual">L</div>
                                                <div class="d-flex justify-content-between my-1" style="width: 60px;">
                                                    <div class="cara-diente cara-m border text-center" data-cara="mesial" style="width:16px; height:22px; cursor:pointer; font-size:10px; background:#f4f6f9;" title="Mesial">M</div>
                                                    <div class="cara-diente cara-o border text-center" data-cara="oclusal" style="width:22px; height:22px; cursor:pointer; font-size:10px; background:#f4f6f9;" title="Oclusal">O</div>
                                                    <div class="cara-diente cara-d border text-center" data-cara="distal" style="width:16px; height:22px; cursor:pointer; font-size:10px; background:#f4f6f9;" title="Distal">D</div>
                                                </div>
                                                <div class="cara-diente cara-v border text-center" data-cara="vestibular" style="width:28px; height:16px; cursor:pointer; font-size:10px; background:#f4f6f9;" title="Vestibular">V</div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>

                                </div>

                                <div class="form-group mt-4">
                                    <label><i class="fas fa-notes-medical"></i> Hallazgos Clínicos y Plan Odontológico General</label>
                                    <textarea name="observaciones_odontograma" class="form-control" rows="3" placeholder="Ej: Se observa caries penetrante en pieza 16 que requiere endodoncia. Profilaxis requerida..."></textarea>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if ($es_optometra): ?>
                            <div class="tab-pane fade" id="tab-vision" role="tabpanel">
                                <div class="alert alert-info shadow-sm mb-4">
                                    <h5><i class="icon fas fa-eye"></i> Historial y Examen de Visión</h5>
                                    Registre los parámetros de Agudeza Visual, Refracción y Examen Físico Ocular obtenidos en la consulta.
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card card-outline card-info shadow-sm">
                                            <div class="card-header"><h3 class="card-title font-weight-bold">Agudeza Visual (AV)</h3></div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-6 form-group">
                                                        <label>AV Lejos (Ojo Derecho)</label>
                                                        <input type="text" class="form-control" name="av_lejos_od" placeholder="Ej: 20/20">
                                                    </div>
                                                    <div class="col-6 form-group">
                                                        <label>AV Lejos (Ojo Izquierdo)</label>
                                                        <input type="text" class="form-control" name="av_lejos_oi" placeholder="Ej: 20/30">
                                                    </div>
                                                    <div class="col-6 form-group">
                                                        <label>AV Cerca (Ojo Derecho)</label>
                                                        <input type="text" class="form-control" name="av_cerca_od" placeholder="Ej: 0.50 M">
                                                    </div>
                                                    <div class="col-6 form-group">
                                                        <label>AV Cerca (Ojo Izquierdo)</label>
                                                        <input type="text" class="form-control" name="av_cerca_oi" placeholder="Ej: 0.50 M">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="card card-outline card-info shadow-sm">
                                            <div class="card-header"><h3 class="card-title font-weight-bold">Presión Intraocular (PIO)</h3></div>
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-6 form-group">
                                                        <label>PIO Ojo Derecho (OD)</label>
                                                        <input type="text" class="form-control" name="presion_intraocular_od" placeholder="Ej: 15 mmHg">
                                                    </div>
                                                    <div class="col-6 form-group">
                                                        <label>PIO Ojo Izquierdo (OI)</label>
                                                        <input type="text" class="form-control" name="presion_intraocular_oi" placeholder="Ej: 16 mmHg">
                                                    </div>
                                                </div>
                                                <p class="text-muted small pt-2">Registre la presión calculada mediante tonometría.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card card-outline card-primary shadow-sm mt-3">
                                    <div class="card-header"><h3 class="card-title font-weight-bold"><i class="fas fa-glasses"></i> Diagnóstico de Refracción</h3></div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-striped text-center minimal">
                                                <thead>
                                                    <tr class="bg-light">
                                                        <th>Ojo</th>
                                                        <th>Esfera (Esf)</th>
                                                        <th>Cilindro (Cil)</th>
                                                        <th>Eje</th>
                                                        <th>Adición (Add)</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td class="font-weight-bold align-middle text-primary">OD (Derecho)</td>
                                                        <td><input type="text" class="form-control text-center" name="ref_esfera_od" placeholder="-1.50"></td>
                                                        <td><input type="text" class="form-control text-center" name="ref_cilindro_od" placeholder="-0.75"></td>
                                                        <td><input type="text" class="form-control text-center" name="ref_eje_od" placeholder="180°"></td>
                                                        <td><input type="text" class="form-control text-center" name="ref_adicion_od" placeholder="+2.00"></td>
                                                    </tr>
                                                    <tr>
                                                        <td class="font-weight-bold align-middle text-info">OI (Izquierdo)</td>
                                                        <td><input type="text" class="form-control text-center" name="ref_esfera_oi" placeholder="-1.25"></td>
                                                        <td><input type="text" class="form-control text-center" name="ref_cilindro_oi" placeholder="-1.00"></td>
                                                        <td><input type="text" class="form-control text-center" name="ref_eje_oi" placeholder="15°"></td>
                                                        <td><input type="text" class="form-control text-center" name="ref_adicion_oi" placeholder="+2.00"></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mt-3">
                                    <div class="col-md-6 form-group">
                                        <label><i class="fas fa-microscope text-secondary"></i> Examen con Lámpara de Hendidura</label>
                                        <textarea class="form-control" name="examen_lente_hendidura" rows="3" placeholder="Evaluación del segmento anterior (córnea, iris, cristalino)..."></textarea>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label><i class="fas fa-eye-dropper text-secondary"></i> Examen de Fondo de Ojo</label>
                                        <textarea class="form-control" name="examen_fondo_ojo" rows="3" placeholder="Evaluación de retina, papila óptica, mácula y vasos retinales..."></textarea>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                        </div>
                    </div>

                    <div class="card-footer text-right">
                        <button type="submit" class="btn btn-success btn-lg shadow-sm">
                            <i class="fas fa-save"></i> Guardar y Finalizar Atención
                        </button>
                    </div>
                </div>
            </form>

        </div>
    </section>
</div>

<?php include 'layout/footer.php'; ?>