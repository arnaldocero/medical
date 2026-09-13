$(document).ready(function() {

    // 1. FUNCIÓN PARA LISTAR PACIENTES EN TIEMPO REAL
    function cargarPacientesAgenda() {
        $.ajax({
            url: 'ajax/acciones_medico.php',
            type: 'GET',
            dataType: 'json',
            data: { accion: 'listar_pacientes' },
            success: function(res) {
                if (res.status === 'success') {
                    const pacientes = res.data;
                    let htmlFilas = '';

                    if (pacientes.length === 0) {
                        htmlFilas = `<tr>
                            <td colspan="5" class="text-muted p-4">No tiene pacientes asignados en sala de espera para hoy.</td>
                        </tr>`;
                    } else {
                        pacientes.forEach(function(p) {
                            let badgeEstado = '';
                            let botonAccion = '';

                            // Evaluamos estado para definir Badges y Botones
                            if (p.estado === 'En Sala') {
                                badgeEstado = `<span class="badge badge-warning p-2">En Sala de Espera</span>`;
                                botonAccion = `<button class="btn btn-primary btn-sm btnLlamar" data-id="${p.id}">
                                                    <i class="fas fa-bullhorn"></i> Llamar Paciente
                                               </button>`;
                            } else {
                                badgeEstado = `<span class="badge badge-success p-2 animate__animated animate__flash animate__infinite">En su Consultorio</span>`;
                                botonAccion = `<a href="historia_clinica.php?cita_id=${p.id}" class="btn btn-success btn-sm">
                                                    <i class="fas fa-folder-open"></i> Atender / Abrir HC
                                               </a>`;
                            }

                            htmlFilas += `<tr>
                                <td><strong>${p.hora_formato}</strong></td>
                                <td class="text-uppercase">${p.paciente}</td>
                                <td><span class="badge badge-secondary p-2">${p.nombre_consultorio}</span></td>
                                <td>${badgeEstado}</td>
                                <td>${botonAccion}</td>
                            </tr>`;
                        });
                    }

                    // Inyectamos las nuevas filas de forma limpia
                    $('#tbodyPacientesMedico').html(htmlFilas);
                }
            },
            error: function(xhr, status, error) {
                console.error("Error al refrescar la agenda del médico: ", error);
            }
        });
    }

    // Ejecución inicial y bucle repetitivo cada 5 segundos
    cargarPacientesAgenda();
    setInterval(cargarPacientesAgenda, 5000);

    // 2. LOGICA PARA EL BOTÓN DE LLAMAR (Usando delegación de eventos)
    $(document).on('click', '.btnLlamar', function() {
        const citaId = $(this).data('id');

        Swal.fire({
            title: '¿Llamar al paciente?',
            text: "Se notificará de inmediato en las pantallas de la sala de espera.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, llamar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'ajax/acciones_medico.php',
                    type: 'POST',
                    dataType: 'json',
                    data: { 
                        accion: 'llamar_paciente', 
                        cita_id: citaId 
                    },
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                title: '¡Llamando!',
                                text: 'El paciente ha sido notificado.',
                                icon: 'success',
                                timer: 1200,
                                showConfirmButton: false
                            });
                            
                            // Refrescamos la lista de inmediato en lugar de recargar toda la página
                            cargarPacientesAgenda();
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'No se pudo procesar la llamada en el servidor.', 'error');
                    }
                });
            }
        });
    });
});