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

<!-- Hojas de estilo requeridas -->
<link rel="stylesheet" href="plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="plugins/datatables-responsive/css/responsive.bootstrap4.min.css">

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1><i class="fas fa-boxes"></i> Inventario: Control de Artículos Generales</h1>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                
                <!-- FORMULARIO DE REGISTRO / EDICIÓN -->
                <div class="col-md-4">
                    <div class="card card-teal">
                        <div class="card-header">
                            <h3 class="card-title" id="formTitle"><i class="fas fa-plus"></i> Registrar Artículo</h3>
                        </div>
                        <form id="formArticulo">
                            <input type="hidden" name="action" id="action" value="guardar">
                            <input type="hidden" name="id_articulo" id="id_articulo" value="">
                            
                            <div class="card-body">
                                <div class="form-group">
                                    <label>Código del Artículo *</label>
                                    <input type="text" name="codigo" id="codigo" class="form-control" placeholder="Ej: ART-001" required>
                                </div>
                                <div class="form-group">
                                    <label>Nombre del Artículo *</label>
                                    <input type="text" name="nombre" id="nombre" class="form-control" placeholder="Ej: Gafas de Seguridad" required>
                                </div>
                                <div class="form-group">
                                    <label>Categoría *</label>
                                    <select name="categoria" id="categoria" class="form-control" required>
                                        <option value="Objetos de aseo">Objetos de aseo</option>
                                        <option value="Gafas">Gafas</option>
                                        <option value="Insumos">Insumos</option>
                                        <option value="Equipos">Equipos</option>
                                        <option value="Otros artículos">Otros artículos</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Descripción</label>
                                    <textarea name="descripcion" id="descripcion" class="form-control" rows="2" placeholder="Detalles del artículo..."></textarea>
                                </div>
                                <div class="row">
                                    <div class="col-6 form-group">
                                        <label>U. Medida *</label>
                                        <input type="text" name="unidad_medida" id="unidad_medida" class="form-control" value="Unidad" required>
                                    </div>
                                    <div class="col-6 form-group">
                                        <label>Alerta Mínima *</label>
                                        <input type="number" name="stock_minimo" id="stock_minimo" class="form-control" value="5" min="1" required>
                                    </div>
                                </div>
                                <div class="row" id="containerStockInicial">
                                    <div class="col-12 form-group">
                                        <label>Stock Inicial</label>
                                        <input type="number" name="stock_actual" id="stock_actual" class="form-control" value="0" min="0">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-6 form-group">
                                        <label>Precio Compra ($) *</label>
                                        <input type="number" step="0.01" name="precio_compra" id="precio_compra" class="form-control" value="0.00" required>
                                    </div>
                                    <div class="col-6 form-group">
                                        <label>Precio Venta ($) *</label>
                                        <input type="number" step="0.01" name="precio_venta" id="precio_venta" class="form-control" value="0.00" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Proveedor</label>
                                    <input type="text" name="proveedor" id="proveedor" class="form-control" placeholder="Nombre del proveedor">
                                </div>
                                <div class="row">
                                    <div class="col-6 form-group">
                                        <label>Fecha Ingreso *</label>
                                        <input type="date" name="fecha_ingreso" id="fecha_ingreso" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="col-6 form-group">
                                        <label>Vencimiento (Opcional)</label>
                                        <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" class="form-control">
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-teal btn-block"><i class="fas fa-save"></i> <span id="btnText">Registrar Artículo</span></button>
                                <button type="button" id="btnCancelarEdicion" class="btn btn-secondary btn-block d-none"><i class="fas fa-times"></i> Cancelar Edición</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- LISTADO Y TABLA DE ARTÍCULOS -->
                <div class="col-md-8">
                    <div class="card card-outline card-teal">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-list"></i> Artículos en Almacén</h3>
                        </div>
                        <div class="card-body">
                            <table id="tablaArticulos" class="table table-bordered table-striped table-hover dt-responsive nowrap" style="width:100%;">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Artículo</th>
                                        <th>Categoría</th>
                                        <th>Stock</th>
                                        <th>Estado Alerta</th>
                                        <th>Acciones</th>
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

<!-- MODAL PARA REGISTRAR ENTRADAS / SALIDAS DE STOCK -->
<div class="modal fade" id="modalStock" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-teal">
                <h5 class="modal-title" id="modalStockTitle"><i class="fas fa-boxes"></i> Ajuste de Stock</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="formMovimientoStock">
                <input type="hidden" name="action" value="ajustar_stock">
                <input type="hidden" name="stock_articulo_id" id="stock_articulo_id">
                <input type="hidden" name="tipo_movimiento" id="tipo_movimiento">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label>Artículo Seleccionado</label>
                        <input type="text" id="stock_articulo_nombre" class="form-control" readonly>
                    </div>
                    <div class="row">
                        <div class="col-6 form-group">
                            <label>Stock Actual</label>
                            <input type="text" id="stock_articulo_actual" class="form-control" readonly>
                        </div>
                        <div class="col-6 form-group">
                            <label>Cantidad a Procesar *</label>
                            <input type="number" name="cantidad" id="stock_cantidad" class="form-control" min="1" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Motivo / Observación *</label>
                        <input type="text" name="motivo" id="stock_motivo" class="form-control" placeholder="Ej: Compra de lote, Pérdida, Rotura" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-teal" id="btnSubmitStock">Procesar Ajuste</button>
                </div>
            </form>
        </div>
    </div>
</div>



<?php include 'layout/footer.php'; ?>