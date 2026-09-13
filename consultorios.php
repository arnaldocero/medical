<?php 
session_start();
if (!isset($_SESSION['id'])) { header("Location: login.php"); exit(); }
include 'layout/header.php'; include 'layout/nav.php'; include 'layout/sidebar.php'; 
require_once 'config/db.php';

// Obtenemos los consultorios con el nombre de su sede
$stmt = $pdo->prepare("
    SELECT c.*, cm.nombre_centro 
    FROM consultorios c
    INNER JOIN centros_medicos cm ON c.centro_id = cm.id
    WHERE c.clinica_id = ?
");
$stmt->execute([$_SESSION['clinica']]);
$consultorios = $stmt->fetchAll();

// Obtenemos las sedes activas para el select del modal
$sedes = $pdo->prepare("SELECT id, nombre_centro FROM centros_medicos WHERE clinica_id = ? AND estado = 1");
$sedes->execute([$_SESSION['clinica']]);
$listaSedes = $sedes->fetchAll();
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1>Gestión de Consultorios</h1></div>
                <div class="col-sm-6 text-right">
                    <button class="btn btn-success" data-toggle="modal" data-target="#modalConsultorio">
                        <i class="fas fa-plus"></i> Nuevo Consultorio
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="card">
            <div class="card-body">
                <table id="tablaConsultorios" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Sede / Centro</th>
                            <th>Nombre Consultorio</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($consultorios as $con): ?>
                        <tr>
                            <td><?= $con['nombre_centro'] ?></td>
                            <td><?= $con['nombre_consultorio'] ?></td>
                            <td><?= $con['tipo'] ?></td>
                            <td><?= $con['estado'] == 1 ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-danger">Inactivo</span>' ?></td>
                            <td>
                                <button class="btn btn-warning btn-sm btnEditarCon" 
                                    data-id="<?= $con['id'] ?>" 
                                    data-centro="<?= $con['centro_id'] ?>"
                                    data-nombre="<?= $con['nombre_consultorio'] ?>"
                                    data-tipo="<?= $con['tipo'] ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<!-- Modal -->
<div class="modal fade" id="modalConsultorio">
    <div class="modal-dialog">
        <form id="formConsultorio">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h4 class="modal-title">Configurar Consultorio</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="idConsultorio" id="idConsultorio">
                    
                    <div class="form-group">
                        <label>Sede Destino</label>
                        <select name="centro_id" id="centro_id" class="form-control" required>
                            <option value="">Seleccione una sede...</option>
                            <?php foreach($listaSedes as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= $s['nombre_centro'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Nombre/Número del Consultorio</label>
                        <input type="text" name="nombre_consultorio" id="nombre_con" class="form-control" placeholder="Ej: Consultorio 101" required>
                    </div>

                    <div class="form-group">
                        <label>Tipo de Área</label>
                        <select name="tipo" id="tipo_con" class="form-control">
                            <option value="General">Consultorio General</option>
                            <option value="Especializado">Consultorio Especializado</option>
                            <option value="Procedimientos">Sala de Procedimientos</option>
                            <option value="Urgencias">Box de Urgencias</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-success">Guardar Consultorio</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include 'layout/footer.php'; ?>
