$(document).ready(function() {

    // Función encargada de renderizar la tabla con filtros activos
    function cargarCitasControl() {
        const fecha = $('#filtroFecha').val();
        const centro_id = $('#filtroCentro').val();

        $('#divTablaCitasControl').html('<div class="p-4 text-center text-muted"><i class="fas fa-sync fa-spin"></i> Cargando agenda diaria...</div>');

        $.post('ajax/listar_citas_control.php', { fecha: fecha, centro_id: centro_id }, function(data) {
            $('#divTablaCitasControl').html(data);
        });
    }

    // Evento de clic en el botón de actualización o filtros
    $('#btnBuscarCitas').on('click', function() {
        cargarCitasControl();
    });

    // Cargar la lista de manera automática al abrir la pantalla
    cargarCitasControl();

    // Evento Delegado: Detectar clics en botones de cambio de estado
    $(document).on('click', '.btnCambiarEstado', function() {
        const citaId = $(this).data('id');
        const nuevoEstado = $(this).data('estado');

        if (nuevoEstado === 'Cancelada') {
            Swal.fire({
                title: '¿Confirmas la cancelación de la cita?',
                text: "Esta acción liberará el espacio en la programación del día.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, cancelar cita',
                cancelButtonText: 'Mantener cita'
            }).then((result) => {
                if (result.isConfirmed) {
                    procesarCambio(citaId, nuevoEstado);
                }
            });
        } else {
            procesarCambio(citaId, nuevoEstado);
        }
    });

    // Función interna encargada de despachar la petición AJAX al procesador
    function procesarCambio(id, estado) {
        $.ajax({
            url: 'ajax/cambiar_estado_cita.php', // El procesador backend que construimos en el paso anterior
            type: 'POST',
            data: { id: id, estado: estado },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    Swal.fire('¡Éxito!', res.message, 'success');
                    cargarCitasControl(); // Volver a refrescar el listado automáticamente
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'No se pudo comunicar con el servidor.', 'error');
            }
        });
    }
});