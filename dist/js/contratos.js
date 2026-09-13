$(document).ready(function () {
    const actionUrl = 'ajax/contrato_action.php';

    // Inicializar DataTables de Contratos con cruce de información completo
    const tabla = $('#tablaContratos').DataTable({
        "ajax": {
            "url": actionUrl,
            "type": "POST",
            "data": { "action": "listar" },
            "dataSrc": ""
        },
        "columns": [
            { "data": "numero_contrato", "className": "text-bold text-navy" },
            { "data": "nombre_eps" },
            { "data": "nombre_manual", "className": "text-muted" },
            { "data": "fecha_inicio" },
            { "data": "fecha_fin" },
            {
                "data": "estado",
                "render": function (data) {
                    return parseInt(data) === 1 
                        ? `<span class="badge badge-success"><i class="fas fa-check-circle"></i> Vigente</span>` 
                        : `<span class="badge badge-danger"><i class="fas fa-times-circle"></i> Finalizado</span>`;
                }
            },
            {
                "data": null,
                "orderable": false,
                "render": function (data, type, row) {
                    const esActivo = parseInt(row.estado) === 1;
                    const btnEstado = esActivo
                        ? `<button class="btn btn-secondary btn-sm btnStatus" data-id="${row.id}" data-estado="0" title="Suspender Contrato"><i class="fas fa-lock"></i></button>`
                        : `<button class="btn btn-success btn-sm btnStatus" data-id="${row.id}" data-estado="1" title="Reactivar Contrato"><i class="fas fa-lock-open"></i></button>`;

                    return `<div class="text-center">${btnEstado}</div>`;
                }
            }
        ],
        "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" }
    });

    // Envío del Formulario vía AJAX
    $('#formContrato').on('submit', function (e) {
        e.preventDefault();
        $.ajax({
            url: actionUrl,
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    Swal.fire('¡Contrato Registrado!', res.message, 'success');
                    $('#modalContrato').modal('hide');
                    $('#formContrato')[0].reset();
                    tabla.ajax.reload(null, false);
                } else {
                    Swal.fire('Atención', res.message, 'warning');
                }
            }
        });
    });

    // Cambiar Estado del Contrato de Forma Inmediata
    $(document).on('click', '.btnStatus', function () {
        const id = $(this).data('id');
        const nuevo = $(this).data('estado');

        $.ajax({
            url: actionUrl,
            type: 'POST',
            data: { action: 'estado', contrato_id: id, nuevo_estado: nuevo },
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    tabla.ajax.reload(null, false);
                }
            }
        });
    });
});