<?php 
session_start();
if (!isset($_SESSION['id'])) { header("Location: login.php"); exit(); }

include 'layout/header.php'; 
include 'layout/nav.php'; 
include 'layout/sidebar.php'; 
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-concierge-bell"></i> Portafolio de Servicios (CUPS)</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <button class="btn btn-success" data-toggle="modal" data-target="#modalServicio" id="btnNuevoServicio">
                        <i class="fas fa-plus"></i> Agregar Servicio / CUPS
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaServicios" class="table table-bordered table-striped w-100">
                        <thead>
                            <tr>
                                <th style="width: 15%;">Código CUPS</th>
                                <th>Descripción del Servicio</th>
                                <th style="width: 18%;">Fecha de Alta</th>
                                <th style="width: 12%;">Estado</th>
                                <th style="width: 15%;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- MODAL DE GESTIÓN -->
<div class="modal fade" id="modalServicio" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formServicio">
                <input type="hidden" name="action" id="action" value="registrar">
                <input type="hidden" name="servicio_id" id="servicio_id" value="">

                <div class="modal-header bg-navy text-white">
                    <h4 class="modal-title" id="modalTitle"><i class="fas fa-plus"></i> Nuevo Servicio</h4>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="codigo_cups">Código CUPS *</label>
                        <input type="text" name="codigo_cups" id="codigo_cups" class="form-control text-uppercase" placeholder="Ej: 890201" required>
                    </div>
                    <div class="form-group">
                        <label for="nombre_servicio">Nombre / Descripción del Servicio *</label>
                        <textarea name="nombre_servicio" id="nombre_servicio" class="form-control" rows="3" placeholder="Ej: Consulta de primera vez por especialista en medicina interna..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardar">Guardar Servicio</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'layout/footer.php'; ?>
