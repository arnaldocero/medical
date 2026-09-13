$(document).ready(function() {

    // --- ACCIÓN 1: Cambio de Sede (Centro Médico) ---
    $('#centro_id').on('change', function() {
        let centro_id = $(this).val();
        resetearDesde('centro'); // Limpiar sub-niveles en cascada de inmediato

        if (!centro_id) return;

        // Carga asíncrona estricta de consultorios pertenecientes a la sede
        $.ajax({
            url: 'ajax/agenda_citas_process.php?action=obtener_consultorios',
            type: 'GET',
            data: { centro_id: centro_id },
            dataType: 'json',
            success: function(data) {
                if(data.length > 0) {
                    let options = '<option value="">-- Seleccione Consultorio --</option>';
                    data.forEach(item => {
                        options += `<option value="${item.id}">${item.nombre_consultorio}</option>`;
                    });
                    // Inyectar consultorios y habilitar campos dependientes
                    $('#consultorio_id').html(options).attr('disabled', false);
                    $('#medico_id').attr('disabled', false); 
                } else {
                    Swal.fire('Atención', 'Esta sede no cuenta con consultorios activos configurados.', 'warning');
                }
            }
        });
    });

    // --- ACCIÓN 2: Cambio de Médico ---
    $('#medico_id').on('change', function() {
        resetearDesde('medico');
        if ($(this).val()) {
            // Habilitar selección de fecha únicamente si hay un médico seleccionado
            $('#fecha_cita').attr('disabled', false);
        }
    });

    // --- ACCIÓN 3: Cambio de Fecha (Validación e Intersección horaria de agenda) ---
    $('#fecha_cita').on('change', function() {
        let fecha = $(this).val();
        let medico_id = $('#medico_id').val();
        let centro_id = $('#centro_id').val();

        resetearDesde('fecha');
        if (!fecha || !medico_id || !centro_id) return;

        // Comprobación en tiempo real en la Base de Datos para evitar días sin agenda (Ej: Martes inactivo)
        $.ajax({
            url: 'ajax/agenda_citas_process.php?action=obtener_horas_disponibles',
            type: 'GET',
            data: { medico_id: medico_id, centro_id: centro_id, fecha: fecha },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    // Cargar parámetros de duración calculados desde la disponibilidad médica
                    $('#duracion_calculada').val(res.duracion);
                    $('#lblDuracion').text(res.duracion);

                    // Poblar dinámicamente las horas que se encuentran disponibles (filtrando cruces)
                    let options = '<option value="">-- Seleccione la Hora --</option>';
                    res.horas.forEach(hora => {
                        options += `<option value="${hora.valor}">${hora.formato}</option>`;
                    });
                    $('#hora_inicio').html(options).attr('disabled', false);

                    // Poblar los servicios/procedimientos CUPS con sus respectivas tarifas base
                    let servOptions = '<option value="">-- Seleccione Procedimiento --</option>';
                    res.servicios.forEach(serv => {
                        servOptions += `<option value="${serv.id}" data-valor="${serv.valor}">[${serv.codigo_cups}] ${serv.nombre_servicio}</option>`;
                    });
                    $('#servicio_id').html(servOptions).attr('disabled', false);

                } else {
                    // Si el día consultado no posee agenda o el médico no atiende, se bloquea el flujo inmediatamente
                    Swal.fire('Médico Sin Agenda', res.message, 'error');
                    $('#fecha_cita').val('');
                }
            }
        });
    });

    // --- ACCIÓN 4: Cambio de Servicio (Liquidación de costos automáticos) ---
    $('#servicio_id').on('change', function() {
        let valor = $(this).find(':selected').data('valor') || 0;
        $('#valor_cita').val(valor);
        $('#lblCosto').text('$' + parseFloat(valor).toLocaleString('es-CO', { minimumFractionDigits: 2 }));
        
        validarEstadoBotonGuardar();
    });

    // --- ACCIÓN 5: Cambio de Hora ---
    $('#hora_inicio').on('change', function() {
        validarEstadoBotonGuardar();
    });

    // --- ACCIÓN 6: Envío y procesamiento del Formulario ---
    $('#formAgendarPasoAPaso').on('submit', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: 'ajax/agenda_citas_process.php?action=guardar',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    Swal.fire('¡Éxito!', response.message, 'success').then(() => {
                        location.reload(); // Recarga la página para restaurar el formulario a su estado inicial limpio
                    });
                } else {
                    Swal.fire('Error al Agendar', response.message, 'error');
                }
            }
        });
    });

    // --- FUNCION AUXILIAR: Validación de botón de envío ---
    function validarEstadoBotonGuardar() {
        if($('#servicio_id').val() && $('#hora_inicio').val()) {
            $('#btnGuardar').attr('disabled', false);
        } else {
            $('#btnGuardar').attr('disabled', true);
        }
    }

    // --- FUNCION AUXILIAR: Reset dinámico en cascada ---
    function resetearDesde(nivel) {
        if (nivel === 'centro') {
            $('#medico_id').val('').attr('disabled', true);
            $('#consultorio_id').html('<option value="">-- Esperando Sede --</option>').attr('disabled', true);
        }
        if (nivel === 'centro' || nivel === 'medico') {
            $('#fecha_cita').val('').attr('disabled', true);
        }
        $('#hora_inicio').html('<option value="">-- Seleccione Fecha Primero --</option>').attr('disabled', true);
        $('#servicio_id').html('<option value="">-- Complete los pasos anteriores --</option>').attr('disabled', true);
        $('#lblDuracion').text('--');
        $('#lblCosto').text('$0.00');
        $('#duracion_calculada').val('');
        $('#valor_cita').val(0);
        $('#btnGuardar').attr('disabled', true);
    }
});