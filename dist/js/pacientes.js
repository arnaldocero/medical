$(document).ready(function () {
    // Inicializar DataTable local sobre la tabla ya renderizada por PHP
    const tabla = $('#tablaPacientes').DataTable({
        "language": {
            "url": "https://cdn.datatables.net/plug-ins/1.13.5/i18n/es-ES.json"
        }
    });

    // Abrir Modal en Modo CREACIÓN
    $('#btnNuevoPaciente').on('click', function () {
        $('#formPaciente')[0].reset();
        $('#idPacienteEdit').val('');
        $('#idUsuarioEdit').val('');

        $('#modalTitle').text('Registrar Nuevo Paciente');
        $('.modal-header').removeClass('bg-warning').addClass('bg-primary');
        $('#btnGuardar').removeClass('btn-warning').addClass('btn-primary').text('Guardar Registro');
        
        $('#inputPassword').attr('required', true).attr('placeholder', '');
        $('#labelPassword').text('Contraseña');

        $('#modalPaciente').modal('show');
    });

    // Abrir Modal en Modo EDICIÓN
    $('#tablaPacientes').on('click', '.btnEditar', function () {
        $('#formPaciente')[0].reset();

        // Extraer identificadores relacionales
        const id = $(this).data('id');
        const uid = $(this).data('uid');

        $('#idPacienteEdit').val(id);
        $('#idUsuarioEdit').val(uid);

        // Mutar estilo visual a Modo Edición
        $('#modalTitle').text('Modificar Información del Paciente');
        $('.modal-header').removeClass('bg-primary').addClass('bg-warning');
        $('#btnGuardar').removeClass('btn-primary').addClass('btn-warning').text('Guardar Cambios');
        
        // Desactivar obligatoriedad de password
        $('#inputPassword').removeAttr('required').attr('placeholder', 'Dejar en blanco para no modificar');
        $('#labelPassword').text('Contraseña (Opcional)');

        // Inyección estricta basándose en los atributos data-* corregidos
        $('input[name="nombre"]').val($(this).data('nombre'));
        $('input[name="email"]').val($(this).data('email'));
        $('input[name="usuario"]').val($(this).data('user')); // ¡Línea agregada!
        $('select[name="estado"]').val($(this).data('estado'));

        $('input[name="documento_identidad"]').val($(this).data('doc'));
        $('input[name="fecha_nacimiento"]').val($(this).data('fnac'));
        $('select[name="genero"]').val($(this).data('genero'));
        $('select[name="tipo_sangre"]').val($(this).data('sangre'));
        $('input[name="telefono_contacto"]').val($(this).data('tel'));
        $('select[name="eps_id"]').val($(this).data('eps'));
        $('input[name="direccion"]').val($(this).data('dir'));
        
        $('input[name="contacto_emergencia_nombre"]').val($(this).data('emernombre'));
        $('input[name="contacto_emergencia_telefono"]').val($(this).data('emertel'));

        $('#modalPaciente').modal('show');
    });

    // Envío del Formulario (Crear o Editar)
    $('#formPaciente').on('submit', function (e) {
        e.preventDefault();

        const esEdicion = $('#idPacienteEdit').val() !== '';
        const actionUrl = esEdicion ? 'ajax/pacientes_action.php?action=editar' : 'ajax/pacientes_action.php?action=crear';

        $.ajax({
            url: actionUrl,
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    Swal.fire({
                        title: '¡Operación Exitosa!',
                        text: res.message,
                        icon: 'success'
                    }).then(() => {
                        location.reload(); // Recarga para actualizar el renderizado del foreach de PHP
                    });
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            },
            error: function () {
                Swal.fire('Error Técnico', 'Ocurrió un problema en la petición AJAX.', 'error');
            }
        });
    });

    // Eliminar Paciente en Cascada Transaccional
    $('#tablaPacientes').on('click', '.btnEliminar', function () {
        const id = $(this).data('id');
        const uid = $(this).data('uid');
        const nombre = $(this).data('nombre');

        Swal.fire({
            title: `¿Eliminar a ${nombre}?`,
            text: "Esta acción borrará de manera definitiva el expediente médico y la cuenta de acceso.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'ajax/pacientes_action.php?action=eliminar',
                    type: 'POST',
                    data: { id: id, usuario_id: uid },
                    dataType: 'json',
                    success: function (res) {
                        if (res.status === 'success') {
                            Swal.fire('Removido', res.message, 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    },
                    error: function () {
                        Swal.fire('Error Técnico', 'No se pudo procesar la eliminación.', 'error');
                    }
                });
            }
        });
    });

    // NUEVO: Lanzar el visor dinámico del expediente e historias clínicas
    $('#tablaPacientes').on('click', '.btnHistorial', function () {
        const paciente_id = $(this).data('id');
        const nombre = $(this).data('nombre');
        
        $('#lblNombrePacienteHistorial').text(nombre);
        $('#contenedorHistorial').html('<div class="text-center my-4"><i class="fas fa-spinner fa-spin fa-2x text-indigo"></i><p class="mt-2">Extrayendo datos de la historia clínica...</p></div>');
        $('#modalHistorialClinico').modal('show');

        $.ajax({
            url: 'ajax/pacientes_action.php?action=obtener_historial',
            type: 'POST',
            data: { paciente_id: paciente_id },
            dataType: 'html', // Esperamos una respuesta maquetada en HTML estructurado
            success: function (htmlResponse) {
                $('#contenedorHistorial').html(htmlResponse);
            },
            error: function () {
                $('#contenedorHistorial').html('<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> No se pudo establecer conexión con el módulo de registros médicos.</div>');
            }
        });
    });

    // IMPLEMENTACIÓN DE ACCIÓN: BOTÓN PARA IMPRIMIR O GUARDAR HISTORIA CLÍNICA EN PDF
    $('#btnImprimirHistorial').on('click', function () {
        const nombrePaciente = $('#lblNombrePacienteHistorial').text();
        const contenidoHistorial = $('#contenedorHistorial').html();

        // Validar si hay registros reales en pantalla antes de proceder
        if (!contenidoHistorial || contenidoHistorial.includes('alert-info') || contenidoHistorial.includes('fa-spinner')) {
            Swal.fire('Atención', 'No hay registros válidos en el historial para generar un documento.', 'warning');
            return;
        }

        // Crear una ventana independiente del navegador para formatear la impresión limpia
        const popup = window.open('', '_blank', 'width=900,height=750');
        
        popup.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Historia Clínica - ${nombrePaciente}</title>
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
                <style>
                    body { background-color: #fff !important; color: #000 !important; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; padding: 25px; }
                    .card { border: 1px solid #ddd !important; box-shadow: none !important; margin-bottom: 20px !important; page-break-inside: avoid; }
                    .card-header { background-color: #f7f7f7 !important; border-bottom: 1px solid #ddd !important; }
                    .text-indigo { color: #6610f2 !important; }
                    .badge { border: 1px solid #000; color: #000 !important; background: transparent !important; }
                    .badge-success { background-color: #28a745 !important; color: #fff !important; border: none; }
                    .badge-danger { background-color: #dc3545 !important; color: #fff !important; border: none; }
                    .blockquote-footer { color: #333 !important; background-color: #f8f9fa !important; border: 1px solid #e9ecef; }
                    /* Agrega esto a la etiqueta <style> del string de impresión */
                    .card-teal { border-top: 3px solid #20c997 !important; }
                    .text-teal { color: #20c997 !important; }
                    .bg-teal { background-color: #20c997 !important; color: #fff !important; }
                    @media print {
                        .no-print { display: none !important; }
                        body { padding: 0; }
                    }
                </style>
            </head>
            <body>
                <div class="container-fluid">
                    <div class="row mb-4">
                        <div class="col-12 text-center">
                            <h2 class="font-weight-bold tracking-tight">REPORTE CONSOLIDADO DE HISTORIA CLÍNICA</h2>
                            <h4 class="text-secondary">Paciente: <strong>${nombrePaciente}</strong></h4>
                            <p class="small text-muted">Documento generado el: ${new Date().toLocaleDateString()} a las ${new Date().toLocaleTimeString()}</p>
                            <hr style="border-top: 2px solid #333;">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            ${contenidoHistorial}
                        </div>
                    </div>
                </div>
                
                <script>
                    window.onload = function() {
                        // Forzar una breve espera para garantizar que carguen los CSS externos
                        setTimeout(function() {
                            window.print();
                            window.close();
                        }, 500);
                    };
                <\/script>
            </body>
            </html>
        `);
        
        popup.document.close();
    });
});