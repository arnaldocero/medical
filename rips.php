<?php 
session_start();
if (!isset($_SESSION['id']) || !isset($_SESSION['clinica'])) { 
    header("Location: login.php"); 
    exit(); 
}
include 'layout/header.php'; 
include 'layout/nav.php'; 
include 'layout/sidebar.php'; 
?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1><i class="fas fa-file-invoice-dollar text-indigo"></i> Generador de RIPS (Nuevo Estándar JSON)</h1>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            
            <div class="card card-outline card-indigo shadow-sm">
                <div class="card-header">
                    <h3 class="card-title">Filtrar Atenciones para Reporte</h3>
                </div>
                <div class="card-body">
                    <form id="formFiltroRips">
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label>Fecha Inicial</label>
                                <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control" value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-4 form-group">
                                <label>Fecha Final</label>
                                <input type="date" name="fecha_fin" id="fecha_fin" class="form-control" value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-4 form-group align-self-end">
                                <button type="button" id="btnBuscarAtenciones" class="btn btn-indigo btn-block shadow-sm">
                                    <i class="fas fa-search"></i> Buscar Consultas
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card card-dark shadow-sm">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title flex-grow-1">Registros Clínicos Encontrados</h3>
                    <button type="button" id="btnGenerarJSON" class="btn btn-success btn-sm shadow" disabled>
                        <i class="fas fa-file-code"></i> Generar y Descargar JSON RIPS
                    </button>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover table-striped text-nowrap m-0" id="tablaRips">
                        <thead class="bg-light">
                            <tr>
                                <th style="width: 40px;"><input type="checkbox" id="checkTodos"></th>
                                <th>Fecha/Hora</th>
                                <th>Documento</th>
                                <th>Paciente</th>
                                <th>Diagnóstico Principal</th>
                                <th>Médico</th>
                            </tr>
                        </thead>
                        <tbody id="cuerpoRips">
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Utilice los filtros superiores para cargar las atenciones médicas.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </section>
</div>

<?php 
include 'layout/footer.php'; 
?>