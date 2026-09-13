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
                    <h1><i class="fas fa-file-invoice-dollar"></i> Manuales Tarifarios</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <button class="btn btn-primary" data-toggle="modal" data-target="#modalManual" id="btnNuevoManual">
                        <i class="fas fa-plus"></i> Crear Manual
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <!-- VISTA 1: LISTADO DE MANUALES -->
        <div class="card card-navy" id="seccionManuales">
            <div class="card-body">
                <table id="tablaManuales" class="table table-bordered table-striped w-100">
                    <thead>
                        <tr>
                            <th>Nombre del Manual</th>
                            <th>Tipo Base</th>
                            <th>Año Vigencia</th>
                            <th>Estado</th>
                            <th style="width: 20%;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <!-- VISTA 2: ASIGNACIÓN DE TARIFAS (OCULTA POR DEFECTO) -->
        <div class="card card-success d-none" id="seccionTarifas">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-coins"></i> Configurando Precios para: <b id="lblManualSeleccionado"></b></h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" id="btnVolverManuales"><i class="fas fa-arrow-left"></i> Volver a Manuales</button>
                </div>
            </div>
            <div class="card-body">
                <blockquote>
                    <i class="fas fa-info-circle"></i> Los precios modificados se guardan automáticamente al cambiar de celda (pérdida de foco) o presionar Enter.
                </blockquote>
                <table id="tablaTarifas" class="table table-bordered w-100">
                    <thead>
                        <tr>
                            <th style="width: 15%;">Código CUPS</th>
                            <th>Servicio / Procedimiento</th>
                            <th style="width: 25%;">Valor Comercial ($)</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<!-- MODAL CREAR MANUAL -->
<div class="modal fade" id="modalManual" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formManual">
                <input type="hidden" name="action" value="registrar">
                <div class="modal-header bg-primary text-white">
                    <h4 class="modal-title"><i class="fas fa-folder-plus"></i> Nuevo Manual Tarifario</h4>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nombre Comercial del Manual *</label>
                        <input type="text" name="nombre_manual" class="form-control" placeholder="Ej: Manual Preferencial Sanitas 2026" required>
                    </div>
                    <div class="form-group">
                        <label>Normatividad / Tipo Base</label>
                        <select name="tipo_base" class="form-control">
                            <option value="PROPIO">Tarifario Propio / Particular</option>
                            <option value="SOAT">Estructura Base SOAT</option>
                            <option value="ISS">Estructura Base ISS</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Año de Vigencia *</label>
                        <input type="number" name="año_vigencia" class="form-control" value="2026" required>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear Manual</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'layout/footer.php'; ?>
