$(document).ready(function () {
    const ajaxUrl = 'ajax/roles_action.php';

    // 1. Inicializar DataTable
    const tablaRoles = $('#tablaRoles').DataTable({
        "ajax": {
            "url": ajaxUrl,
            "type": "POST",
            "data": { "action": "listar" },
            "dataSrc": ""
        },
        "columns": [
            { "data": "id", "className": "text-center font-weight-bold" },
            { 
                "data": "nombre_rol",
                "render": function(data) {
                    return `<span class="badge badge-primary px-2 py-1">${data}</span>`;
                }
            },
            { 
                "data": "descripcion",
                "render": function(data) {
                    return data ? data : '<span class="text-muted font-italic">Sin descripción</span>';
                }
            },
            { 
                "data": "total_usuarios",
                "className": "text-center",
                "render": function(data) {
                    return `<span class="badge badge-secondary">${data} usuario(s)</span>`;
                }
            },
            { 
                "data": "total_permisos",
                "className": "text-center",
                "render": function(data) {
                    return `<span class="badge badge-info">${data} permiso(s)</span>`;
                }
            },
            {
                "data": null,
                "orderable": false,
                "className": "text-center",
                "render": function (data, type, row) {
                    const btnEliminar = row.id == 1 
                        ? `<button class="btn btn-secondary btn-sm shadow-sm" disabled title="Rol protegido"><i class="fas fa-lock"></i></button>`
                        : `<button class="btn btn-danger btn-sm btnEliminarRol shadow-sm" data-id="${row.id}" data-nombre="${row.nombre_rol}" title="Eliminar Rol"><i class="fas fa-trash"></i></button>`;

                    return `
                        <button class="btn btn-warning btn-sm btnEditarRol shadow-sm" data-id="${row.id}" title="Editar Rol y Permisos">
                            <i class="fas fa-edit"></i>
                        </button>
                        ${btnEliminar}
                    `;
                }
            }
        ],
        "language": {
            "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
        }
    });

    // 2. Abrir modal para Crear Nuevo Rol
    $('#btnNuevoRol').on('click', function () {
        $('#formRol')[0].reset();
        $('#idRol').val('');
        $('.chk-permiso').prop('checked', false);

        $('#modalRolTitle').html('<i class="fas fa-shield-alt"></i> Crear Nuevo Rol');
        $('#modalRol .modal-header').removeClass('bg-warning').addClass('bg-primary');
        $('#btnGuardarRol').removeClass('btn-warning').addClass('btn-primary').text('Guardar Rol');

        $('#modalRol').modal('show');
    });

    // 3. Abrir modal para Editar Rol y marcar sus permisos asignados
    $('#tablaRoles').on('click', '.btnEditarRol', function () {
        const id = $(this).data('id');

        $('#formRol')[0].reset();
        $('.chk-permiso').prop('checked', false);

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: { action: 'obtener', id: id },
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    $('#idRol').val(res.rol.id);
                    $('#nombre_rol').val(res.rol.nombre_rol);
                    $('#descripcion').val(res.rol.descripcion);

                    // Marcar checkboxes correspondientes
                    if (res.permisos && res.permisos.length > 0) {
                        res.permisos.forEach(permisoId => {
                            $(`#perm_${permisoId}`).prop('checked', true);
                        });
                    }

                    $('#modalRolTitle').html(`<i class="fas fa-user-edit"></i> Modificar Rol: ${res.rol.nombre_rol}`);
                    $('#modalRol .modal-header').removeClass('bg-primary').addClass('bg-warning');
                    $('#btnGuardarRol').removeClass('btn-primary').addClass('btn-warning').text('Guardar Cambios');

                    $('#modalRol').modal('show');
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function () {
                Swal.fire('Error', 'No se pudieron cargar los datos del rol.', 'error');
            }
        });
    });

    // 4. Envío del Formulario (Guardar / Actualizar)
    $('#formRol').on('submit', function (e) {
        e.preventDefault();

        const btn = $('#btnGuardarRol');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Guardando...');

        const formData = $(this).serialize() + '&action=guardar';

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    Swal.fire('¡Éxito!', res.message, 'success');
                    $('#modalRol').modal('hide');
                    tablaRoles.ajax.reload(null, false);
                } else {
                    Swal.fire('Atención', res.message, 'warning');
                }
                btn.prop('disabled', false).text($('#idRol').val() ? 'Guardar Cambios' : 'Guardar Rol');
            },
            error: function () {
                Swal.fire('Error', 'Ocurrió un fallo en el servidor.', 'error');
                btn.prop('disabled', false).text($('#idRol').val() ? 'Guardar Cambios' : 'Guardar Rol');
            }
        });
    });

    // 5. Eliminar Rol
    $('#tablaRoles').on('click', '.btnEliminarRol', function () {
        const id = $(this).data('id');
        const nombre = $(this).data('nombre');

        Swal.fire({
            title: `¿Eliminar el rol "${nombre}"?`,
            text: "Esta acción removerá el rol y sus asociaciones de permisos.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: { action: 'eliminar', id: id },
                    dataType: 'json',
                    success: function (res) {
                        if (res.status === 'success') {
                            Swal.fire('Eliminado', res.message, 'success');
                            tablaRoles.ajax.reload(null, false);
                        } else {
                            Swal.fire('Atención', res.message, 'warning');
                        }
                    },
                    error: function () {
                        Swal.fire('Error', 'No fue posible completar la eliminación.', 'error');
                    }
                });
            }
        });
    });

    // 6. Accesos directos para checkboxes
    $('#btnMarcarTodos').on('click', function () {
        $('.chk-permiso').prop('checked', true);
    });

    $('#btnDesmarcarTodos').on('click', function () {
        $('.chk-permiso').prop('checked', false);
    });

    $('.btn-check-grupo').on('click', function () {
        const targetClass = $(this).data('target');
        const checkboxes = $('.' + targetClass);
        const algunoDesmarcado = checkboxes.filter(':not(:checked)').length > 0;
        checkboxes.prop('checked', algunoDesmarcado);
    });
});