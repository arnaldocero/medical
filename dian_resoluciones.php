<?php 
session_start();
if (!isset($_SESSION['id']) || !isset($_SESSION['clinica'])) { header("Location: login.php"); exit(); }

include 'layout/header.php'; 
include 'layout/nav.php'; 
include 'layout/sidebar.php'; 
?>

<!-- DataTables & SweetAlert2 Estilos -->
<link rel="stylesheet" href="plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="plugins/datatables-responsive/css/responsive.bootstrap4.min.css">

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-landmark"></i> Resoluciones DIAN</h1>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                
                <!-- FORMULARIO DE REGISTRO -->
                <div class="col-md-4">
                    <div class="card card-navy">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-plus-circle"></i> Nueva Resolución</h3>
                        </div>
                        <form id="formResolucion">
                            <input type="hidden" name="action" value="guardar">
                            <div class="card-body">
                                <div class="form-group">
                                    <label>Número de Resolución *</label>
                                    <input type="text" name="numero_resolucion" class="form-control" placeholder="Ej. 187640000001" required>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 form-group">
                                        <label>Prefijo</label>
                                        <input type="text" name="prefijo" class="form-control" placeholder="Ej. SETT" max_length="10">
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label>Vigencia (Meses) *</label>
                                        <input type="number" name="vigencia_meses" class="form-control" value="12" min="1" required>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 form-group">
                                        <label>No. Inicial *</label>
                                        <input type="number" name="numero_inicial" class="form-control" value="1" min="1" required>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label>No. Final *</label>
                                        <input type="number" name="numero_final" class="form-control" placeholder="Ej. 5000" min="1" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Fecha de Resolución *</label>
                                    <input type="date" name="fecha_resolucion" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Clave Técnica DIAN</label>
                                    <input type="password" name="clave_tecnica" class="form-control" placeholder="Clave provista por la DIAN">
                                    <small class="text-muted">Requerida si manejas Facturación Electrónica directa.</small>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save"></i> Activar y Registrar</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- LISTADO COMPLETO -->
                <div class="col-md-8">
                    <div class="card card-outline card-navy">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-list"></i> Historial de Autorizaciones</h3>
                        </div>
                        <div class="card-body">
                            <table id="tablaResoluciones" class="table table-bordered table-striped table-hover dt-responsive nowrap" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>Prefijo / No.</th>
                                        <th>Rango Autorizado</th>
                                        <th>Progreso / Actual</th>
                                        <th>Vencimiento</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Carga vía AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>
</div>

<?php include 'layout/footer.php'; ?>

<!-- Plugins requeridos -->

