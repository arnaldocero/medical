<?php
session_start();
if (!isset($_SESSION['id'])) { header("Location: login.php"); exit(); }

include 'layout/header.php';
include 'layout/nav.php';
include 'layout/sidebar.php';
require_once 'config/db.php';

// Capturamos el ID del paciente desde la URL
$paciente_id = $_GET['paciente_id'] ?? '';

if (empty($paciente_id)) {
    echo "<div class='content-wrapper'><p class='p-4 text-warning'>Por favor, seleccione un paciente válido desde el panel para ver su expediente.</p></div>";
    include 'layout/footer.php';
    exit();
}

// Consultamos los datos generales del paciente para el encabezado
$stmt = $pdo->prepare("
    SELECT u.id, u.nombre, pd.documento_identidad, pd.fecha_nacimiento, pd.genero
    FROM usuarios_clinicas u
    INNER JOIN pacientes_datos pd ON u.id = pd.usuario_id
    WHERE u.id = ?
");
$stmt->execute([$paciente_id]);
$paciente = $stmt->fetch();

if (!$paciente) {
    echo "<div class='content-wrapper'><p class='p-4 text-danger'>El paciente solicitado no existe en el sistema.</p></div>";
    include 'layout/footer.php';
    exit();
}

// Calcular edad
$cumpleanos = new DateTime($paciente['fecha_nacimiento']);
$hoy = new DateTime();
$edad = $hoy->diff($cumpleanos)->y;
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-folder-open text-indigo"></i> Expediente Digital de Anexos</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <a href="panel_medico.php" class="btn btn-secondary shadow-sm">
                        <i class="fas fa-chevron-left"></i> Volver al Panel
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">

            <div class="card card-indigo shadow-sm">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-user-injured"></i> Información del Paciente</h3>
                </div>
                <div class="card-body bg-light">
                    <div class="row">
                        <div class="col-md-4">
                            <strong>Nombre:</strong> <span class="text-uppercase"><?= htmlspecialchars($paciente['nombre']) ?></span>
                        </div>
                        <div class="col-md-3">
                            <strong>Documento:</strong> <?= htmlspecialchars($paciente['documento_identidad']) ?>
                        </div>
                        <div class="col-md-2">
                            <strong>Edad:</strong> <?= $edad ?> años
                        </div>
                        <div class="col-md-3">
                            <strong>Género:</strong> <span class="text-uppercase"><?= htmlspecialchars($paciente['genero'] ?? 'No definido') ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card card-outline card-indigo shadow-sm">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-cloud-upload-alt text-indigo"></i> Cargar Nuevo Documento / Ayuda Diagnóstica</h3>
                </div>
                <div class="card-body">
                    <form id="formExpedienteAdjuntos">
                        <input type="hidden" id="paciente_id_exp" value="<?= $paciente['id'] ?>">
                        <input type="hidden" id="cita_id_exp" value=""> <div class="row">
                            <div class="col-md-4 form-group">
                                <label>Tipo de Documento</label>
                                <select id="tipo_archivo_exp" class="form-control">
                                    <option value="Laboratorio">Laboratorio Clínico</option>
                                    <option value="Imagenologia">Imagenología (Rx, Tac, Eco)</option>
                                    <option value="Odontologia">Registro Odontológico</option>
                                    <option value="Otros" selected>Otros Documentos / Historia Vieja</option>
                                </select>
                            </div>
                            <div class="col-md-5 form-group">
                                <label>Descripción / Nombre del Examen</label>
                                <input type="text" id="nombre_personalizado_exp" class="form-control" placeholder="Ej: Reporte de Laboratorio Pre-quirúrgico" required>
                            </div>
                            <div class="col-md-3 form-group">
                                <label>Archivo</label>
                                <div class="custom-file">
                                    <input type="file" id="archivo_adjunto_exp" class="custom-file-input" accept=".pdf,.jpg,.jpeg,.png" required>
                                    <label class="custom-file-label" for="archivo_adjunto_exp" data-browse="Buscar">Elegir...</label>
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <button type="submit" id="btnSubirExpediente" class="btn btn-indigo shadow-sm">
                                <i class="fas fa-file-upload"></i> Cargar al Historial Digital
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-dark">
                    <h3 class="card-title"><i class="fas fa-history"></i> Archivos y Documentos Almacenados</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-hover m-0">
                            <thead>
                                <tr>
                                    <th>Fecha Registro</th>
                                    <th>Categoría</th>
                                    <th>Descripción del Documento</th>
                                    <th>Registrado Por</th>
                                    <th style="width: 120px;" class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="cuerpoAdjuntosExpediente">
                                </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<?php include 'layout/footer.php'; ?>