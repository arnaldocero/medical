$(document).ready(function () {
    const actionUrl = 'ajax/articulo_action.php';
    
    // 1. Inicialización de la Tabla Dinámica de Artículos
    let table = $('#tablaArticulos').DataTable({
        "ajax": {
            "url": actionUrl,
            "type": "POST",
            "data": { action: 'listar' }
        },
        "columns": [
            { "data": "codigo", "render": d => `<code>${d}</code>` },
            { "data": "nombre", "render": d => `<b>${d}</b>` },
            { "data": "categoria" },
            { "data": "stock_actual", "render": (d, t, r) => `<span class="badge badge-light px-3 py-2 border shadow-sm">${d} ${r.unidad_medida}</span>` },
            {
                "data": null,
                "render": function (data) {
                    let actual = parseInt(data.stock_actual);
                    let minimo = parseInt(data.stock_minimo);
                    
                    if (actual === 0) {
                        return '<span class="badge badge-danger"><i class="fas fa-times-circle"></i> Agotado</span>';
                    } else if (actual <= minimo) {
                        return '<span class="badge badge-warning"><i class="fas fa-exclamation-triangle"></i> Stock Crítico</span>';
                    }
                    return '<span class="badge badge-success"><i class="fas fa-check-circle"></i> Óptimo</span>';
                }
            },
            {
                "data": null,
                "orderable": false,
                "render": function (data) {
                    return `
                        <div class="btn-group">
                            <button class="btn btn-xs btn-success btnEntrada" data-id="${data.id_articulo}" data-nombre="${data.nombre}" data-stock="${data.stock_actual}" title="Entrada de Stock"><i class="fas fa-plus-square"></i></button>
                            <button class="btn btn-xs btn-warning text-white btnSalida" data-id="${data.id_articulo}" data-nombre="${data.nombre}" data-stock="${data.stock_actual}" title="Salida de Stock"><i class="fas fa-minus-square"></i></button>
                            <button class="btn btn-xs btn-info btnEditar" data-id="${data.id_articulo}" title="Editar Ficha"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-xs btn-danger btnEliminar" data-id="${data.id_articulo}" data-nombre="${data.nombre}" title="Eliminar de Inventario"><i class="fas fa-trash"></i></button>
                        </div>
                    `;
                }
            }
        ],
        "responsive": true,
        "language": { "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json" }
    });

    // 2. Envío del Formulario Principal (Insertar / Actualizar)
    $('#formArticulo').on('submit', function (e) {
        e.preventDefault();
        $.ajax({
            url: actionUrl,
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    Swal.fire('¡Operación Exitosa!', res.message, 'success');
                    resetearFormulario();
                    table.ajax.reload();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }
        });
    });

    // 3. Capturar click de Edición
    $(document).on('click', '.btnEditar', function () {
        let id = $(this).data('id');
        $.ajax({
            url: actionUrl,
            type: 'POST',
            data: { action: 'obtener', id_articulo: id },
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    $('#action').val('actualizar');
                    $('#id_articulo').val(res.data.id_articulo);
                    $('#codigo').val(res.data.codigo);
                    $('#nombre').val(res.data.nombre);
                    $('#categoria').val(res.data.categoria);
                    $('#descripcion').val(res.data.descripcion);
                    $('#unidad_medida').val(res.data.unidad_medida);
                    $('#stock_minimo').val(res.data.stock_minimo);
                    $('#precio_compra').val(res.data.precio_compra);
                    $('#precio_venta').val(res.data.precio_venta);
                    $('#proveedor').val(res.data.proveedor);
                    $('#fecha_ingreso').val(res.data.fecha_ingreso);
                    $('#fecha_vencimiento').val(res.data.fecha_vencimiento);
                    
                    // Ocultar campo stock inicial durante edición por protección histórica
                    $('#containerStockInicial').addClass('d-none');
                    
                    // Modificar estado estético del Formulario lateral
                    $('#formTitle').html('<i class="fas fa-edit"></i> Modificar Artículo');
                    $('#btnText').text('Guardar Cambios');
                    $('#btnCancelarEdicion').removeClass('d-none');
                    
                    $('html, body').animate({ scrollTop: 0 }, 'slow');
                } else {
                    Swal.fire('Error de Permisos', res.message, 'error');
                }
            }
        });
    });

    // Cancelar la edición de forma controlada
    $('#btnCancelarEdicion').on('click', function() {
        resetearFormulario();
    });

    function resetearFormulario() {
        $('#formArticulo')[0].reset();
        $('#action').val('guardar');
        $('#id_articulo').val('');
        $('#containerStockInicial').removeClass('d-none');
        $('#formTitle').html('<i class="fas fa-plus"></i> Registrar Artículo');
        $('#btnText').text('Registrar Artículo');
        $('#btnCancelarEdicion').addClass('d-none');
    }

    // 4. Capturar click para Eliminación del elemento
    $(document).on('click', '.btnEliminar', function () {
        let id = $(this).data('id');
        let nombre = $(this).data('nombre');
        
        Swal.fire({
            title: '¿Está seguro de eliminar?',
            text: `Se dará de baja el artículo "${nombre}" de la base de datos de forma permanente.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: actionUrl,
                    type: 'POST',
                    data: { action: 'eliminar', id_articulo: id },
                    dataType: 'json',
                    success: function (res) {
                        if (res.status === 'success') {
                            Swal.fire('Eliminado', res.message, 'success');
                            table.ajax.reload();
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    }
                });
            }
        });
    });

    // 5. Manejo de Modales de Ajuste de Stock (Entrada / Salida)
    $(document).on('click', '.btnEntrada, .btnSalida', function() {
        let isEntrada = $(this).hasClass('btnEntrada');
        let id = $(this).data('id');
        let nombre = $(this).data('nombre');
        let stock = $(this).data('stock');

        $('#formMovimientoStock')[0].reset();
        $('#stock_articulo_id').val(id);
        $('#stock_articulo_nombre').val(nombre);
        $('#stock_articulo_actual').val(stock);
        
        if (isEntrada) {
            $('#tipo_movimiento').val('ENTRADA');
            $('#modalStockTitle').html('<i class="fas fa-plus-square text-success"></i> Registrar Entrada de Stock');
            $('#btnSubmitStock').removeClass('btn-danger').addClass('btn-success').text('Procesar Entrada');
        } else {
            $('#tipo_movimiento').val('SALIDA');
            $('#modalStockTitle').html('<i class="fas fa-minus-square text-danger"></i> Registrar Salida de Stock');
            $('#btnSubmitStock').removeClass('btn-success').addClass('btn-danger').text('Procesar Salida');
        }
        
        $('#modalStock').modal('show');
    });

    // Envío del Ajuste de Stock vía AJAX
    $('#formMovimientoStock').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: actionUrl,
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    $('#modalStock').modal('hide');
                    Swal.fire('Stock Actualizado', res.message, 'success');
                    table.ajax.reload();
                } else {
                    Swal.fire('Atención', res.message, 'error');
                }
            }
        });
    });
});