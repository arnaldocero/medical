<?php 
session_start();
if (!isset($_SESSION['id']) || !isset($_SESSION['clinica'])) { header("Location: login.php"); exit(); }
include 'layout/header.php'; 
include 'layout/nav.php'; 
include 'layout/sidebar.php'; 
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1><i class="fas fa-dolly-flatbed"></i> Despacho de Farmacia</h1>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <!-- BUSCADOR PRINCIPAL -->
            <div class="card card-outline card-primary">
                <div class="card-body">
                    <form id="formBuscarFormula">
                        <div class="row align-items-end">
                            <div class="col-md-9">
                                <label>Documento de Identidad del Paciente o N° de Fórmula</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    </div>
                                    <input type="text" id="inputBusqueda" class="form-control" placeholder="Ej: 10453322 o 45" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-filter"></i> Buscar Fórmula</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- PANEL DE DETALLES (OCULTO POR DEFECTO) -->
            <div class="row d-none" id="panelDetalleFormula">
                <!-- Información General -->
                <div class="col-md-4">
                    <div class="card card-info">
                        <div class="card-header"><h3 class="card-title">Datos del Paciente</h3></div>
                        <div class="card-body">
                            <p><strong>Paciente:</strong> <span id="txtPaciente">...</span></p>
                            <p><strong>Documento:</strong> <span id="txtDocumento">...</span></p>
                            <p><strong>Diagnóstico:</strong> <span class="badge badge-warning" id="txtDiagnostico">...</span></p>
                            <p><strong>Fecha Emisión:</strong> <span id="txtFecha">...</span></p>
                            <hr>
                            <p><strong>Observaciones Médicas:</strong></p>
                            <p class="text-muted" id="txtObservaciones">...</p>
                        </div>
                    </div>
                </div>

                <!-- Listado de Medicamentos a entregar -->
                <div class="col-md-8">
                    <div class="card card-success">
                        <div class="card-header"><h3 class="card-title"><i class="fas fa-pills"></i> Medicamentos Recetados</h3></div>
                        <div class="card-body p-0">
                            <table class="table table-striped table-valign-middle">
                                <thead>
                                    <tr>
                                        <th>Medicamento</th>
                                        <th class="text-center">Cant. Solicitada</th>
                                        <th>Instrucciones de Uso</th>
                                        <th>Disponibilidad en Stock</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyMedicamentos">
                                    <!-- Se llena dinámicamente -->
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer text-right">
                            <input type="hidden" id="hdnFormulaId">
                            <button type="button" id="btnEntregarFormula" class="btn btn-success btn-lg shadow">
                                <i class="fas fa-check-circle"></i> Confirmar Entrega de Medicamentos
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
</div>

<?php include 'layout/footer.php'; ?>
<!-- Vinculamos el JS correspondiente -->
