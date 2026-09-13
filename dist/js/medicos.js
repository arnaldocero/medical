$(document).ready(function () {
    const ajaxUrl = 'ajax/medicos_action.php';

    // 1. Inicialización de DataTable con mapeo de campos reales
    const tabla = $('#tablaMedicos').DataTable({
        "ajax": {
            "url": ajaxUrl,
            "type": "POST",
            "data": { "action": "listar" },
            "dataSrc": "" 
        },
        "columns": [
            { "data": "medico_nombre" },
            { "data": "medico_email" },
            { "data": "usuario" },
            { "data": "licencia_medica" },
            { "data": "especialidad_nombre" },
            {
                "data": null,
                "orderable": false,
                "render": function (data, type, row) {
                    return `
                        <button class="btn btn-warning btn-sm btnEditar shadow-sm" 
                            data-id="${row.usuario_id}" 
                            data-nombre="${row.medico_nombre}"
                            data-email="${row.medico_email}"
                            data-user="${row.usuario}"
                            data-rol="${row.rol_id}"
                            data-licencia="${row.licencia_medica}"
                            data-especialidad="${row.especialidad_id}"
                            data-universidad="${row.universidad_egreso || ''}"
                            data-experiencia="${row.anos_experiencia || 0}">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-danger btn-sm btnEliminar shadow-sm" data-id="${row.usuario_id}">
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
    $('#btnNuevoMedico').on('click', function() {
        // CORREGIDO: Validación previa para evitar caídas de script si se comparte con pacientes.js
        if ($('#formMedico').length) {
            $('#formMedico')[0].reset();
        }
        $('#action').val('registrar');
        $('#usuario_id').val('');
        $('#modalTitle').html('<i class="fas fa-user-md"></i> Registrar Médico');
        $('#password').attr('required', true);
        $('#passHelp').addClass('d-none');
    });

    // 3. Carga de atributos en el modo Edición
    $('#tablaMedicos').on('click', '.btnEditar', function () {
        const id = $(this).data('id');
        
        $('#action').val('editar');
        $('#usuario_id').val(id);
        $('#nombre').val($(this).data('nombre'));
        $('#email').val($(this).data('email'));
        $('#usuario').val($(this).data('user'));
        $('#rol_id').val($(this).data('rol'));
        $('#licencia_medica').val($(this).data('licencia'));
        $('#especialidad_id').val($(this).data('especialidad'));
        $('#universidad_egreso').val($(this).data('universidad'));
        $('#anos_experiencia').val($(this).data('experiencia'));
        
        // Contraseña opcional en edición
        $('#password').attr('required', false).val('');
        $('#passHelp').removeClass('d-none');
        $('#modalTitle').html('<i class="fas fa-user-edit"></i> Modificar Médico');
        
        // Despliega el modal de manera explícita
        $('#modalMedico').modal('show');
    });

    // 4. Envío de Formulario unificado vía Ajax
    $('#formMedico').on('submit', function (e) {
        e.preventDefault();

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    Swal.fire('¡Éxito!', res.message, 'success');
                    $('#modalMedico').modal('hide');
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

    // 5. Gestión de bajas de personal
    $('#tablaMedicos').on('click', '.btnEliminar', function () {
        const id = $(this).data('id');

        Swal.fire({
            title: '¿Está seguro de eliminar al médico?',
            text: "Esta acción borrará la cuenta de usuario y su registro médico permanente.",
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
                    data: { action: 'eliminar', usuario_id: id },
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
                        Swal.fire('Error', 'Error crítico al procesar la baja.', 'error');
                        console.error(xhr.responseText);
                    }
                });
            }
        });
    });
});