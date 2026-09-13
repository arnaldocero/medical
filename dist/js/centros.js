$(document).ready(function() {
    // Abrir para Editar
    $(document).on('click', '.btnEditarCentro', function() {
        const d = $(this).data();
        $('#idCentro').val(d.id);
        $('input[name="nombre_centro"]').val(d.nombre);
        $('input[name="nit"]').val(d.nit);
        $('input[name="ciudad"]').val(d.ciudad);
        $('input[name="direccion"]').val(d.direccion);
        
        $('#modalCentro .modal-title').text('Editar Centro Médico');
        $('#modalCentro .modal-header').removeClass('bg-primary').addClass('bg-warning');
        $('#modalCentro').modal('show');
    });

    // Resetear al cerrar
    $('#modalCentro').on('hidden.bs.modal', function () {
        $('#formCentro')[0].reset();
        $('#idCentro').val('');
        $('#modalCentro .modal-title').text('Nuevo Centro Médico');
        $('#modalCentro .modal-header').removeClass('bg-warning').addClass('bg-primary');
    });

    // Guardar/Actualizar
    $('#formCentro').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: 'ajax/guardar_centro.php',
            type: 'POST',
            data: $(this).serialize(),
            success: function(res) {
                const data = JSON.parse(res);
                if(data.status === 'success') {
                    Swal.fire('¡Éxito!', data.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            }
        });
    });
    // --- FUNCIÓN PARA DESACTIVAR CENTRO MÉDICO ---
$(document).on('click', '.btnEliminarCentro', function() {
    const id = $(this).data('id');
    const nombre = $(this).data('nombre');

    Swal.fire({
        title: `¿Desactivar el centro ${nombre}?`,
        text: "El centro ya no aparecerá en las opciones de asignación.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, desactivar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'ajax/eliminar_centro.php',
                type: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        Swal.fire('¡Hecho!', res.message, 'success').then(() => {
                            location.reload(); 
                        });
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Resultado del servidor:", xhr.responseText); // Esto te dirá el error real en la consola
                    Swal.fire('Error', 'Revisa la consola (F12) para ver el error técnico', 'error');
                }
            });
        }
    });
});
});