$(document).ready(function() {

    // ==========================================
    // VARIABLES GLOBALES INTERNAS
    // ==========================================
    let datosOdontograma = [];
    let renglonHCIndex = 0;

    // ==========================================
    // 1. CÁLCULO DINÁMICO DEL IMC
    // ==========================================
    $('.vital-calc').on('input', function() {
        const peso = parseFloat($('#peso').val());
        const estatura = parseFloat($('#estatura').val());

        if (peso > 0 && estatura > 0) {
            const imc = peso / (estatura * estatura);
            $('#imc').val(imc.toFixed(2));
        } else {
            $('#imc').val('');
        }
    });

    // ==========================================
    // 2. LOGÍSTICA DE MEDICAMENTOS (HISTORIA CLÍNICA)
    // ==========================================
    
    // Inicializar buscador select2 de medicamentos para la HC
    $('#buscadorMedicamento_hc').select2({
        theme: 'bootstrap4',
        placeholder: 'Escribe el nombre del medicamento...',
        minimumInputLength: 2,
        ajax: {
            url: 'ajax/formula_action.php',
            dataType: 'json',
            data: function (params) {
                return { action: 'buscar_medicamentos', q: params.term };
            },
            processResults: function (data) {
                return { results: data.results };
            }
        }
    });

    // Agregar medicamentos a la tabla de la Historia Clínica
    $('#btnAgregarMedicamento_hc').on('click', function () {
        let medData = $('#buscadorMedicamento_hc').select2('data')[0];
        let cantidad = parseInt($('#tempCantidad_hc').val());

        if (!medData || cantidad <= 0) {
            Swal.fire('Validación', 'Seleccione un medicamento y defina una cantidad válida.', 'warning');
            return;
        }

        // Remover fila vacía usando el ID correcto
        $('#filaVacia_hc').remove();

        let nuevaFila = `
            <tr id="renglon_hc_${renglonHCIndex}">
                <td>
                    <input type="hidden" name="med_items[${renglonHCIndex}][medicamento_id]" value="${medData.id}">
                    <strong>${medData.text}</strong>
                </td>
                <td>
                    <input type="number" name="med_items[${renglonHCIndex}][cantidad]" class="form-control form-control-sm" value="${cantidad}" min="1" required>
                </td>
                <td>
                    <input type="text" name="med_items[${renglonHCIndex}][dosificacion]" class="form-control form-control-sm" placeholder="Ej: 1 tableta cada 12 horas" required>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-danger btn-sm btn-eliminar-item-hc" data-id="${renglonHCIndex}">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;

        $('#cuerpoFormula_hc').append(nuevaFila);
        renglonHCIndex++;

        // Resetear selectores de fármacos superiores
        $('#buscadorMedicamento_hc').val(null).trigger('change');
        $('#tempCantidad_hc').val(1);
    });

    // Evento para eliminar medicamentos de la tabla en HC
    $(document).on('click', '.btn-eliminar-item-hc', function () {
        let id = $(this).data('id');
        $(`#renglon_hc_${id}`).remove();

        if ($('#cuerpoFormula_hc tr').length === 0) {
            $('#cuerpoFormula_hc').html('<tr id="filaVacia_hc"><td colspan="4" class="text-center text-muted py-3">No se han recetado medicamentos en esta consulta aún.</td></tr>');
        }
    });

    // ==========================================
    // 3. ENVÍO ASÍNCRONO DEL FORMULARIO DE ATENCIÓN
    // ==========================================
    $('#formHistoriaClinica').submit(function(e) {
        e.preventDefault();

        // Validación: Verificar si el diagnóstico principal fue seleccionado
        if ($('#diagnostico_id').val() === '') {
            Swal.fire('Atención', 'Debe seleccionar un diagnóstico principal válido desde el buscador CIE-10.', 'warning');
            return;
        }

        Swal.fire({
            title: '¿Finalizar atención médica?',
            text: "Se guardará el registro clínico, diagnóstico y las recetas asociadas.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, finalizar',
            cancelButtonText: 'Revisar de nuevo'
        }).then((result) => {
            if (result.isConfirmed) {
                const datosFormulario = $(this).serialize();

                $.ajax({
                    url: 'ajax/guardar_historia.php',
                    type: 'POST',
                    dataType: 'json',
                    data: datosFormulario,
                    success: function(res) {
                        if (res.status === 'success') {
                            Swal.fire({
                                title: '¡Atención Guardada!',
                                text: res.message,
                                icon: 'success',
                                confirmButtonText: 'Aceptar'
                            }).then(() => {
                                window.location.href = 'panel_medico.php';
                            });
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'No se pudieron registrar los datos clínicos en el servidor.', 'error');
                    }
                });
            }
        });
    });

    // ==========================================
    // 4. BUSCADOR EN TIEMPO REAL CIE-10
    // ==========================================
    let timeoutCie10 = null;

    $('#buscar_cie10').on('keyup', function() {
        clearTimeout(timeoutCie10);
        const query = $(this).val().trim();
        const listaResultados = $('#resultados_cie10');

        if (query.length < 2) {
            listaResultados.hide().empty();
            $('#diagnostico_id').val('');
            return;
        }

        timeoutCie10 = setTimeout(function() {
            $.ajax({
                url: 'ajax/buscar_diagnostico.php',
                type: 'GET',
                dataType: 'json',
                data: { q: query },
                success: function(data) {
                    listaResultados.empty();
                    
                    if (data.length > 0) {
                        data.forEach(function(item) {
                            listaResultados.append(`
                                <button type="button" class="list-group-item list-group-item-action item-cie10" data-id="${item.id}" data-texto="${item.text}">
                                    ${item.text}
                                </button>
                            `);
                        });
                        listaResultados.show();
                    } else {
                        listaResultados.append(`<div class="list-group-item text-muted">No se encontraron diagnósticos.</div>`);
                        listaResultados.show();
                        $('#diagnostico_id').val('');
                    }
                }
            });
        }, 300);
    });

    $(document).on('click', '.item-cie10', function() {
        const id = $(this).data('id');
        const texto = $(this).data('texto');

        $('#buscar_cie10').val(texto);    
        $('#diagnostico_id').val(id);     
        $('#resultados_cie10').hide();    
    });

    $(document).click(function(e) {
        if (!$(e.target).closest('.form-group').length) {
            $('#resultados_cie10').hide();
        }
    });

    // ==========================================
    // 5. GESTIÓN DE ANEXOS Y ARCHIVOS ADJUNTOS
    // ==========================================
    const pacienteId = $('input[name="paciente_id"]').val();
    const citaId = $('input[name="cita_id"]').val();

    // Cargar los archivos cargados previamente si el paciente los tiene
    if (pacienteId) {
        listarAdjuntosPaciente(pacienteId);
    }

    // Efecto visual para actualizar la caja de texto file-input de Bootstrap
    $(document).on('change', '#archivo_adjunto', function() {
        let fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').addClass("selected").html(fileName);
    });

    // Acción asíncrona para cargar el archivo binario independiente del formulario general
    $('#btnSubirArchivo').on('click', function(e) {
        e.preventDefault();

        let fileInput = $('#archivo_adjunto')[0].files[0];
        let nombreDoc = $('#nombre_personalizado').val().trim();
        let tipoDoc = $('#tipo_archivo').val();

        if (!fileInput || nombreDoc === "") {
            Swal.fire('Validación', 'Por favor asigne un descripción al documento y seleccione un archivo válido.', 'warning');
            return;
        }

        let formData = new FormData();
        formData.append('action', 'subir_adjunto');
        formData.append('paciente_id', pacienteId);
        formData.append('historia_id', citaId); 
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
                $('#btnSubirArchivo').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Subiendo...');
            },
            success: function(res) {
                $('#btnSubirArchivo').prop('disabled', false).html('<i class="fas fa-cloud-upload-alt"></i> Adjuntar Documento');
                if (res.status === 'success') {
                    Swal.fire('¡Subido!', res.message, 'success');
                    $('#nombre_personalizado').val('');
                    $('#archivo_adjunto').val('').next('.custom-file-label').html('Elegir...');
                    listarAdjuntosPaciente(pacienteId); // Refrescar la tabla automáticamente
                } else {
                    Swal.fire('Atención', res.message, 'warning');
                }
            },
            error: function() {
                $('#btnSubirArchivo').prop('disabled', false).html('<i class="fas fa-cloud-upload-alt"></i> Adjuntar Documento');
                Swal.fire('Error', 'No se pudo conectar con el servidor de archivos.', 'error');
            }
        });
    });

    // Función para renderizar los archivos existentes
    function listarAdjuntosPaciente(pacienteId) {
        if (!pacienteId) return;
        
        $.ajax({
            url: 'ajax/adjuntos_action.php',
            type: 'POST',
            dataType: 'json',
            data: { action: 'listar_adjuntos', paciente_id: pacienteId },
            success: function(res) {
                if (res.status === 'success' && res.data.length > 0) {
                    let html = '';
                    res.data.forEach(function(item) {
                        html += `
                            <tr>
                                <td>${item.fecha}</td>
                                <td><span class="badge badge-secondary">${item.tipo}</span></td>
                                <td>${item.nombre}</td>
                                <td>${item.registrado_por}</td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <a href="${item.ruta}" target="_blank" class="btn btn-sm btn-info shadow-sm" title="Ver">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger btnEliminarAdjunto shadow-sm" data-id="${item.id}" title="Eliminar">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                    });
                    $('#cuerpoAdjuntos').html(html);
                } else {
                    $('#cuerpoAdjuntos').html('<tr><td colspan="5" class="text-center text-muted py-3">El paciente no registra anexos previos.</td></tr>');
                }
            }
        });
    }

    // Acción para eliminar físicamente un anexo
    $(document).on('click', '.btnEliminarAdjunto', function() {
        let idAdjunto = $(this).data('id');
        let pacienteIdLocal = $('input[name="paciente_id"]').val();

        Swal.fire({
            title: '¿Eliminar documento?',
            text: "Esta acción removerá el archivo físico del historial.",
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
                            listarAdjuntosPaciente(pacienteIdLocal); 
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    }
                });
            }
        });
    });

  // ==========================================
    // 6. INTERACTIVIDAD DEL ODONTOGRAMA CLÍNICO
    // ==========================================
    
    // CONTROL VISUAL: Asegura que al cambiar de estado se marquen los inputs y clases correctamente
    $(document).on('click', 'label.btn-outline-danger, label.btn-outline-primary, label.btn-outline-warning, label.btn-outline-secondary, label.btn-outline-success', function() {
        // Remover la clase active de todos los botones de estado
        $('label[style*="cursor:pointer"]').removeClass('active');
        
        // Añadir active al botón actual y marcar su input interno como checked
        $(this).addClass('active');
        $(this).find('input[name="dental_status"]').prop('checked', true);
    });

    // Captura el evento clic sobre cualquier cara mapeada de las piezas dentales
    $(document).on('click', '.cara-diente', function(e) {
        e.preventDefault();
        
        let cara = $(this).data('cara');
        let diente = $(this).closest('.geometric-tooth').data('diente');
        
        // Leer el valor del input radio que esté REALMENTE seleccionado ahora mismo
        let estado = $("input[name='dental_status']:checked").val();
        
        // Si por alguna razón extraña no hay ninguno checked, usamos el que tenga el label activo
        if (!estado) {
            estado = $(".active").find('input[name="dental_status"]').val();
        }

        // Respaldo final si todo falla
        if (!estado) estado = "Caries"; 

        // Paleta cromática según el diagnóstico clínico dental
        let bg = "#f4f6f9";
        let color = "#212529";
        
        switch(estado) {
            case "Caries":
                bg = "#dc3545"; // Rojo peligro
                color = "#ffffff";
                break;
            case "Restaurado":
                bg = "#007bff"; // Azul clínico
                color = "#ffffff";
                break;
            case "Corona":
                bg = "#ffc107"; // Amarillo oro
                color = "#212529";
                break;
            case "Ausente":
                bg = "#6c757d"; // Gris cementerio / Extraído
                color = "#ffffff";
                break;
            case "Sano":
                bg = "#f4f6f9"; // Restaurar al color neutro de fábrica
                color = "#212529";
                break;
        }

        // Ejecutar los cambios gráficos en el DOM sobre el objeto cliqueado
        $(this).css({
            "background-color": bg,
            "color": color
        });

        // Administrar la estructura lógica dentro del array para el JSON
        let index = datosOdontograma.findIndex(x => x.diente === diente && x.cara === cara);

        if (index > -1) {
            if (estado === "Sano") {
                datosOdontograma.splice(index, 1); // Si se marca sano, se retira de las patologías
            } else {
                datosOdontograma[index].estado = estado; // Actualizar diagnóstico previo
            }
        } else if (estado !== "Sano") {
            datosOdontograma.push({ diente: diente, cara: cara, estado: estado }); // Registrar nuevo hallazgo
        }

        // Sincronizar el array de datos estructurados con el input hidden correspondiente del formulario
        $('#odontograma_json').val(JSON.stringify(datosOdontograma));
    });

    // ==========================================
    // 7. INTERACTIVIDAD EXAMEN DE LA VISTA (NUEVO)
    // ==========================================
    
    // Autoformatear campos de refracción (Esfera, Cilindro, Adición) al perder el foco
    $('input[name^="ref_esfera_"], input[name^="ref_cilindro_"], input[name^="ref_adicion_"]').on('blur', function() {
        let valor = $(this).val().trim();
        if (valor === '') return;

        // Quitar espacios y estandarizar signos
        valor = valor.replace(/\s+/g, '');

        // Si es solo un número entero o decimal sin signo, asumimos positivo (+)
        if (!valor.startsWith('+') && !valor.startsWith('-')) {
            let num = parseFloat(valor);
            if (!isNaN(num)) {
                valor = (num >= 0 ? '+' : '') + num;
            }
        }

        // Forzar dos decimales si es un número válido (Ej: +1.5 -> +1.50)
        let signo = valor.charAt(0);
        let resto = valor.substring(1);
        let numero = parseFloat(resto);

        if (!isNaN(numero) && (signo === '+' || signo === '-')) {
            $(this).val(signo + numero.toFixed(2));
        }
    });

    // Validar y autoformatear el campo Eje agregando el símbolo de grados (°)
    $('input[name^="ref_eje_"]').on('blur', function() {
        let valor = $(this).val().trim().replace('°', '');
        if (valor === '') return;

        let numero = parseInt(valor, 10);
        if (!isNaN(numero)) {
            // Rango estándar del eje óptico: 0 a 180 grados
            if (numero < 0) numero = 0;
            if (numero > 180) numero = 180;
            $(this).val(numero + '°');
        }
    });

});