/**
 * Convierte a MAYÚSCULAS los campos de texto del sistema (nombres, descripciones, observaciones...).
 *
 * - Se aplica mientras el usuario escribe y otra vez justo antes de enviar cada formulario,
 *   así lo que se guarda en la base de datos queda en mayúsculas.
 * - Funciona también con campos creados dinámicamente (modales, filas agregadas por JS).
 * - NO se aplica a correos, usuarios, contraseñas, buscadores ni enlaces.
 * - Para excluir un campo puntual, agréguele la clase "no-mayusculas".
 */
(function () {
    var SELECTOR = 'input[type="text"], input:not([type]), textarea';
    var EXCLUIR = /(email|correo|usuario|user|password|clave|token|url|link|search|buscar)/i;

    function aplica(el) {
        if (!el || !el.matches || !el.matches(SELECTOR)) return false;
        if (el.readOnly || el.disabled) return false;
        if (el.classList.contains('no-mayusculas') || el.closest('.no-mayusculas')) return false;
        // Buscadores de Select2 y DataTables
        if (el.classList.contains('select2-search__field') || el.closest('.dataTables_filter')) return false;
        return !EXCLUIR.test((el.name || '') + ' ' + (el.id || ''));
    }

    function convertir(el) {
        var valor = el.value;
        var mayus = valor.toLocaleUpperCase('es');
        if (valor === mayus) return;

        // Conservar la posición del cursor al convertir mientras se escribe
        var inicio = el.selectionStart, fin = el.selectionEnd;
        el.value = mayus;
        if (document.activeElement === el && inicio !== null && mayus.length === valor.length) {
            el.setSelectionRange(inicio, fin);
        }
    }

    // Mientras se escribe o se pega texto
    document.addEventListener('input', function (e) {
        if (aplica(e.target)) convertir(e.target);
    });

    // Antes de enviar: fase de captura, se ejecuta antes que los manejadores de cada formulario
    // (que suelen usar $(form).serialize()), e incluye valores cargados desde la BD al editar.
    document.addEventListener('submit', function (e) {
        var campos = e.target.querySelectorAll(SELECTOR);
        for (var i = 0; i < campos.length; i++) {
            if (aplica(campos[i])) convertir(campos[i]);
        }
    }, true);
})();
