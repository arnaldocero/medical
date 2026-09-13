$(document).ready(function() {
    
    // --- 0. INICIALIZAR DATATABLE ---
    if ($.fn.DataTable.isDataTable('#tablaUsuarios')) {
        $('#tablaUsuarios').DataTable().destroy();
    }
    
    $('#tablaUsuarios').DataTable({
        "responsive": true,
        "autoWidth": false,
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.20/i18n/Spanish.json"
        }
    });

    // --- 1. FUNCIÓN PARA ABRIR MODAL EN MODO EDICIÓN ---
    $(document).on('click', '.btnEditarUsuario', function() {
        // Captura de datos básicos del botón
        const id = $(this).data('id');
        const nombre = $(this).data('nombre');
        const usuario = $(this).data('usuario');
        const email = $(this).data('email');
        const rol = $(this).data('rol');
        
        // Datos específicos del perfil
        const tipoDoc = $(this).data('tipodoc'); 
        const documento = $(this).data('doc');     
        const telefono = $(this).data('tel');       
        const direccion = $(this).data('dir');     
        const cargo = $(this).data('cargo');       
        const fechaNacimiento = $(this).data('fnac'); 
        const genero = $(this).data('genero'); // Traerá 'Masculino', 'Femenino' u 'Otro'
        $('select[name="genero"]').val(genero); // Seleccionará la opción correcta en el moda  
    
        // Ajustamos la apariencia del modal para modo Edición
        $('#modalUsuario .modal-title').text('Editar Usuario');
        $('#modalUsuario .modal-header').removeClass('bg-primary').addClass('bg-warning');
        
        // Inyectamos los datos básicos en el formulario
        $('input[name="nombre"]').val(nombre);
        $('input[name="usuario"]').val(usuario);
        $('input[name="email"]').val(email);
        $('select[name="rol_id"]').val(rol);
        
        // Inyectamos los datos de perfil en el formulario
        $('select[name="tipo_doc"]').val(tipoDoc);
        $('input[name="documento"]').val(documento);
        $('input[name="telefono"]').val(telefono);
        $('input[name="direccion"]').val(direccion);
        $('input[name="cargo"]').val(cargo);
        $('input[name="fecha_nacimiento"]').val(fechaNacimiento);
        $('select[name="genero"]').val(genero);
        
        // Hacemos que el password sea opcional en modo edición
        $('input[name="password"]').attr('placeholder', 'Dejar en blanco para conservar la actual').removeAttr('required');
    
        // Agregamos o actualizamos el campo oculto "idUsuario" para que el controlador PHP haga UPDATE
        if ($('#idUsuarioEdit').length === 0) {
            $('#formNuevoUsuario').append(`<input type="hidden" id="idUsuarioEdit" name="idUsuario" value="${id}">`);
        } else {
            $('#idUsuarioEdit').val(id);
        }
    
        // Desplegamos el modal
        $('#modalUsuario').modal('show');
    });

    // --- 2. FUNCIÓN PARA ELIMINAR (DESACTIVAR) ---
    $(document).on('click', '.btnEliminar', function() {
        const id = $(this).data('id');
        const nombre = $(this).data('nombre');

        Swal.fire({
            title: `¿Eliminar a ${nombre}?`,
            text: "El usuario será desactivado del sistema",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'ajax/eliminar_usuario.php',
                    type: 'POST',
                    data: { id: id },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire('¡Eliminado!', res.message, 'success').then(() => {
                                location.reload();
                            });
                        }
                    }
                });
            }
        });
    });

    // --- 3. CARGAR SEDES EN MODAL DE ASIGNACIÓN ---
    $(document).on('click', '.btnAsignarSede', function() {
        const idUsuario = $(this).data('id');
        const nombre = $(this).data('nombre');

        $('#idUsuarioAsignar').val(idUsuario);
        $('#nombreUsuarioAsignar').text(nombre);
        
        $('#contenedorCentros').html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Cargando centros...</div>');
        $('#modalAsignarSede').modal('show');

        $.ajax({
            url: 'ajax/obtener_asignaciones.php',
            type: 'POST',
            data: { usuario_id: idUsuario },
            success: function(response) {
                $('#contenedorCentros').html(response);
            },
            error: function() {
                $('#contenedorCentros').html('<p class="text-danger">Error al conectar con el servidor.</p>');
            }
        });
    });

    // --- 4. GUARDAR ASIGNACIÓN DE SEDES ---
    $('#formAsignarSede').on('submit', function(e) {
        e.preventDefault();
        const btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Guardando...');

        $.ajax({
            url: 'ajax/guardar_asignacion_sede.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                if(res.status === 'success') {
                    Swal.fire('¡Asignado!', res.message, 'success');
                    $('#modalAsignarSede').modal('hide');
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
                btn.prop('disabled', false).text('Guardar Asignación');
            },
            error: function() {
                Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
                btn.prop('disabled', false).text('Guardar Asignación');
            }
        });
    });

    // --- 5. RESETEO DE MODAL USUARIO ---
    $('#modalUsuario').on('hidden.bs.modal', function () {
        $('#formNuevoUsuario')[0].reset();
        $('#idUsuarioEdit').remove(); // Eliminamos el ID de edición para que no interfiera en nuevos registros
        $('#modalUsuario .modal-title').text('Registrar Nuevo Personal/Usuario');
        $('#modalUsuario .modal-header').removeClass('bg-warning').addClass('bg-primary');
        $('input[name="password"]').attr('placeholder', '').attr('required', true);
    });

    // --- 6. ENVÍO FORMULARIO USUARIO (CREAR/EDITAR) ---
    $('#formNuevoUsuario').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serialize();
        const btn = $(this).find('button[type="submit"]');
        btn.prop('disabled', true).text('Guardando...');

        $.ajax({
            url: 'ajax/guardar_usuario.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: response.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: response.message });
                    btn.prop('disabled', false).text('Guardar Registro');
                }
            },
            error: function(xhr) {
                console.error(xhr.responseText);
                Swal.fire({ icon: 'error', title: 'Error de servidor', text: 'Ocurrió un problema.' });
                btn.prop('disabled', false).text('Guardar Registro');
            }
        });
    });
});