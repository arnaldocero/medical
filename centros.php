<?php 
session_start();
if (!isset($_SESSION['id'])) { header("Location: login.php"); exit(); }

include 'layout/header.php'; 
include 'layout/nav.php'; 
include 'layout/sidebar.php'; 
require_once 'config/db.php';

// --- CORRECCIÓN AQUÍ: Filtrar por la clínica del usuario de la sesión ---
$stmt = $pdo->prepare("SELECT * FROM centros_medicos WHERE clinica_id = ? ORDER BY id DESC");
$stmt->execute([$_SESSION['clinica']]);
$centros = $stmt->fetchAll();
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6"><h1>Centros de Atención</h1></div>
                <div class="col-sm-6 text-right">
                    <button class="btn btn-primary" data-toggle="modal" data-target="#modalCentro">
                        <i class="fas fa-hospital"></i> Nuevo Centro
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="card">
            <div class="card-body">
                <table id="tablaCentros" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>NIT</th>
                            <th>Ciudad</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($centros as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['nombre_centro']) ?></td>
                            <td><?= htmlspecialchars($c['nit']) ?></td>
                            <td><?= htmlspecialchars($c['ciudad']) ?></td>
                            <td>
                                <?= $c['estado'] == 1 
                                    ? '<span class="badge badge-success">Activo</span>' 
                                    : '<span class="badge badge-danger">Inactivo</span>' ?>
                            </td>
                            <td>
                                <button class="btn btn-warning btn-sm btnEditarCentro" 
                                    data-id="<?= $c['id'] ?>" 
                                    data-nombre="<?= $c['nombre_centro'] ?>"
                                    data-nit="<?= $c['nit'] ?>"
                                    data-ciudad="<?= $c['ciudad'] ?>"
                                    data-direccion="<?= $c['direccion'] ?>"
                                    data-email="<?= $c['email'] ?>"
                                    data-tel="<?= $c['telefono'] ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger btn-sm btnEliminarCentro" 
    data-id="<?= $c['id'] ?>" 
    data-nombre="<?= $c['nombre_centro'] ?>">
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

<div class="modal fade" id="modalCentro">
    <div class="modal-dialog modal-lg">
        <form id="formCentro">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h4 class="modal-title">Registrar Centro Médico</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="idCentro" name="idCentro">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Nombre del Centro</label>
                                <input type="text" name="nombre_centro" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>NIT</label>
                                <input type="text" name="nit" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Email de Contacto</label>
                                <input type="email" name="email" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Teléfono</label>
                                <input type="text" name="telefono" class="form-control">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Ciudad</label>
                                <input type="text" name="ciudad" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Dirección</label>
                                <input type="text" name="direccion" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Registro</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include 'layout/footer.php'; ?>

<script>
$(function () {
    $("#tablaCentros").DataTable({
        "responsive": true, 
        "autoWidth": false,
        "language": { "url": "//cdn.datatables.net/plug-ins/1.10.20/i18n/Spanish.json" }
    });
});
</script>