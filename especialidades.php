<?php 
session_start();
if (!isset($_SESSION['id'])) { header("Location: login.php"); exit(); }

include 'layout/header.php'; 
include 'layout/nav.php'; 
include 'layout/sidebar.php'; 
require_once 'config/db.php';

$stmt = $pdo->prepare("SELECT * FROM especialidades WHERE clinica_id = ? ORDER BY nombre ASC");
$stmt->execute([$_SESSION['clinica']]);
$especialidades = $stmt->fetchAll();
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1>Gestión de Especialidades</h1></div>
                <div class="col-sm-6 text-right">
                    <button class="btn btn-success" data-toggle="modal" data-target="#modalEspecialidad">
                        <i class="fas fa-plus"></i> Nueva Especialidad
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="card">
            <div class="card-body">
                <table id="tablaEspecialidades" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($especialidades as $e): ?>
                        <tr>
                            <td><?= $e['nombre'] ?></td>
                            <td><?= $e['descripcion'] ?></td>
                            <td>
                                <?= $e['estado'] == 1 
                                    ? '<span class="badge badge-success">Activo</span>' 
                                    : '<span class="badge badge-danger">Inactivo</span>' ?>
                            </td>
                            <td>
                                <button class="btn btn-warning btn-sm btnEditarEsp" 
                                    data-id="<?= $e['id'] ?>" 
                                    data-nombre="<?= $e['nombre'] ?>" 
                                    data-descripcion="<?= $e['descripcion'] ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger btn-sm btnEliminarEsp" data-id="<?= $e['id'] ?>" data-nombre="<?= $e['nombre'] ?>">
                                    <i class="fas fa-trash"></i>
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

<!-- Modal para Crear/Editar -->
<div class="modal fade" id="modalEspecialidad">
    <div class="modal-dialog">
        <form id="formEspecialidad">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h4 class="modal-title">Nueva Especialidad</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="idEspecialidad" id="idEspecialidad">
                    <div class="form-group">
                        <label>Nombre de la Especialidad</label>
                        <input type="text" name="nombre" id="nombreEsp" class="form-control" required placeholder="Ej: Cardiología">
                    </div>
                    <div class="form-group">
                        <label>Descripción (Opcional)</label>
                        <textarea name="descripcion" id="descEsp" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarEsp">Guardar Especialidad</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include 'layout/footer.php'; ?>
