$(document).ready(function () {
    const actionUrl = 'ajax/manual_action.php';
    let manualSeleccionadoId = 0;

    // DataTable Principal: Lista de Manuales
    const tablaManuales = $('#tablaManuales').DataTable({
        "ajax": {
            "url": actionUrl,
            "type": "POST",
            "data": { "action": "listar" },
            "dataSrc": ""
        },
        "columns": [
            { "data": "nombre_manual", "className": "text-bold" },
            { "data": "tipo_base" },
            { "data": "año_vigencia" },
            { 
                "data": "estado",
                "render": function(data) {
                    return parseInt(data) === 1 ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-danger">Inactivo</span>';
                }
            },
            {
                "data": null,
                "render": function(data, type, row) {
                    return `<button class="btn btn-success btn-sm btnGestionarPrecios" data-id="${row.id}" data-nombre="${row.nombre_manual}">
                                <i class="fas fa-dollar-sign"></i> Configurar Tarifas
                            </button>`;
                }
            }
        ],
        "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" }
    });

    // Envío del Formulario de Nuevo Manual
    $('#formManual').on('submit', function (e) {
        e.preventDefault();
        $.ajax({
            url: actionUrl,
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    Swal.fire('¡Creado!', res.message, 'success');
                    $('#modalManual').modal('hide');
                    $('#formManual')[0].reset();
                    tablaManuales.ajax.reload(null, false);
                }
            }
        });
    });

    // Transición de Vista: Ir a Configurar Precios
    $(document).on('click', '.btnGestionarPrecios', function () {
        manualSeleccionadoId = $(this).data('id');
        const nombre = $(this).data('nombre');

        $('#lblManualSeleccionado').text(nombre);
        $('#seccionManuales').addClass('d-none');
        $('#seccionTarifas').removeClass('d-none');

        // Inicializamos o recargamos la tabla de tarifas para este manual específico
        cargarTablaTarifas(manualSeleccionadoId);
    });

    // Volver al listado de manuales
    $('#btnVolverManuales').on('click', function() {
        $('#seccionTarifas').addClass('d-none');
        $('#seccionManuales').removeClass('d-none');
        tablaManuales.ajax.reload(null, false);
    });

    // Función constructora del detalle de precios
    function cargarTablaTarifas(manualId) {
        if ($.fn.DataTable.isDataTable('#tablaTarifas')) {
            $('#tablaTarifas').DataTable().destroy();
        }

        $('#tablaTarifas').DataTable({
            "ajax": {
                "url": actionUrl,
                "type": "POST",
                "data": { "action": "listar_tarifas", "manual_id": manualId },
                "dataSrc": ""
            },
            "columns": [
                { "data": "codigo_cups", "className": "text-bold text-navy" },
                { "data": "nombre_servicio" },
                {
                    "data": null,
                    "orderable": false,
                    "render": function (data, type, row) {
                        // Renderizamos un input numérico incrustado directamente en la celda
                        return `<div class="input-group input-group-sm">
                                    <div class="input-group-prepend"><span class="input-group-text">$</span></div>
                                    <input type="number" class="form-control txtInputPrecio" 
                                           data-servicio="${row.servicio_id}" 
                                           value="${parseFloat(row.valor).toFixed(2)}" step="0.01" min="0">
                                </div>`;
                    }
                }
            ],
            "pageLength": 10,
            "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" }
        });
    }

    // EVENTO RECOLECTOR AUTOMÁTICO (Foco perdido / Blur)
    // Escucha cuando el usuario cambia un precio y hace clic afuera o salta con Tabulador
    $(document).on('blur', '.txtInputPrecio', function () {
        const input = $(this);
        const servicioId = input.data('servicio');
        const nuevoPrecio = input.val();

        // Enviamos la actualización en background de forma silenciosa e inmediata
        $.ajax({
            url: actionUrl,
            type: 'POST',
            data: {
                action: 'guardar_tarifa',
                manual_id: manualSeleccionadoId,
                servicio_id: servicioId,
                valor: nuevoPrecio
            },
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    // Marcamos visualmente el input en verde temporalmente para confirmar guardado
                    input.addClass('is-valid');
                    setTimeout(() => input.removeClass('is-valid'), 1000);
                } else {
                    input.addClass('is-invalid');
                }
            }
        });
    });
});