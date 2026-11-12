<?php
session_start();
// Seguridad: sesión activa (o "Recordarme") + permiso de la pantalla
require_once 'config/auth.php';
protegerPagina('auditoria.ver');

include 'layout/header.php';
include 'layout/nav.php';
include 'layout/sidebar.php';
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1><i class="fas fa-clipboard-list text-primary"></i> Auditoría de Cambios</h1>
            <small class="text-muted">Quién hizo qué, cuándo y desde dónde. Los registros no se pueden modificar ni borrar.</small>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <!-- Filtros -->
            <div class="card card-outline card-primary">
                <div class="card-body pb-1">
                    <form id="formFiltrosAuditoria" class="row">
                        <div class="col-md-2 form-group">
                            <label class="small mb-1">Desde</label>
                            <input type="date" name="desde" id="aud_desde" class="form-control form-control-sm" value="<?= date('Y-m-d', strtotime('-7 days')) ?>">
                        </div>
                        <div class="col-md-2 form-group">
                            <label class="small mb-1">Hasta</label>
                            <input type="date" name="hasta" id="aud_hasta" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-2 form-group">
                            <label class="small mb-1">Módulo</label>
                            <select name="modulo" id="aud_modulo" class="form-control form-control-sm"><option value="">Todos</option></select>
                        </div>
                        <div class="col-md-2 form-group">
                            <label class="small mb-1">Usuario</label>
                            <select name="usuario_id" id="aud_usuario" class="form-control form-control-sm"><option value="">Todos</option></select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label class="small mb-1">Texto o ID del registro</label>
                            <input type="text" name="texto" class="form-control form-control-sm no-mayusculas" placeholder="Ej: factura, FAC-12, nombre del paciente...">
                        </div>
                        <div class="col-md-1 form-group d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm btn-block"><i class="fas fa-search"></i></button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <table id="tablaAuditoria" class="table table-sm table-bordered table-striped table-hover w-100">
                        <thead>
                            <tr>
                                <th style="width: 140px;">Fecha</th>
                                <th>Usuario</th>
                                <th>Módulo</th>
                                <th>Acción</th>
                                <th>Descripción</th>
                                <th>Sede</th>
                                <th>IP</th>
                                <th class="text-center" style="width: 60px;"></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Detalle de un registro: antes / después -->
<div class="modal fade" id="modalDetalleAuditoria" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-search-plus"></i> Detalle del cambio</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body" id="cuerpoDetalleAuditoria"></div>
        </div>
    </div>
</div>

<?php include 'layout/footer.php'; ?>
