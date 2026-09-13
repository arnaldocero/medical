<?php 
session_start();
if (!isset($_SESSION['id']) || !isset($_SESSION['clinica'])) { header("Location: login.php"); exit(); }
include 'layout/header.php'; 
include 'layout/nav.php'; 
include 'layout/sidebar.php'; 
?>

<link rel="stylesheet" href="plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="plugins/datatables-responsive/css/responsive.bootstrap4.min.css">

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1><i class="fas fa-pills"></i> Farmacia: Control de Medicamentos</h1>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                
                <!-- REGISTRO -->
                <div class="col-md-4">
                    <div class="card card-teal">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-plus"></i> Ingresar Al Inventario</h3>
                        </div>
                        <form id="formMedicamento">
                            <input type="hidden" name="action" value="guardar">
                            <div class="card-body">
                                <div class="form-group">
                                    <label>Nombre Genérico *</label>
                                    <input type="text" name="nombre_generico" class="form-control" placeholder="Ej: Acetaminofén" required>
                                </div>
                                <div class="form-group">
                                    <label>Nombre Comercial / Laboratorio</label>
                                    <input type="text" name="nombre_comercial" class="form-control" placeholder="Ej: MK / Genfar">
                                </div>
                                <div class="form-group">
                                    <label>Presentación y Concentración *</label>
                                    <input type="text" name="presentacion" class="form-control" placeholder="Ej: Tableta 500mg / Ampolla 2ml" required>
                                </div>
                                <div class="row">
                                    <div class="col-6 form-group">
                                        <label>Stock Inicial</label>
                                        <input type="number" name="stock_actual" class="form-control" value="0" min="0">
                                    </div>
                                    <div class="col-6 form-group">
                                        <label>Alerta Mínima</label>
                                        <input type="number" name="stock_minimo" class="form-control" value="10" min="1">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Código de Barras</label>
                                    <input type="text" name="codigo_barras" class="form-control" placeholder="Opcional para escáner">
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-teal btn-block"><i class="fas fa-save"></i> Registrar Medicamento</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- LISTADO / STOCK -->
                <div class="col-md-8">
                    <div class="card card-outline card-teal">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-boxes"></i> Existencias en Bodega</h3>
                        </div>
                        <div class="card-body">
                            <table id="tablaMedicamentos" class="table table-bordered table-striped table-hover dt-responsive nowrap" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>Medicamento (Principio Activo)</th>
                                        <th>Presentación</th>
                                        <th>Laboratorio</th>
                                        <th>Stock Actual</th>
                                        <th>Estado Alerta</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>
</div>

<?php include 'layout/footer.php'; ?>
