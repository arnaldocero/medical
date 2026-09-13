$(document).ready(function () {
    const actionUrl = 'ajax/servicio_action.php';

    // Inicializar DataTables enfocada en CUPS
    const tabla = $('#tablaServicios').DataTable({
        "ajax": {
            "url": actionUrl,
            "type": "POST",
            "data": { "action": "listar" },
            "dataSrc": ""
        },
        "columns": [
            { "data": "codigo_cups", "className": "text-bold" },
            { "data": "nombre_servicio" },
            { "data": "created_at" },
            {
                "data": "estado",
                "render": function (data) {
                    return parseInt(data) === 1 
                        ? `<span class="badge badge-success">Activo</span>` 
                        : `<span class="badge badge-danger">Inactivo</span>`;
                }
            },
            {
                "data": null,
                "orderable": false,
                "render": function (data, type, row) {
                    const esActivo = parseInt(row.estado) === 1;
                    const btnEstado = esActivo
                        ? `<button class="btn btn-secondary btn-sm btnStatus" data-id="${row.id}" data-estado="0" title="Inactivar"><i class="fas fa-toggle-on"></i></button>`
                        : `<button class="btn btn-success btn-sm btnStatus" data-id="${row.id}" data-estado="1" title="Activar"><i class="fas fa-toggle-off"></i></button>`;

                    return `
                        <button class="btn btn-warning btn-sm btnEditar" data-id="${row.id}" data-cups="${row.codigo_cups}" data-nombre="${row.nombre_servicio}"><i class="fas fa-edit"></i></button>
                        ${btnEstado}
                        <button class="btn btn-danger btn-sm btnEliminar" data-id="${row.id}"><i class="fas fa-trash"></i></button>
                    `;
                }
            }
        ],
        "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" }
    });

    // Resetear al abrir para nuevo registro
    $('#btnNuevoServicio').on('click', function() {
        $('#formServicio')[0].reset();
        $('#action').val('registrar');
        $('#servicio_id').val('');
        $('#modalTitle').html('<i class="fas fa-plus"></i> Nuevo Servicio CUPS');
    });

    // Cargar datos en el modal de Edición
    $(document).on('click', '.btnEditar', function () {
        $('#action').val('editar');
        $('#servicio_id').val($(this).data('id'));
        $('#codigo_cups').val($(this).data('cups'));
        $('#nombre_servicio').val($(this).data('nombre'));
        $('#modalTitle').html('<i class="fas fa-edit"></i> Modificar Servicio CUPS');
        $('#modalServicio').modal('show');
    });

    // Envío del formulario (Crear/Editar)
    $('#formServicio').on('submit', function (e) {
        e.preventDefault();
        $.ajax({
            url: actionUrl,
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    Swal.fire('¡Procesado!', res.message, 'success');
                    $('#modalServicio').modal('hide');
                    tabla.ajax.reload(null, false);
                } else {
                    Swal.fire('Advertencia', res.message, 'warning');
                }
            }
        });
    });

    // Intercambiar Estado (Activar/Inactivar)
    $(document).on('click', '.btnStatus', function () {
        const id = $(this).data('id');
        const nuevo = $(this).data('estado');

        $.ajax({
            url: actionUrl,
            type: 'POST',
            data: { action: 'estado', servicio_id: id, nuevo_estado: nuevo },
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    tabla.ajax.reload(null, false);
                }
            }
        });
    });

    // Eliminar Servicio
    $(document).on('click', '.btnEliminar', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: '¿Eliminar este servicio?',
            text: "Se borrará permanentemente si no tiene vínculos activos.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, borrar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: actionUrl,
                    type: 'POST',
                    data: { action: 'eliminar', servicio_id: id },
                    dataType: 'json',
                    success: function (res) {
                        if (res.status === 'success') {
                            Swal.fire('Eliminado', res.message, 'success');
                            tabla.ajax.reload(null, false);
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    }
                });
            }
        });
    });
});