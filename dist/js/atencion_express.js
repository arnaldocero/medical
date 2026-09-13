$(document).ready(function() {

    let listaServicios = [];

    // 1. Filtro local en tiempo real
    $('#filtroPacienteExpress').on('keyup', function() {
        let valor = $(this).val().toLowerCase();
        $("#cuerpoPacientesExpress tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(valor) > -1);
        });
    });

    // Función genérica para poblar selects
    function poblarSelect(selector, items, idField = 'id', textField = 'nombre', defaultId = null) {
        let $select = $(selector);
        $select.empty();

        if (!items || items.length === 0) {
            $select.append('<option value="">Sin opciones disponibles</option>');
            return;
        }

        if (items.length > 1) {
            $select.append('<option value="">-- Seleccione una opción --</option>');
        }

        $.each(items, function(i, item) {
            let selected = (items.length === 1 || item[idField] == defaultId) ? 'selected' : '';
            $select.append(`<option value="${item[idField]}" ${selected}>${item[textField]}</option>`);
        });

        $select.trigger('change');
    }

    // 2. Abrir Modal y Cargar Datos Iniciales (Centros y Servicios)
    $(document).on('click', '.btn-atender-express', function() {
        let pacienteId = $(this).data('id');
        let pacienteNombre = $(this).data('nombre');

        $('#express_paciente_id').val(pacienteId);
        $('#express_paciente_nombre').val(pacienteNombre);
        $('#alertaAgendaHoy').addClass('d-none');

        // Resetear selects dependientes
        $('#express_medico_id').prop('disabled', true).empty().append('<option value="">-- Seleccione primero una sede --</option>');
        $('#express_consultorio_id').prop('disabled', true).empty().append('<option value="">-- Seleccione primero un médico --</option>');

        $.ajax({
            url: 'ajax/obtener_parametros_express.php?action=inicial',
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    listaServicios = res.servicios || [];

                    // Poblar Centros
                    poblarSelect('#express_centro_id', res.centros, 'id', 'nombre');

                    // Poblar Servicios
                    let serviciosFormat = listaServicios.map(s => ({
                        id: s.id,
                        nombre: `${s.codigo_cups} - ${s.nombre_servicio} ($${s.valor})`
                    }));
                    poblarSelect('#express_servicio_id', serviciosFormat, 'id', 'nombre');

                    $('#modalCitaExpress').modal('show');
                } else {
                    Swal.fire('Error', res.message || 'Error al obtener parámetros.', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'No se pudieron consultar los parámetros requeridos.', 'error');
            }
        });
    });

    // 3. EVENTO CASCADA 1: Al seleccionar Centro -> Cargar Médicos
    $('#express_centro_id').on('change', function() {
        let centroId = $(this).val();
        let $selectMedicos = $('#express_medico_id');
        let $selectCons = $('#express_consultorio_id');

        $('#alertaAgendaHoy').addClass('d-none');
        $selectCons.prop('disabled', true).empty().append('<option value="">-- Seleccione primero un médico --</option>');

        if (!centroId) {
            $selectMedicos.prop('disabled', true).empty().append('<option value="">-- Seleccione primero una sede --</option>');
            return;
        }

        $.ajax({
            url: 'ajax/obtener_parametros_express.php?action=medicos_por_centro&centro_id=' + centroId,
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    $selectMedicos.prop('disabled', false);
                    poblarSelect('#express_medico_id', res.medicos, 'id', 'nombre');
                }
            }
        });
    });

    // 4. EVENTO CASCADA 2: Al seleccionar Médico -> Cargar Consultorios y Validar Agenda de Hoy
    $('#express_medico_id').on('change', function() {
        let centroId = $('#express_centro_id').val();
        let medicoId = $(this).val();
        let $selectCons = $('#express_consultorio_id');
        let $alerta = $('#alertaAgendaHoy');

        if (!medicoId || !centroId) {
            $selectCons.prop('disabled', true).empty().append('<option value="">-- Seleccione primero un médico --</option>');
            $alerta.addClass('d-none');
            return;
        }

        $.ajax({
            url: 'ajax/obtener_parametros_express.php?action=consultorios_por_medico&centro_id=' + centroId + '&medico_id=' + medicoId,
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    $selectCons.prop('disabled', false);
                    poblarSelect('#express_consultorio_id', res.consultorios, 'id', 'nombre_consultorio');

                    // Mostrar estado de la agenda del día
                    $alerta.removeClass('d-none alert-success alert-warning');
                    if (res.tiene_agenda_hoy) {
                        $alerta.addClass('alert-success').html(`<i class="fas fa-check-circle"></i> El médico tiene agenda activa parametrizada para el día de hoy (<b>${res.dia_actual}</b>).`);
                    } else {
                        $alerta.addClass('alert-warning').html(`<i class="fas fa-exclamation-triangle"></i> Atención: El médico <b>NO</b> tiene agenda regular parametrizada para hoy (<b>${res.dia_actual}</b>). Se registrará como atención espontánea.`);
                    }
                }
            }
        });
    });

    // 5. Asignar el valor del servicio al input oculto
    $('#express_servicio_id').on('change', function() {
        let servicioId = $(this).val();
        let servEncontrado = listaServicios.find(s => s.id == servicioId);
        if (servEncontrado) {
            $('#express_valor_cita').val(servEncontrado.valor);
        } else {
            $('#express_valor_cita').val('0');
        }
    });

    // 6. Enviar Cita Express y Redirigir según el Rol y Coincidencia de Médico
    $('#formCitaExpress').on('submit', function(e) {
        e.preventDefault();

        let btnGuardar = $('#btnGuardarCitaExpress');
        btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Guardando...');

        $.ajax({
            url: 'ajax/cita_express_action.php',
            type: 'POST',
            dataType: 'json',
            data: $(this).serialize(),
            success: function(res) {
                if (res.status === 'success') {
                    $('#modalCitaExpress').modal('hide');
                    Swal.fire({
                        icon: 'success',
                        title: 'Atención Iniciada',
                        text: res.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        // PARÁMETROS RETORNADOS
                        let rolUsuario     = parseInt(res.rol, 10);              // Rol numérico (2 = Médico)
                        let medicoAsignado = parseInt(res.medico_id, 10);        // Médico elegido en el formulario
                        let usuarioSesion  = parseInt(res.usuario_sesion_id, 10); // Usuario en sesión activa
                        
                        // Solo va al Panel del Médico si es Rol 2 Y el médico elegido es él mismo
                        if (rolUsuario === 2 && medicoAsignado === usuarioSesion) {
                            window.location.href = 'panel_medico.php';
                        } else {
                            // Recepción, Admin o si un médico agendó para otro colega
                            window.location.href = 'atencion_express.php';
                        }
                    });
                } else {
                    Swal.fire('Error', res.message, 'error');
                    btnGuardar.prop('disabled', false).html('<i class="fas fa-check-circle"></i> Iniciar Atención');
                }
            },
            error: function() {
                Swal.fire('Error', 'Error al procesar la solicitud en el servidor.', 'error');
                btnGuardar.prop('disabled', false).html('<i class="fas fa-check-circle"></i> Iniciar Atención');
            }
        });
    });
});