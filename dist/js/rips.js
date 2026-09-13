$(document).ready(function() {
    
    // 1. Cargar las consultas por AJAX según el rango de fechas
$('#btnBuscarAtenciones').on('click', function() {
    const fechaInicio = $('#fecha_inicio').val();
    const fechaFin = $('#fecha_fin').val();

    $.ajax({
        url: 'ajax/generar_rips_action.php',
        type: 'GET',
        dataType: 'json',
        data: { 
            action: 'listar_atenciones', 
            fecha_inicio: fechaInicio, 
            fecha_fin: fechaFin 
        },
        beforeSend: function() {
            $('#cuerpoRips').html('<tr><td colspan="6" class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-indigo"></i> <br>Buscando registros...</td></tr>');
            $('#btnGenerarJSON').prop('disabled', true);
        },
        success: function(res) {
            $('#cuerpoRips').empty();
            if(res.status === 'success' && res.data.length > 0) {
                res.data.forEach(function(item) {
                    $('#cuerpoRips').append(`
                        <tr>
                            <td><input type="checkbox" class="checkItem" value="${item.historia_id}"></td>
                            <td>${item.fecha_atencion} ${item.hora_inicio}</td>
                            <td><code>${item.documento_identidad}</code></td>
                            <td class="text-uppercase">${item.paciente}</td>
                            <td><strong>${item.codigo_cie10}</strong> - ${item.nombre_diagnostico}</td>
                            <td>${item.medico}</td>
                        </tr>
                    `);
                });
                $('#btnGenerarJSON').prop('disabled', false);
            } else {
                let mensaje = res.message ? res.message : 'No se encontraron atenciones médicas cerradas en este rango de fechas.';
                $('#cuerpoRips').html(`<tr><td colspan="6" class="text-center text-muted py-4">${mensaje}</td></tr>`);
            }
        },
        error: function(xhr, status, errorThrown) {
            let mensajeError = "Error desconocido.";
            
            if (xhr.status === 0) {
                mensajeError = "No se pudo conectar al servidor. Verifique su red.";
            } else if (xhr.status == 404) {
                mensajeError = "El archivo controlador no fue encontrado (Error 404). Verifique la ruta de la URL.";
            } else if (xhr.status == 500) {
                mensajeError = "Error interno del servidor (Error 500). Hay un problema en el código PHP.";
            } else if (status === 'parsererror') {
                mensajeError = "Error de parseo JSON. El servidor respondió con texto plano o un error PHP mal formateado.";
            } else if (status === 'timeout') {
                mensajeError = "Tiempo de espera agotado.";
            }

            // Mostramos el detalle técnico exacto en la tabla para que sepas qué pasó de inmediato
            $('#cuerpoRips').html(`
                <tr>
                    <td colspan="6" class="text-center text-danger py-4">
                        <i class="fas fa-exclamation-triangle fa-2x"></i> <br>
                        <strong>${mensajeError}</strong> <br>
                        <small class="text-muted">Detalle técnico: ${errorThrown || 'Sin texto de error'} - Código HTTP: ${xhr.status}</small>
                        <hr class="w-50">
                        <div class="text-left bg-light p-2 mx-auto rounded" style="max-width: 600px; font-family: monospace; font-size: 11px; white-space: pre-wrap; overflow-x: auto;">${xhr.responseText ? xhr.responseText.substring(0, 400) : 'Sin respuesta de texto'}</div>
                    </td>
                </tr>
            `);
        }
    });
});

    // 2. Control del Checkbox Maestro (Seleccionar/Deseleccionar todo)
    $('#checkTodos').on('change', function() {
        $('.checkItem').prop('checked', $(this).prop('checked'));
    });

    // 3. Procesar y Descargar el archivo JSON de RIPS
    $('#btnGenerarJSON').on('click', function() {
        let seleccionados = [];
        $('.checkItem:checked').each(function() {
            seleccionados.push($(this).val());
        });

        if (seleccionados.length === 0) {
            Swal.fire('Atención', 'Por favor, seleccione al menos una consulta médica de la lista.', 'warning');
            return;
        }

        // Crear un formulario virtual e invisible para forzar la descarga binaria del stream JSON
        let form = $('<form>', { action: 'ajax/generar_rips_action.php', method: 'POST' });
        form.append($('<input>', { type: 'hidden', name: 'action', value: 'descargar_rips' }));
        form.append($('<input>', { type: 'hidden', name: 'historias_ids', value: JSON.stringify(seleccionados) }));
        
        $('body').append(form);
        form.submit();
        form.remove();
    });
});