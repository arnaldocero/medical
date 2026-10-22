$(document).ready(function () {
    // Solo en la pantalla de Gestión de Formatos
    if (!$('#tablaFormatos').length) return;

    const actionUrl = 'ajax/formatos_action.php';

    // Escapar texto para insertarlo en HTML
    const esc = (t) => $('<div>').text(t == null ? '' : t).html();

    // --- 1. LISTADO ---
    const tabla = $('#tablaFormatos').DataTable({
        ajax: { url: actionUrl, data: { action: 'listar' }, dataSrc: '' },
        columns: [
            {
                data: 'nombre',
                render: (d, t, row) => `<strong>${esc(d)}</strong>`
                    + (parseInt(row.es_predeterminado) === 1 ? ' <span class="badge badge-primary">Predeterminado</span>' : '')
                    + (row.descripcion ? `<br><small class="text-muted">${esc(row.descripcion)}</small>` : '')
            },
            {
                data: 'secciones',
                render: (d) => d.length
                    ? d.map(s => `<span class="badge badge-light border mr-1">${esc(s)}</span>`).join('')
                    : '<span class="text-muted small">Solo motivo y diagnóstico</span>'
            },
            { data: 'total_campos', className: 'text-center' },
            { data: 'total_medicos', className: 'text-center' },
            {
                data: 'estado', className: 'text-center',
                render: (d) => parseInt(d) === 1
                    ? '<span class="badge badge-success">Activo</span>'
                    : '<span class="badge badge-danger">Inactivo</span>'
            },
            {
                data: null, orderable: false, className: 'text-center text-nowrap',
                render: (d, t, row) => `
                    <button class="btn btn-warning btn-sm btnEditarFormato" data-id="${row.id}" title="Editar"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-info btn-sm btnMedicosFormato" data-id="${row.id}" data-nombre="${esc(row.nombre)}" title="Asignar médicos"><i class="fas fa-user-md"></i></button>
                    <button class="btn btn-sm ${parseInt(row.estado) === 1 ? 'btn-outline-danger' : 'btn-outline-success'} btnEstadoFormato"
                            data-id="${row.id}" data-estado="${parseInt(row.estado) === 1 ? 0 : 1}"
                            title="${parseInt(row.estado) === 1 ? 'Desactivar' : 'Activar'}">
                        <i class="fas ${parseInt(row.estado) === 1 ? 'fa-ban' : 'fa-check'}"></i>
                    </button>`
            }
        ],
        order: [],
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' }
    });

    // --- 2. CAMPOS PERSONALIZADOS (filas dinámicas) ---
    function agregarCampo(campo) {
        const fila = $($('#plantillaCampo').html());
        if (campo) {
            fila.find('.campo-id').val(campo.id);
            fila.find('.campo-etiqueta').val(campo.etiqueta);
            fila.find('.campo-tipo').val(campo.tipo);
            fila.find('.campo-opciones').val(campo.opciones || '');
            fila.find('.campo-obligatorio').prop('checked', parseInt(campo.obligatorio) === 1);
        }
        fila.find('.campo-opciones').prop('disabled', fila.find('.campo-tipo').val() !== 'lista');
        $('#cuerpoCampos').append(fila);
    }

    $('#btnAgregarCampo').on('click', () => agregarCampo(null));

    $('#cuerpoCampos')
        .on('change', '.campo-tipo', function () {
            $(this).closest('tr').find('.campo-opciones').prop('disabled', $(this).val() !== 'lista');
        })
        .on('click', '.btnQuitarCampo', function () { $(this).closest('tr').remove(); })
        .on('click', '.btnSubirCampo', function () {
            const fila = $(this).closest('tr');
            fila.prev().before(fila);
        })
        .on('click', '.btnBajarCampo', function () {
            const fila = $(this).closest('tr');
            fila.next().after(fila);
        });

    function limpiarModal() {
        $('#formFormato')[0].reset();
        $('#formato_id').val('');
        $('#cuerpoCampos').empty();
    }

    // --- 3. NUEVO / EDITAR ---
    $('#btnNuevoFormato').on('click', function () {
        limpiarModal();
        $('#tituloModalFormato').html('<i class="fas fa-file-signature"></i> Nuevo Formato');
        // Por defecto, las secciones de una consulta general
        ['signos_vitales', 'examen_fisico', 'plan_formula', 'anexos'].forEach(s => $('#sec_' + s).prop('checked', true));
        $('#modalFormato').modal('show');
    });

    $('#tablaFormatos').on('click', '.btnEditarFormato', function () {
        $.getJSON(actionUrl, { action: 'obtener', id: $(this).data('id') }, function (res) {
            if (res.status !== 'success') return Swal.fire('Atención', res.message, 'warning');
            const f = res.formato;
            limpiarModal();
            $('#tituloModalFormato').html('<i class="fas fa-edit"></i> Editar Formato');
            $('#formato_id').val(f.id);
            $('#formato_nombre').val(f.nombre);
            $('#formato_descripcion').val(f.descripcion || '');
            $('#formato_predeterminado').prop('checked', parseInt(f.es_predeterminado) === 1);
            f.secciones.forEach(s => $('#sec_' + s).prop('checked', true));
            f.campos.forEach(c => agregarCampo(c));
            $('#modalFormato').modal('show');
        });
    });

    // --- 4. GUARDAR ---
    $('#formFormato').on('submit', function (e) {
        e.preventDefault();

        // Serializar el formulario + campos personalizados en orden
        const datos = $(this).serializeArray();
        datos.push({ name: 'action', value: 'guardar' });
        $('#cuerpoCampos tr').each(function (i) {
            const fila = $(this);
            datos.push({ name: `campos[${i}][id]`, value: fila.find('.campo-id').val() });
            datos.push({ name: `campos[${i}][etiqueta]`, value: fila.find('.campo-etiqueta').val() });
            datos.push({ name: `campos[${i}][tipo]`, value: fila.find('.campo-tipo').val() });
            datos.push({ name: `campos[${i}][opciones]`, value: fila.find('.campo-opciones').val() });
            datos.push({ name: `campos[${i}][obligatorio]`, value: fila.find('.campo-obligatorio').is(':checked') ? 1 : 0 });
        });

        const btn = $('#btnGuardarFormato').prop('disabled', true);
        $.post(actionUrl, $.param(datos), function (res) {
            btn.prop('disabled', false);
            if (res.status === 'success') {
                $('#modalFormato').modal('hide');
                Swal.fire('¡Guardado!', res.message, 'success');
                tabla.ajax.reload(null, false);
            } else {
                Swal.fire('Atención', res.message, 'warning');
            }
        }, 'json').fail(() => {
            btn.prop('disabled', false);
            Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
        });
    });

    // --- 5. ACTIVAR / DESACTIVAR ---
    $('#tablaFormatos').on('click', '.btnEstadoFormato', function () {
        const id = $(this).data('id');
        const estado = $(this).data('estado');
        Swal.fire({
            title: estado ? '¿Activar el formato?' : '¿Desactivar el formato?',
            text: estado ? '' : 'Los médicos dejarán de verlo. Las historias ya registradas se conservan.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí',
            cancelButtonText: 'Cancelar'
        }).then((r) => {
            if (!r.isConfirmed) return;
            $.post(actionUrl, { action: 'cambiar_estado', id: id, estado: estado }, function (res) {
                Swal.fire(res.status === 'success' ? 'Listo' : 'Atención', res.message, res.status === 'success' ? 'success' : 'warning');
                tabla.ajax.reload(null, false);
            }, 'json');
        });
    });

    // --- 6. ASIGNAR MÉDICOS ---
    $('#tablaFormatos').on('click', '.btnMedicosFormato', function () {
        const id = $(this).data('id');
        $('#medicos_formato_id').val(id);
        $('#medicos_formato_nombre').text($(this).data('nombre'));
        $('#contenedorMedicosFormato').html('<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Cargando médicos...</div>');
        $('#modalMedicosFormato').modal('show');

        $.getJSON(actionUrl, { action: 'medicos', id: id }, function (res) {
            if (res.status !== 'success') return $('#contenedorMedicosFormato').html(`<p class="text-danger">${esc(res.message)}</p>`);
            if (!res.medicos.length) return $('#contenedorMedicosFormato').html('<p class="text-muted mb-0">No hay médicos activos en su sede.</p>');
            $('#contenedorMedicosFormato').html(res.medicos.map(m => `
                <div class="custom-control custom-checkbox mb-1">
                    <input class="custom-control-input" type="checkbox" name="medicos[]" id="fm_${m.id}" value="${m.id}" ${parseInt(m.asignado) ? 'checked' : ''}>
                    <label class="custom-control-label" for="fm_${m.id}">${esc(m.nombre)}
                        <small class="text-muted">${esc(m.especialidad || '')}</small></label>
                </div>`).join(''));
        });
    });

    $('#formMedicosFormato').on('submit', function (e) {
        e.preventDefault();
        $.post(actionUrl, $(this).serialize() + '&action=guardar_medicos', function (res) {
            if (res.status === 'success') {
                $('#modalMedicosFormato').modal('hide');
                Swal.fire('¡Asignado!', res.message, 'success');
                tabla.ajax.reload(null, false);
            } else {
                Swal.fire('Atención', res.message, 'warning');
            }
        }, 'json');
    });
});
