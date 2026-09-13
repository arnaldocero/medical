$(document).ready(function() {
    
    // FILTRO: Al cambiar médico, cargar su especialidad desde datos_medicos
    $('#selMedico').on('change', function() {
        const idMedico = $(this).val();
        if(idMedico) {
            $.post('ajax/obtener_filtros_agenda.php?action=getEspecialidades', {id: idMedico}, function(data) {
                $('#selEspecialidad').html(data).prop('disabled', false);
            });
        } else {
            $('#selEspecialidad').html('<option value="">Seleccione...</option>').prop('disabled', true);
        }
    });

    // FILTRO: Al cambiar sede, cargar sus consultorios
    $('#selSede').on('change', function() {
        const idSede = $(this).val();
        if(idSede) {
            $.post('ajax/obtener_filtros_agenda.php?action=getConsultorios', {id: idSede}, function(data) {
                $('#selConsultorio').html(data).prop('disabled', false);
            });
        } else {
            $('#selConsultorio').html('<option value="">Seleccione...</option>').prop('disabled', true);
        }
    });

    // GUARDAR DISPONIBILIDAD
    $('#formDisponibilidad').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: 'ajax/guardar_disponibilidad.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                if(res.status === 'success') {
                    Swal.fire('¡Éxito!', res.message, 'success');
                    
                    // Limpiar formulario de forma segura
                    $('#formDisponibilidad')[0].reset(); 
                    
                    // Si utilizas el plugin Select2, reinicia el estado visual del selector de médicos
                    if($.fn.select2 && $('#selMedico').hasClass("select2-hidden-accessible")) {
                        $('#selMedico').val('').trigger('change');
                    } else {
                        // Forzar desactivación manual de los select dependientes dinámicos
                        $('#selEspecialidad').html('<option value="">Seleccione médico primero...</option>').prop('disabled', true);
                        $('#selConsultorio').html('<option value="">Seleccione sede primero...</option>').prop('disabled', true);
                    }
                    
                    cargarTablaDispo(); // Refrescar la tabla automáticamente
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }
        });
    });

    // Función para cargar la tabla de horarios
    function cargarTablaDispo() {
        $.post('ajax/listar_disponibilidad.php', function(data) {
            $('#divTablaDisponibilidad').html(data);
        });
    }

    // Inicializar la tabla al cargar la página
    cargarTablaDispo();

    // Evento delegado para eliminar un horario configurado
    $(document).on('click', '.btnEliminarDispo', function() {
        const id = $(this).data('id');
        
        Swal.fire({
            title: '¿Eliminar este horario?',
            text: "Esta acción no se puede deshacer",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('ajax/eliminar_disponibilidad.php', {id: id}, function(res) {
                    if(res.status === 'success') {
                        cargarTablaDispo();
                        Swal.fire('Eliminado', res.message, 'success');
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                }, 'json');
            }
        });
    });
});