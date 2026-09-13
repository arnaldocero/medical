$(document).ready(function() {
    $("#tablaConsultorios").DataTable({
        "responsive": true, "autoWidth": false,
        "language": { "url": "//cdn.datatables.net/plug-ins/1.10.20/i18n/Spanish.json" }
    });

    // Editar
    $(document).on('click', '.btnEditarCon', function() {
        const id = $(this).data('id');
        const centro = $(this).data('centro');
        const nombre = $(this).data('nombre');
        const tipo = $(this).data('tipo');

        $('#idConsultorio').val(id);
        $('#centro_id').val(centro);
        $('#nombre_con').val(nombre);
        $('#tipo_con').val(tipo);

        $('#modalConsultorio .modal-title').text('Editar Consultorio');
        $('#modalConsultorio').modal('show');
    });

    // Guardar
    $('#formConsultorio').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: 'ajax/guardar_consultorio.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                if(res.status === 'success') {
                    Swal.fire('¡Éxito!', res.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            }
        });
    });

    // Limpiar al cerrar
    $('#modalConsultorio').on('hidden.bs.modal', function() {
        $('#formConsultorio')[0].reset();
        $('#idConsultorio').val('');
    });
});