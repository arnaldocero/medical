$(document).ready(function () {
    const actionUrl = 'ajax/diagnostico_action.php';

    // Inicializar DataTables cargando inicialmente los registros por defecto
    const tabla = $('#tablaDiagnosticos').DataTable({
        "ajax": {
            "url": actionUrl,
            "type": "POST",
            "data": function(d) {
                // Inyectamos dinámicamente el valor del input personalizado en la petición AJAX
                d.action = "listar";
                d.search = $('#searchDiag').val();
            },
            "dataSrc": ""
        },
        "columns": [
            { "data": "codigo_cie", "className": "text-bold text-success" },
            { "data": "descripcion" },
            { "data": "version" },
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
                        ? `<button class="btn btn-secondary btn-sm btnStatus" data-id="${row.id}" data-estado="0" title="Desactivar"><i class="fas fa-toggle-on"></i></button>`
                        : `<button class="btn btn-success btn-sm btnStatus" data-id="${row.id}" data-estado="1" title="Activar"><i class="fas fa-toggle-off"></i></button>`;

                    return `
                        <button class="btn btn-warning btn-sm btnEditar" data-id="${row.id}" data-cie="${row.codigo_cie}" data-desc="${row.descripcion}" data-version="${row.version}"><i class="fas fa-edit"></i></button>
                        ${btnEstado}
                    `;
                }
            }
        ],
        "dom": "tpi", // Quitamos el buscador nativo de DataTables para usar el nuestro de alto rendimiento
        "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json" }
    });

    // Evento dinámico: Al escribir en la barra de búsqueda, recargamos la tabla vía AJAX
    let delayTimer;
    $('#searchDiag').on('keyup', function () {
        clearTimeout(delayTimer);
        // Esperamos 350ms después de que el usuario deje de escribir para no saturar el servidor (Debounce)
        delayTimer = setTimeout(function() {
            tabla.ajax.reload(null, false);
        }, 350);
    });

    // Control de modales - Nuevo registro
    $('#btnNuevoDiag').on('click', function() {
        $('#formDiagnostico')[0].reset();
        $('#action').val('registrar');
        $('#diagnostico_id').val('');
        $('#modalTitle').html('<i class="fas fa-plus"></i> Crear Diagnóstico Manual');
    });

    // Control de modales - Edición
    $(document).on('click', '.btnEditar', function () {
        $('#action').val('editar');
        $('#diagnostico_id').val($(this).data('id'));
        $('#codigo_cie').val($(this).data('cie'));
        $('#descripcion').val($(this).data('desc'));
        $('#version').val($(this).data('version'));
        $('#modalTitle').html('<i class="fas fa-edit"></i> Modificar Diagnóstico CIE');
        $('#modalDiagnostico').modal('show');
    });

    // Guardado mediante AJAX
    $('#formDiagnostico').on('submit', function (e) {
        e.preventDefault();
        $.ajax({
            url: actionUrl,
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    Swal.fire('¡Éxito!', res.message, 'success');
                    $('#modalDiagnostico').modal('hide');
                    tabla.ajax.reload(null, false);
                } else {
                    Swal.fire('Atención', res.message, 'warning');
                }
            }
        });
    });

    // Activación / Inactivación
    $(document).on('click', '.btnStatus', function () {
        const id = $(this).data('id');
        const nuevo = $(this).data('estado');

        $.ajax({
            url: actionUrl,
            type: 'POST',
            data: { action: 'estado', diagnostico_id: id, nuevo_estado: nuevo },
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    tabla.ajax.reload(null, false);
                }
            }
        });
    });
});