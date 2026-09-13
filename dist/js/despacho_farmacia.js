$(document).ready(function() {

    // 1. Buscar la fórmula médica
    $('#formBuscarFormula').on('submit', function(e) {
        e.preventDefault();
        let busqueda = $('#inputBusqueda').val();

        $.ajax({
            url: 'ajax/farmacia_action.php?action=buscar_formula',
            method: 'POST',
            data: { busqueda: busqueda },
            dataType: 'json',
            success: function(response) {
                if(response.status === 'success') {
                    // Mapear datos generales de la fórmula
                    $('#hdnFormulaId').val(response.formula.formula_id);
                    $('#txtPaciente').text(response.formula.paciente_nombre);
                    $('#txtDocumento').text(response.formula.documento_identidad);
                    $('#txtDiagnostico').text(response.formula.diagnostico_cie10);
                    $('#txtFecha').text(response.formula.fecha_prescripcion);
                    $('#txtObservaciones').text(response.formula.observaciones || 'Sin observaciones.');

                    // Mapear tabla de medicamentos
                    let htmlRows = '';
                    let stockInsuficiente = false;

                    response.medicamentos.forEach(function(item) {
                        // Validamos si hay stock suficiente en tiempo real
                        let badgeStock = '';
                        if (parseInt(item.stock_actual) >= parseInt(item.cantidad_solicitada)) {
                            badgeStock = `<span class="badge badge-success">Disponible (${item.stock_actual} u.)</span>`;
                        } else {
                            badgeStock = `<span class="badge badge-danger">Insuficiente (${item.stock_actual} u.)</span>`;
                            stockInsuficiente = true;
                        }

                        htmlRows += `
                            <tr>
                                <td><strong>${item.nombre_comercial}</strong> <br><small class="text-muted">${item.presentacion}</small></td>
                                <td class="text-center"><span class="badge badge-primary style="font-size:14px;">${item.cantidad_solicitada}</span></td>
                                <td>${item.dosificacion}</td>
                                <td>${badgeStock}</td>
                            </tr>
                        `;
                    });

                    $('#tbodyMedicamentos').html(htmlRows);
                    
                    // Si no hay stock, deshabilitamos el botón de despacho para prevenir errores catastróficos
                    if(stockInsuficiente) {
                        $('#btnEntregarFormula').prop('disabled', true).addClass('btn-secondary').removeClass('btn-success');
                        toastr.error('Uno o más medicamentos no cuentan con stock suficiente para cubrir la receta.');
                    } else {
                        $('#btnEntregarFormula').prop('disabled', false).addClass('btn-success').removeClass('btn-secondary');
                    }

                    $('#panelDetalleFormula').removeClass('d-none');
                } else {
                    toastr.error(response.message);
                    $('#panelDetalleFormula').addClass('d-none');
                }
            }
        });
    });

    // 2. Procesar el despacho definitivo
    $('#btnEntregarFormula').on('click', function() {
        let formulaId = $('#hdnFormulaId').val();

        if(!confirm('¿Estás seguro de que deseas confirmar la entrega de estos medicamentos? Esta acción descontará el inventario.')) return;

        $.ajax({
            url: 'ajax/farmacia_action.php?action=despachar_formula',
            method: 'POST',
            data: { formula_id: formulaId },
            dataType: 'json',
            success: function(response) {
                if(response.status === 'success') {
                    toastr.success(response.message);
                    $('#panelDetalleFormula').addClass('d-none');
                    $('#inputBusqueda').val('').focus();
                } else {
                    toastr.error(response.message);
                }
            }
        });
    });
});