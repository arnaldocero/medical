$(document).ready(function () {
    const ajaxUrl = 'ajax/eps_action.php';

    // 1. Inicialización de DataTables mapeado a los campos de la tabla eps
    const tabla = $('#tablaEPS').DataTable({
        "ajax": {
            "url": ajaxUrl,
            "type": "POST",
            "data": { "action": "listar" },
            "dataSrc": "" 
        },
        "columns": [
            { "data": "codigo_minsalud" },
            { "data": "nombre" },
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
                        ? `<button class="btn btn-secondary btn-sm btnToggleEstado" data-id="${row.id}" data-nuevo="0" title="Inactivar"><i class="fas fa-toggle-on"></i></button>`
                        : `<button class="btn btn-success btn-sm btnToggleEstado" data-id="${row.id}" data-nuevo="1" title="Activar"><i class="fas fa-toggle-off"></i></button>`;

                    return `
                        <button class="btn btn-warning btn-sm btnEditar" 
                            data-id="${row.id}" 
                            data-nombre="${row.nombre}"
                            data-codigo="${row.codigo_minsalud}">
                            <i class="fas fa-edit"></i>
                        </button>
                        ${btnEstado}
                        <button class="btn btn-danger btn-sm btnEliminar" data-id="${row.id}">
                            <i class="fas fa-trash"></i>
                        </button>
                    `;
                }
            }
        ],
        "language": {
            "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
        }
    });

    // 2. Control de estado para ventana modal en modo Registro
    $('#btnNuevaEPS').on('click', function() {
        $('#formEPS')[0].reset();
        $('#action').val('registrar');
        $('#eps_id').val('');
        $('#modalTitle').html('<i class="fas fa-hospital-alt"></i> Registrar EPS');
    });

    // 3. Extracción de atributos para modo Edición
    $(document).on('click', '.btnEditar', function () {
        $('#action').val('editar');
        $('#eps_id').val($(this).data('id'));
        $('#nombre').val($(this).data('nombre'));
        $('#codigo_minsalud').val($(this).data('codigo'));
        $('#modalTitle').html('<i class="fas fa-edit"></i> Modificar EPS');
        $('#modalEPS').modal('show');
    });

    // 4. Envío de Formulario unificado (Crear / Actualizar)
    $('#formEPS').on('submit', function (e) {
        e.preventDefault();

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    Swal.fire('¡Éxito!', res.message, 'success');
                    $('#modalEPS').modal('hide');
                    tabla.ajax.reload(null, false); 
                } else {
                    Swal.fire('Atención', res.message, 'warning');
                }
            },
            error: function (xhr) {
                Swal.fire('Error', 'No se pudo procesar la solicitud en el servidor.', 'error');
                console.error(xhr.responseText); 
            }
        });
    });

    // 5. Cambio de Estado dinámico (Activar / Inactivar)
    $(document).on('click', '.btnToggleEstado', function () {
        const id = $(this).data('id');
        const nuevoEstado = $(this).data('nuevo');
        const texto = nuevoEstado === 1 ? 'activar' : 'inactivar';

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: { action: 'estado', eps_id: id, nuevo_estado: nuevoEstado },
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    Swal.fire('Estado Actualizado', res.message, 'success');
                    tabla.ajax.reload(null, false);
                }
            }
        });
    });

    // 6. Baja Permanente de Registro
    $(document).on('click', '.btnEliminar', function () {
        const id = $(this).data('id');

        Swal.fire({
            title: '¿Está seguro de eliminar esta EPS?',
            text: "Esta acción borrará permanentemente el registro si no tiene dependencias.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: { action: 'eliminar', eps_id: id },
                    dataType: 'json',
                    success: function (res) {
                        if (res.status === 'success') {
                            Swal.fire('Eliminado', res.message, 'success');
                            tabla.ajax.reload(null, false);
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    },
                    error: function(xhr) {
                        Swal.fire('Error', 'Error crítico del sistema al eliminar.', 'error');
                    }
                });
            }
        });
    });
});