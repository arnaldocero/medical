<?php
/**
 * Cargador mínimo de variables de entorno desde el archivo .env (sin Composer).
 *
 * Formato del .env:
 *   CLAVE=valor
 *   CLAVE="valor con espacios"
 *   CLAVE='valor literal con $ ! } etc.'
 *   # comentario
 *
 * Las variables definidas en el servidor (panel del hosting) tienen prioridad sobre el .env.
 */

if (!function_exists('cargarEnv')) {

    function cargarEnv($ruta) {
        if (!is_readable($ruta)) {
            return false;
        }

        foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
            $linea = trim($linea);
            if ($linea === '' || $linea[0] === '#' || strpos($linea, '=') === false) {
                continue;
            }

            list($clave, $valor) = explode('=', $linea, 2);
            $clave = trim($clave);
            $valor = trim($valor);

            // Quitar comillas envolventes (simples o dobles)
            $largo = strlen($valor);
            if ($largo >= 2 && ($valor[0] === '"' || $valor[0] === "'") && $valor[$largo - 1] === $valor[0]) {
                $valor = substr($valor, 1, -1);
            }

            // No sobrescribir variables ya definidas en el entorno del servidor
            if (getenv($clave) === false && !isset($_ENV[$clave])) {
                $_ENV[$clave] = $valor;
            }
        }
        return true;
    }

    /**
     * Lee una variable de entorno, con valor por defecto si no existe.
     */
    function env($clave, $porDefecto = null) {
        if (isset($_ENV[$clave])) {
            return $_ENV[$clave];
        }
        $valor = getenv($clave);
        return $valor === false ? $porDefecto : $valor;
    }

    // El .env vive en la raíz del proyecto (un nivel arriba de /config)
    cargarEnv(dirname(__DIR__) . '/.env');
}
