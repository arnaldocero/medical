$(document).ready(function() {
    // Inicializar DataTable
    $("#tablaEspecialidades").DataTable({
        "responsive": true, "autoWidth": false,
        "language": { "url": "//cdn.datatables.net/plug-ins/1.10.20/i18n/Spanish.json" }
    });

    // Abrir modal para editar
    $(document).on('click', '.btnEditarEsp', function() {
        const id = $(this).data('id');
        const nombre = $(this).data('nombre');
        const desc = $(this).data('descripcion');

        $('#modalEspecialidad .modal-title').text('Editar Especialidad');
        $('#modalEspecialidad .modal-header').removeClass('bg-primary').addClass('bg-warning');
        
        $('#idEspecialidad').val(id);
        $('#nombreEsp').val(nombre);
        $('#descEsp').val(desc);

        $('#modalEspecialidad').modal('show');
    });

    // Resetear modal al cerrar
    $('#modalEspecialidad').on('hidden.bs.modal', function () {
        $('#formEspecialidad')[0].reset();
        $('#idEspecialidad').val('');
        $('#modalEspecialidad .modal-title').text('Nueva Especialidad');
        $('#modalEspecialidad .modal-header').removeClass('bg-warning').addClass('bg-primary');
    });

    // Guardar (Crear o Editar)
    $('#formEspecialidad').on('submit', function(e) {
        e.preventDefault();
        const btn = $('#btnGuardarEsp');
        btn.prop('disabled', true).text('Procesando...');

        $.ajax({
            url: 'ajax/guardar_especialidad.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                if(res.status === 'success') {
                    Swal.fire('¡Éxito!', res.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message, 'error');
                    btn.prop('disabled', false).text('Guardar Especialidad');
                }
            }
        });
    });
});