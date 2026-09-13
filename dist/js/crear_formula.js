$(document).ready(function () {
    // Inicializar Select2 estándar
    $('.select2').select2({ theme: 'bootstrap4' });

    // Configurar buscador en vivo de fármacos
    $('#buscadorMedicamento').select2({
        theme: 'bootstrap4',
        placeholder: 'Escribe el nombre del medicamento...',
        minimumInputLength: 2,
        ajax: {
            url: 'ajax/formula_action.php',
            dataType: 'json',
            data: function (params) {
                return { action: 'buscar_medicamentos', q: params.term };
            },
            processResults: function (data) {
                return { results: data.results };
            }
        }
    });

    let renglonIndex = 0;

    // Agregar renglones dinámicos a la tabla
    $('#btnAgregarMedicamento').on('click', function () {
        let medData = $('#buscadorMedicamento').select2('data')[0];
        let cantidad = parseInt($('#tempCantidad').val());

        if (!medData || cantidad <= 0) {
            Swal.fire('Validación', 'Seleccione un medicamento y defina una cantidad válida.', 'warning');
            return;
        }

        $('#filaVacia').remove();

        let nuevaFila = `
            <tr id="renglon_${renglonIndex}">
                <td>
                    <input type="hidden" name="med_items[${renglonIndex}][medicamento_id]" value="${medData.id}">
                    <strong>${medData.text}</strong>
                </td>
                <td>
                    <input type="number" name="med_items[${renglonIndex}][cantidad]" class="form-control form-control-sm" value="${cantidad}" min="1" required>
                </td>
                <td>
                    <input type="text" name="med_items[${renglonIndex}][dosificacion]" class="form-control form-control-sm" placeholder="Ej: 1 tableta cada 12 horas por 3 días" required>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-danger btn-sm btn-eliminar-item" data-id="${renglonIndex}">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;

        $('#cuerpoFormula').append(nuevaFila);
        renglonIndex++;

        // Resetear controles superiores
        $('#buscadorMedicamento').val(null).trigger('change');
        $('#tempCantidad').val(1);
    });

    // Evento para remover ítems individuales
    $(document).on('click', '.btn-eliminar-item', function () {
        let id = $(this).data('id');
        $(`#renglon_${id}`).remove();

        if ($('#cuerpoFormula tr').length === 0) {
            $('#cuerpoFormula').html('<tr id="filaVacia"><td colspan="4" class="text-center text-muted">No se han recetado medicamentos aún.</td></tr>');
        }
    });

    // Guardar vía AJAX
    $('#formNuevaFormula').on('submit', function (e) {
        e.preventDefault();

        if ($('#filaVacia').length > 0) {
            Swal.fire('Atención', 'No puede firmar una fórmula sin registrar medicamentos.', 'warning');
            return;
        }

        $.ajax({
            url: 'ajax/formula_action.php',
            type: 'POST',
            data: $(this).serialize() + '&action=guardar_formula',
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    Swal.fire('Completado', res.message, 'success').then(() => {
                        $('#formNuevaFormula')[0].reset();
                        $('#selectPaciente').val(null).trigger('change');
                        $('#selectDiagnostico').val(null).trigger('change');
                        $('#cuerpoFormula').html('<tr id="filaVacia"><td colspan="4" class="text-center text-muted">No se han recetado medicamentos aún.</td></tr>');

                    });
                } else {
                    Swal.fire('Error Operacional', res.message, 'error');
                }
            }
        });
    });
    // Configurar el buscador dinámico de Diagnósticos CIE-10
    $('#selectDiagnostico').select2({
        theme: 'bootstrap4',
        placeholder: 'Escribe el código o enfermedad (Ej: J00 o Gripe)...',
        minimumInputLength: 2, // Empieza a buscar a partir de 2 caracteres escritos
        allowClear: true,
        ajax: {
            url: 'ajax/formula_action.php',
            dataType: 'json',
            delay: 250, // Espera 250ms antes de lanzar la petición para no saturar el servidor
            data: function (params) {
                return { 
                    action: 'buscar_diagnosticos', 
                    q: params.term 
                };
            },
            processResults: function (data) {
                return { results: data.results };
            }
        }
    });
});