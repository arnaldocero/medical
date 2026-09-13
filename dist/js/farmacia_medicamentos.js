$(document).ready(function () {
    const actionUrl = 'ajax/medicamento_action.php';
    
    let table = $('#tablaMedicamentos').DataTable({
        "ajax": {
            "url": actionUrl,
            "type": "POST",
            "data": { action: 'listar' }
        },
        "columns": [
            { "data": "nombre_generico", "render": d => `<b>${d}</b>` },
            { "data": "presentacion" },
            { "data": "nombre_comercial", "render": d => d ? d : '<span class="text-muted">Genérico</span>' },
            { "data": "stock_actual", "render": d => `<span class="badge badge-light px-3 py-2 border shadow-sm">${d}</span>` },
            {
                "data": null,
                "render": function (data) {
                    let actual = parseInt(data.stock_actual);
                    let minimo = parseInt(data.stock_minimo);
                    
                    if (actual === 0) {
                        return '<span class="badge badge-danger"><i class="fas fa-times"></i> Agotado</span>';
                    } else if (actual <= minimo) {
                        return '<span class="badge badge-warning"><i class="fas fa-exclamation-triangle"></i> Stock Crítico</span>';
                    }
                    return '<span class="badge badge-success"><i class="fas fa-check"></i> Disponible</span>';
                }
            }
        ],
        "responsive": true,
        "language": { "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json" }
    });

    $('#formMedicamento').on('submit', function (e) {
        e.preventDefault();
        $.ajax({
            url: actionUrl,
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    Swal.fire('Guardado', res.message, 'success');
                    $('#formMedicamento')[0].reset();
                    table.ajax.reload();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }
        });
    });
});