$(document).ready(function () {
    const actionUrl = 'ajax/dian_resolucion_action.php';
    let tabla;

    // Inicialización de la Tabla con DataTables
    function inicializarTabla() {
        tabla = $('#tablaResoluciones').DataTable({
            "ajax": {
                "url": actionUrl,
                "type": "POST",
                "data": { action: 'listar' }
            },
            "columns": [
                { 
                    "data": null,
                    "render": function(data) {
                        return `<strong>Prefijo: ${data.prefijo ? data.prefijo : 'N/A'}</strong><br><small class="text-muted">Res: ${data.numero_resolucion}</small>`;
                    }
                },
                {
                    "data": null,
                    "render": function(data) {
                        return `Desde: <b>${data.numero_inicial}</b><br>Hasta: <b>${data.numero_final}</b>`;
                    }
                },
                {
                    "data": null,
                    "render": function(data) {
                        // Calcular porcentaje consumido
                        let totalNumeros = data.numero_final - data.numero_inicial + 1;
                        let consumidos = data.consecutivo_actual - data.numero_inicial + 1;
                        if(consumidos < 0) consumidos = 0;
                        
                        let porcentaje = Math.round((consumidos / totalNumeros) * 100);
                        if(porcentaje > 100) porcentaje = 100;

                        let barraColor = 'bg-success';
                        if(porcentaje > 85) barraColor = 'bg-danger';
                        else if(porcentaje > 60) barraColor = 'bg-warning';

                        return `
                            <div>Sig. Consecutivo: <b>${parseInt(data.consecutivo_actual) + 1}</b></div>
                            <div class="progress progress-sm rounded shadow-sm">
                                <div class="progress-bar ${barraColor}" style="width: ${porcentaje}%" role="progressbar"></div>
                            </div>
                            <small class="text-muted">${porcentaje}% Consumido (${consumidos}/${totalNumeros})</small>
                        `;
                    }
                },
                { 
                    "data": null,
                    "render": function(data) {
                        return `<span>${data.fecha_vencimiento}</span><br><small class="text-muted">${data.vigencia_meses} Meses vig.</small>`;
                    }
                },
                { 
                    "data": "estado",
                    "render": function(estado) {
                        let badge = {
                            'activa': '<span class="badge badge-success"><i class="fas fa-check-circle"></i> Activa</span>',
                            'inactiva': '<span class="badge badge-secondary">Inactiva</span>',
                            'agotada': '<span class="badge badge-danger">Agotada</span>',
                            'vencida': '<span class="badge badge-warning">Vencida</span>'
                        };
                        return badge[estado] || estado;
                    }
                },
                {
                    "data": null,
                    "render": function(data) {
                        if (data.estado === 'inactiva') {
                            return `<button type="button" class="btn btn-xs btn-success btnActivar" data-id="${data.id}">
                                        <i class="fas fa-power-off"></i> Activar
                                    </button>`;
                        }
                        if (data.estado === 'activa') {
                            return `<button type="button" class="btn btn-xs btn-secondary btnDesactivar" data-id="${data.id}">
                                        <i class="fas fa-ban"></i> Pausar
                                    </button>`;
                        }
                        return `<button class="btn btn-xs btn-light text-muted" disabled>Sin acciones</button>`;
                    }
                }
            ],
            "responsive": true,
            "autoWidth": false,
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json"
            }
        });
    }

    inicializarTabla();

    // Evento: Guardar nueva resolución
    $('#formResolucion').on('submit', function (e) {
        e.preventDefault();

        $.ajax({
            url: actionUrl,
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    Swal.fire('¡Éxito!', res.message, 'success');
                    $('#formResolucion')[0].reset();
                    tabla.ajax.reload();
                } else {
                    Swal.fire('Error de validación', res.message, 'error');
                }
            },
            error: function () {
                Swal.fire('Error Crítico', 'No se pudo procesar la solicitud en el servidor.', 'error');
            }
        });
    });

    // Evento: Activar una resolución del listado
    $(document).on('click', '.btnActivar', function () {
        const id = $(this).data('id');
        cambiarEstadoResolucion(id, 'activa');
    });

    // Evento: Pausar/Inactivar una resolución
    $(document).on('click', '.btnDesactivar', function () {
        const id = $(this).data('id');
        cambiarEstadoResolucion(id, 'inactiva');
    });

    function cambiarEstadoResolucion(id, nuevoEstado) {
        let textoAlerta = nuevoEstado === 'activa' 
            ? "Esto pausará la resolución que se encuentre vigente actualmente." 
            : "¿Desea desactivar esta resolución temporalmente?";

        Swal.fire({
            title: '¿Confirmar cambio de estado?',
            text: textoAlerta,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, cambiar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: actionUrl,
                    type: 'POST',
                    data: { action: 'cambiar_estado', id: id, estado: nuevoEstado },
                    dataType: 'json',
                    success: function (res) {
                        if (res.status === 'success') {
                            Swal.fire('Actualizado', res.message, 'success');
                            tabla.ajax.reload();
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    }
                });
            }
        });
    }
});