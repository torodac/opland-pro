<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

// Comprobación común para los procesos que traen datos de un servicio externo.
//
// Por qué existe: en 2026 se nos escaparon dos averías largas por el mismo punto ciego.
//
//   1. Un fallo que solo se avisa con $this->error()/warn() va a la consola, y la salida del
//      cron va a /dev/null. El proceso termina "con éxito" y nadie se entera.
//   2. Peor todavía: un servicio puede responder 200 con datos perfectamente válidos que
//      nuestro código no sepa leer -- le cambió la forma a la respuesta. Cero errores, cero
//      avisos y cero datos. Así estuvo la planilla de liquidación del 20/08 al 01/10/2026.
//
// La conclusión de las dos es la misma: no basta con vigilar los errores, hay que vigilar la
// cosecha. Si un proceso que normalmente trae 65 propiedades o 190 tareas trae cero, eso es una
// avería aunque ninguna llamada haya fallado.
//
// Se mide en llamadas con respuesta UTILIZABLE, no en filas escritas: una ejecución con todo ya
// al día escribe cero filas y eso es perfectamente normal.
class SaludIntegraciones
{
    // Por debajo de esta proporción de llamadas utilizables se considera avería.
    private const UMBRAL = 0.5;

    /**
     * @param string $proceso    Nombre del comando, tal y como se invoca.
     * @param int    $intentos   Llamadas al servicio externo que se han hecho.
     * @param int    $utiles     De esas, cuántas devolvieron algo que se ha podido leer.
     * @param string $consecuencia Qué deja de funcionar, en lenguaje llano, para que quien lea
     *                             el informe diario sepa si le urge sin tener que investigar.
     */
    public static function comprobar(string $proceso, int $intentos, int $utiles, string $consecuencia): void
    {
        if ($intentos <= 0) {
            // Nada que pedir no es una avería: puede no haber nada en el rango de fechas.
            return;
        }

        $proporcion = $utiles / $intentos;
        if ($proporcion > self::UMBRAL) {
            return;
        }

        $detalle = $utiles === 0
            ? "ninguna de las {$intentos} llamadas devolvió datos utilizables"
            : "solo {$utiles} de {$intentos} llamadas devolvieron datos utilizables";

        Log::error("{$proceso}: {$detalle}. {$consecuencia} "
            . 'Suele ser un cambio en la API del proveedor o unas credenciales caducadas.');
    }
}
