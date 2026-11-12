$(document).ready(function () {
    // Solo en la pantalla de Auditoría
    if (!$('#tablaAuditoria').length) return;

    const url = 'ajax/auditoria_action.php';
    const esc = (t) => $('<div>').text(t == null ? '' : String(t)).html();
    const colorAccion = (a) => ({
        crear: 'success', crear_express: 'success', emitir: 'primary', despachar: 'info', editar: 'warning',
        desactivar: 'danger', eliminar: 'danger', desbloquear: 'secondary', restablecer_contrasena: 'dark'
    })[a] || 'light';

    // Opciones de los filtros (módulos y usuarios que tienen registros)
    $.post(url, { action: 'filtros' }, function (res) {
        if (res.status !== 'success') return;
        res.modulos.forEach(m => $('#aud_modulo').append(`<option value="${esc(m)}">${esc(m)}</option>`));
        res.usuarios.forEach(u => $('#aud_usuario').append(`<option value="${u.usuario_id}">${esc(u.usuario_nombre)}</option>`));
    }, 'json');

    const tabla = $('#tablaAuditoria').DataTable({
        ajax: {
            url: url, type: 'POST',
            data: (d) => Object.assign(d, { action: 'listar' }, Object.fromEntries(new FormData(document.getElementById('formFiltrosAuditoria')))),
            dataSrc: (json) => json.data || []
        },
        columns: [
            { data: 'fecha' },
            { data: 'usuario_nombre', render: (d) => esc(d) },
            { data: 'modulo', render: (d) => `<span class="badge badge-light border">${esc(d)}</span>` },
            { data: 'accion', render: (d) => `<span class="badge badge-${colorAccion(d)}">${esc(d)}</span>` },
            { data: 'descripcion', render: (d) => esc(d) },
            { data: 'nombre_centro', render: (d) => d ? esc(d) : '<span class="text-muted small">Todas / general</span>' },
            { data: 'ip', render: (d) => `<small class="text-muted">${esc(d)}</small>` },
            {
                data: null, orderable: false, className: 'text-center',
                render: (d, t, row) => parseInt(row.tiene_detalle) === 1
                    ? `<button class="btn btn-xs btn-outline-primary btnDetalleAud" data-id="${row.id}" title="Ver antes / después"><i class="fas fa-search-plus"></i></button>` : ''
            }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' }
    });

    $('#formFiltrosAuditoria').on('submit', function (e) {
        e.preventDefault();
        tabla.ajax.reload();
    });

    // Detalle: tabla campo por campo con los cambios resaltados
    $('#tablaAuditoria').on('click', '.btnDetalleAud', function () {
        $('#cuerpoDetalleAuditoria').html('<div class="text-center p-4"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>');
        $('#modalDetalleAuditoria').modal('show');
        $.post(url, { action: 'detalle', id: $(this).data('id') }, function (res) {
            if (res.status !== 'success') return $('#cuerpoDetalleAuditoria').html(`<div class="alert alert-warning">${esc(res.message)}</div>`);
            const r = res.registro;
            const filas = res.campos.map(c => `
                <tr class="${c.cambio ? 'table-warning' : ''}">
                    <td class="font-weight-bold small">${esc(c.campo)}</td>
                    <td class="small text-break">${c.antes === null ? '<span class="text-muted">—</span>' : esc(c.antes)}</td>
                    <td class="small text-break">${c.despues === null ? '<span class="text-muted">—</span>' : esc(c.despues)}</td>
                </tr>`).join('');
            $('#cuerpoDetalleAuditoria').html(`
                <p class="mb-1"><strong>${esc(r.descripcion)}</strong></p>
                <p class="text-muted small">${esc(r.fecha)} · ${esc(r.usuario_nombre)} · ${esc(r.nombre_centro || 'Todas / general')} · IP ${esc(r.ip)} · Registro #${esc(r.registro_id)}</p>
                <table class="table table-sm table-bordered">
                    <thead class="thead-light"><tr><th style="width: 22%;">Campo</th><th>Antes</th><th>Después</th></tr></thead>
                    <tbody>${filas || '<tr><td colspan="3" class="text-muted">Sin datos de detalle.</td></tr>'}</tbody>
                </table>
                <small class="text-muted"><span class="badge badge-warning">&nbsp;</span> Campos que cambiaron. Las contraseñas y tokens nunca se guardan.</small>`);
        }, 'json');
    });
});
