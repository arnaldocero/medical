$(document).ready(function () {
    const actionUrl = 'ajax/facturacion_action.php';
    let itemsFactura = []; // Array global para acumular los procedimientos en memoria

    // Inicialización segura de Select2 con soporte para Bootstrap 4
    if ($.fn.select2) {
        $('.select2').select2({
            theme: 'bootstrap4',
            placeholder: '-- Seleccione una opción --',
            allowClear: true
        });
    } else {
        console.error("Error crítico: La librería Select2 no se cargó en el DOM antes de este script.");
    }

    // Evento: Al cambiar el paciente, detectamos automáticamente su EPS y Contrato
    $('#paciente_id').on('change', function () {
        const pId = $(this).val();
        if (!pId) {
            limpiarContrato();
            return;
        }

        $.ajax({
            url: actionUrl,
            type: 'POST',
            data: { action: 'obtener_cobertura_paciente', paciente_id: pId },
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    $('#contrato_id').val(res.data.contrato_id);
                    $('#manual_id').val(res.data.manual_id);
                    $('#txtContratoInfo').val(`${res.data.nombre_eps} (${res.data.nombre_manual})`);
                } else {
                    $('#contrato_id').val('');
                    $('#manual_id').val('');
                    $('#txtContratoInfo').val('Particular (Venta Directa)');
                }
                itemsFactura = [];
                renderizarTabla();
            },
            error: function (xhr, status, error) {
                console.error("Error obteniendo cobertura:", error);
            }
        });
    });

    // Evento: Botón Añadir Ítem
    $('#btnAgregarItem').on('click', function () {
        const sId = $('#servicio_id').val();
        const sText = $("#servicio_id option:selected").text();
        const cantidad = parseInt($('#txtCantidad').val());
        const manualId = $('#manual_id').val();

        if (!sId) { 
            Swal.fire('Atención', 'Seleccione un servicio o código CUPS.', 'warning'); 
            return; 
        }
        if (isNaN(cantidad) || cantidad <= 0) {
            Swal.fire('Atención', 'Ingrese una cantidad válida mayor a 0.', 'warning');
            return;
        }

        const existe = itemsFactura.find(i => i.servicio_id === sId);
        if (existe) {
            existe.cantidad += cantidad;
            renderizarTabla();
            $('#servicio_id').val('').trigger('change');
            $('#txtCantidad').val('1');
            return;
        }

        $.ajax({
            url: actionUrl,
            type: 'POST',
            data: { action: 'obtener_precio_servicio', servicio_id: sId, manual_id: manualId },
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    itemsFactura.push({
                        servicio_id: sId,
                        descripcion: sText,
                        cantidad: cantidad,
                        precio: parseFloat(res.precio)
                    });
                    renderizarTabla();
                    
                    $('#servicio_id').val('').trigger('change');
                    $('#txtCantidad').val('1');
                } else {
                    Swal.fire('Error', 'No se pudo recuperar el precio del servicio.', 'error');
                }
            }
        });
    });

    // Eliminar ítem de la grilla
    $(document).on('click', '.btnQuitar', function () {
        const index = $(this).data('index');
        itemsFactura.splice(index, 1);
        renderizarTabla();
    });

    function renderizarTabla() {
        const tbody = $('#tablaConceptos tbody');
        tbody.empty();
        let granTotal = 0;

        if (itemsFactura.length === 0) {
            tbody.append('<tr><td colspan="5" class="text-center text-muted">Ningún procedimiento añadido a esta orden</td></tr>');
            $('#lblTotalFactura').text('$0.00');
            return;
        }

        itemsFactura.forEach((item, index) => {
            const itemTotal = item.cantidad * item.precio;
            granTotal += itemTotal;

            tbody.append(`
                <tr>
                    <td>${item.descripcion}</td>
                    <td class="text-center">${item.cantidad}</td>
                    <td>$ ${item.precio.toLocaleString('co-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                    <td class="text-bold text-navy">$ ${itemTotal.toLocaleString('co-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-danger btn-sm btnQuitar" data-index="${index}">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </td>
                </tr>
            `);
        });

        $('#lblTotalFactura').text(`$ ${granTotal.toLocaleString('co-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);
    }

    function limpiarContrato() {
        $('#contrato_id').val('');
        $('#manual_id').val('');
        $('#txtContratoInfo').val('Particular (Venta Directa)');
        itemsFactura = [];
        renderizarTabla();
    }

    // Evento Final: Enviar Factura a Guardar en Base de Datos
    $('#btnProcesarFactura').on('click', function () {
        const pId = $('#paciente_id').val();
        const resId = $('#dian_resolucion_id').val(); // NUEVO: Capturar resolución técnica DIAN

        if (!resId) { 
            Swal.fire('Bloqueo Legal', 'No existe resolución DIAN activa. No se puede emitir el documento.', 'error'); 
            return; 
        }
        if (!pId) { 
            Swal.fire('Error', 'Debe seleccionar un paciente.', 'error'); 
            return; 
        }
        if (itemsFactura.length === 0) { 
            Swal.fire('Error', 'La factura no tiene ningún concepto asignado.', 'error'); 
            return; 
        }

        Swal.fire({
            title: '¿Confirmar Factura?',
            text: "Se registrará la transacción y consumirá el consecutivo legal de la DIAN.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, guardar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: actionUrl,
                    type: 'POST',
                    data: {
                        action: 'guardar_factura',
                        paciente_id: pId,
                        dian_resolucion_id: resId, // NUEVO: Pasado al backend PHP
                        contrato_id: $('#contrato_id').val(),
                        metodo_pago: $('#metodo_pago').val(),
                        detalles: itemsFactura
                    },
                    dataType: 'json',
                    success: function (res) {
                        if (res.status === 'success') {
                            Swal.fire('¡Factura Emitida!', res.message, 'success').then(() => {
                                // Forzar recarga limpia para actualizar el contador visual de la DIAN
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error al procesar', res.message, 'warning');
                        }
                    },
                    error: function() {
                        Swal.fire('Error Crítico', 'No se pudo comunicar con el servidor.', 'error');
                    }
                });
            }
        });
    });

    // Modal Detalles Auditoría
    $(document).on('click', '.btnVerDetalles', function() {
        const facturaId = $(this).data('id');
        
        $('#modalFacturaCuerpo').html(`
            <div class="text-center p-4">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-2">Cargando auditoría de conceptos...</p>
            </div>
        `);
        
        $('#modalDetalleFactura').modal('show');
        
        $.ajax({
            url: 'ajax/facturacion_action.php',
            type: 'POST',
            data: { action: 'ver_detalle_linea', factura_id: facturaId },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    $('#modalFacturaCuerpo').html(res.html);
                } else {
                    $('#modalFacturaCuerpo').html(`<div class="alert alert-warning">${res.message}</div>`);
                }
            },
            error: function(xhr, status, error) {
                console.error("Error en servidor:", xhr.responseText);
                $('#modalFacturaCuerpo').html('<div class="alert alert-danger">Error al procesar los detalles en el servidor.</div>');
            }
        });
    });
});