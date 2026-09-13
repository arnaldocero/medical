$(document).ready(function() {
    const pacienteId = $('#paciente_id_exp').val();

    if (pacienteId) {
        listarAdjuntosExpediente(pacienteId);
    }

    // Efecto input file de Bootstrap
    $(document).on('change', '#archivo_adjunto_exp', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').addClass("selected").html(fileName);
    });

    // Enviar archivo
    $('#formExpedienteAdjuntos').on('submit', function(e) {
        e.preventDefault();

        let fileInput = $('#archivo_adjunto_exp')[0].files[0];
        let nombreDoc = $('#nombre_personalizado_exp').val().trim();
        let tipoDoc = $('#tipo_archivo_exp').val();

        let formData = new FormData();
        formData.append('action', 'subir_adjunto');
        formData.append('paciente_id', pacienteId);
        formData.append('historia_id', ''); // Forzamos envío vacío
        formData.append('nombre_personalizado', nombreDoc);
        formData.append('tipo_archivo', tipoDoc);
        formData.append('archivo_adjunto', fileInput);

        $.ajax({
            url: 'ajax/adjuntos_action.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            contentType: false,
            processData: false,
            beforeSend: function() {
                $('#btnSubirExpediente').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Guardando...');
            },
            success: function(res) {
                $('#btnSubirExpediente').prop('disabled', false).html('<i class="fas fa-file-upload"></i> Cargar al Historial Digital');
                if (res.status === 'success') {
                    Swal.fire('¡Guardado!', res.message, 'success');
                    $('#nombre_personalizado_exp').val('');
                    $('#archivo_adjunto_exp').val('').next('.custom-file-label').html('Elegir...');
                    listarAdjuntosExpediente(pacienteId);
                } else {
                    Swal.fire('Atención', res.message, 'warning');
                }
            },
            error: function() {
                $('#btnSubirExpediente').prop('disabled', false).html('<i class="fas fa-file-upload"></i> Cargar al Historial Digital');
                Swal.fire('Error', 'No se pudo comunicar con el servidor.', 'error');
            }
        });
    });

    function listarAdjuntosExpediente(idPac) {
        $.ajax({
            url: 'ajax/adjuntos_action.php',
            type: 'GET',
            dataType: 'json',
            data: { action: 'listar_adjuntos', paciente_id: idPac },
            beforeSend: function() {
                $('#cuerpoAdjuntosExpediente').html('<tr><td colspan="5" class="text-center py-3"><i class="fas fa-spinner fa-spin"></i> Cargando historial clínico...</td></tr>');
            },
            success: function(res) {
                $('#cuerpoAdjuntosExpediente').empty();
                if (res.status === 'success' && res.data.length > 0) {
                    res.data.forEach(function(item) {
                        $('#cuerpoAdjuntosExpediente').append(`
                            <tr>
                                <td>${item.fecha_registro}</td>
                                <td><span class="badge badge-secondary p-1">${item.tipo_archivo}</span></td>
                                <td class="font-weight-bold text-uppercase">${item.nombre_personalizado}</td>
                                <td><small class="text-muted"><i class="fas fa-user-md"></i> ${item.medico_nombre}</small></td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <a href="${item.ruta_archivo}" target="_blank" class="btn btn-sm btn-info shadow-sm" title="Ver archivo">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger btnEliminarAdjuntoExp shadow-sm" data-id="${item.id}" title="Eliminar">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `);
                    });
                } else {
                    $('#cuerpoAdjuntosExpediente').html('<tr><td colspan="5" class="text-center text-muted py-3">Este paciente no cuenta con documentos indexados aún.</td></tr>');
                }
            }
        });
    }

    $(document).on('click', '.btnEliminarAdjuntoExp', function() {
        let idAdjunto = $(this).data('id');
        Swal.fire({
            title: '¿Eliminar documento?',
            text: "El archivo se destruirá del expediente médico permanentemente.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'ajax/adjuntos_action.php',
                    type: 'POST',
                    dataType: 'json',
                    data: { action: 'eliminar_adjunto', id: idAdjunto },
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire('Eliminado', res.message, 'success');
                            listarAdjuntosExpediente(pacienteId);
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    }
                });
            }
        });
    });
});