$(document).ready(function() {

    // Evento click para cambiar estado de la cita
    $(document).on('click', '.btnCambiarEstado', function() {
        const citaId = $(this).data('id');
        const nuevoEstado = $(this).data('estado');
        
        // Si la acción es cancelar, pedimos una confirmación extra de seguridad
        if (nuevoEstado === 'Cancelada') {
            Swal.fire({
                title: '¿Confirmas la cancelación?',
                text: "Esta acción liberará el espacio en la agenda médica.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, cancelar cita',
                cancelButtonText: 'Mantener asignada'
            }).then((result) => {
                if (result.isConfirmed) {
                    ejecutarCambioEstado(citaId, nuevoEstado);
                }
            });
        } else {
            // Para otros estados como "En Sala" o "Atendida" procedemos directo
            ejecutarCambioEstado(citaId, nuevoEstado);
        }
    });

    // Función reutilizable que hace la petición AJAX
    function ejecutarCambioEstado(id, estado) {
        $.ajax({
            url: 'ajax/cambiar_estado_cita.php',
            type: 'POST',
            data: { id: id, estado: estado },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    Swal.fire('¡Actualizado!', res.message, 'success');
                    
                    // AQUÍ LLAMAS A TU FUNCIÓN DE REFRESCAR LA TABLA O CALENDARIO
                    // Ejemplo: cargarTablaCitasActivas();
                    if (typeof cargarTablaCitas === 'function') {
                        cargarTablaCitas(); 
                    } else {
                        // Opción de respaldo por si no tienes función AJAX de recarga de tabla instalada aún:
                        location.reload(); 
                    }
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
            }
        });
    }
});