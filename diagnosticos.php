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
                    <h1><i class="fas fa-notes-medical"></i> Catálogo de Diagnósticos (CIE-10 / CIE-11)</h1>
                </div>
                <div class="col-sm-6 text-right">
                    <button class="btn btn-success" data-toggle="modal" data-target="#modalDiagnostico" id="btnNuevoDiag">
                        <i class="fas fa-plus"></i> Crear Diagnóstico Manual
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="card">
            <div class="card-header bg-light">
                <div class="row">
                    <div class="col-md-6 mt-1">
                        <h3 class="card-title text-muted">Escribe un código o palabra clave para filtrar la base de datos internacional</h3>
                    </div>
                    <div class="col-md-6">
                        <!-- Buscador dinámico acoplado al servidor -->
                        <div class="input-group">
                            <input type="text" id="searchDiag" class="form-control" placeholder="Ej: E119, Diabetes, Hipertensión, Fractura...">
                            <div class="input-group-append">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaDiagnosticos" class="table table-bordered table-striped w-100">
                        <thead>
                            <tr>
                                <th style="width: 12%;">Código CIE</th>
                                <th>Descripción de la Patología / Enfermedad</th>
                                <th style="width: 12%;">Clasificación</th>
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

<!-- MODAL GESTIÓN -->
<div class="modal fade" id="modalDiagnostico" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formDiagnostico">
                <input type="hidden" name="action" id="action" value="registrar">
                <input type="hidden" name="diagnostico_id" id="diagnostico_id" value="">

                <div class="modal-header bg-success text-white">
                    <h4 class="modal-title" id="modalTitle"><i class="fas fa-plus"></i> Añadir Diagnóstico</h4>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Versión Estándar *</label>
                        <select name="version" id="version" class="form-control">
                            <option value="CIE-10">CIE-10 (Uso estándar actual)</option>
                            <option value="CIE-11">CIE-11 (Nueva versión transicional)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Código Alfanumérico *</label>
                        <input type="text" name="codigo_cie" id="codigo_cie" class="form-control text-uppercase" placeholder="Ej: I10X, E119, U071" required>
                    </div>
                    <div class="form-group">
                        <label>Descripción Científica / Médica *</label>
                        <textarea name="descripcion" id="descripcion" class="form-control" rows="3" placeholder="Descripción formal de la patología..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardar">Registrar en Catálogo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'layout/footer.php'; ?>
