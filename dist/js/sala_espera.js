$(document).ready(function() {
    
    function actualizarReloj() {
        const ahora = new Date();
        let horas = ahora.getHours();
        let minutos = ahora.getMinutes();
        let segundos = ahora.getSeconds();
        const ampm = horas >= 12 ? 'PM' : 'AM';
        horas = horas % 12;
        horas = horas ? horas : 12;
        minutos = minutos < 10 ? '0' + minutos : minutos;
        segundos = segundos < 10 ? '0' + segundos : segundos;
        $('#liveClock').text(horas + ':' + minutos + ':' + segundos + ' ' + ampm);
    }
    actualizarReloj();
    setInterval(actualizarReloj, 1000);

    // Guardamos el ID del último paciente llamado para no repetir el sonido infinitamente
    let ultimoIdLlamado = null;

    function actualizarContenidoSala() {
        $.ajax({
            url: 'ajax/consultar_sala.php',
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    const pacientes = res.data;
                    let htmlFilas = '';
                    let alguienSiendoLlamado = false;
                    let idActualLlamado = null;

                    if (pacientes.length === 0) {
                        htmlFilas = `<tr>
                            <td colspan="4" class="text-muted p-4 text-center">
                                <i class="fas fa-check-circle text-success mb-2" style="font-size: 1.5rem;"></i><br>
                                No hay pacientes en espera en este momento.
                            </td>
                        </tr>`;
                    } else {
                        pacientes.forEach(function(p) {
                            let claseFila = '';
                            let badgeConsultorio = '';

                            // SI EL MÉDICO LO ESTÁ LLAMANDO AHORA
                            if (p.estado === 'En Consulta') {
                                claseFila = 'fila-llamado-activa'; // Estilo CSS de parpadeo
                                badgeConsultorio = `<span class="badge badge-danger p-2 shadow animate__animated animate__flash animate__infinite" style="font-size: 1.1rem;">
                                                        <i class="fas fa-bell"></i> PASAR A: ${p.nombre_consultorio}
                                                    </span>`;
                                alguienSiendoLlamado = true;
                                idActualLlamado = p.id;
                            } else {
                                // Paciente normal esperando en la cola
                                claseFila = '';
                                badgeConsultorio = `<span class="badge badge-info p-2" style="font-size: 0.95rem;">
                                                        <i class="fas fa-door-open"></i> ${p.nombre_consultorio}
                                                    </span>`;
                            }
                            
                            htmlFilas += `<tr class="${claseFila}">
                                <td><strong style="font-size: 1.1rem;">${p.hora_formato}</strong></td>
                                <td class="text-uppercase"><strong>${p.paciente_corto}</strong></td>
                                <td>Dr(a). ${p.medico}</td>
                                <td>${badgeConsultorio}</td>
                            </tr>`;
                        });
                    }

                    $('#tbodySalaEspera').html(htmlFilas);

                    // CONTROL INTELIGENTE DEL AUDIO
                    // Si hay alguien en estado 'En Consulta' y es diferente al último que sonó, disparamos la alerta
                    if (alguienSiendoLlamado && idActualLlamado !== ultimoIdLlamado) {
                        ultimoIdLlamado = idActualLlamado;
                        const audio = document.getElementById('sndAlerta');
                        if (audio) {
                            audio.currentTime = 0; // Reiniciar audio si estaba sonando
                            audio.play().catch(e => console.log("Permisos de audio pendientes:", e));
                        }
                    }
                }
            }
        });
    }

    actualizarContenidoSala();
    setInterval(actualizarContenidoSala, 3000); // Monitoreo rápido cada 3 segundos en el TV
});