<?php 
session_start();
if (!isset($_SESSION['id'])) { header("Location: login.php"); exit(); }

include 'layout/header.php'; 
include 'layout/nav.php'; 
include 'layout/sidebar.php'; 
require_once 'config/db.php'; 
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-hospital-alt"></i> Gestión de EPS</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <button class="btn btn-success" data-toggle="modal" data-target="#modalEPS" id="btnNuevaEPS">
                        <i class="fas fa-plus"></i> Registrar EPS
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaEPS" class="table table-bordered table-striped w-100">
                        <thead>
                            <tr>
                                <th>Código MinSalud</th>
                                <th>Nombre Entidad</th>
                                <th>Fecha Registro</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Controlado dinámicamente por DataTables -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- VENTANA MODAL ACTUALIZADA CON CONTROL DE CLÍNICA -->
<div class="modal fade" id="modalEPS" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog"> 
        <div class="modal-content">
            <form id="formEPS">
                <!-- Variables de control ocultas -->
                <input type="hidden" name="action" id="action" value="registrar">
                <input type="hidden" name="eps_id" id="eps_id" value="">
                <input type="hidden" name="clinica_id" value="<?= intval($_SESSION['clinica'] ?? 0) ?>">

                <div class="modal-header bg-primary text-white">
                    <h4 class="modal-title" id="modalTitle"><i class="fas fa-hospital-alt"></i> Registrar EPS</h4>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label>Código MinSalud *</label>
                            <input type="text" name="codigo_minsalud" id="codigo_minsalud" class="form-control" placeholder="Ej: EPS001" required>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label>Nombre de la EPS *</label>
                            <input type="text" name="nombre" id="nombre" class="form-control" placeholder="Nombre completo de la entidad" required>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardar">Guardar Entidad</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'layout/footer.php'; ?>