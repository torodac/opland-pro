<?php

namespace App\Services;

/**
 * Validación de orden horario compartida entre la PWA, el formulario
 * personalizado de fichaje y la ficha genérica. Permite igualdad en los
 * límites (entrada <= inicio_pausa <= fin_pausa <= salida).
 */
class FichajeValidator
{
    public static function validarHorario(?string $inicio, ?string $fin, ?string $pausaIni, ?string $pausaFin): ?string
    {
        $toMin = function (?string $t): ?int {
            if (!$t) return null;
            $partes = explode(':', $t);
            return ((int) $partes[0]) * 60 + ((int) ($partes[1] ?? 0));
        };

        $inicioMin = $toMin($inicio);
        $finMin    = $toMin($fin);
        $pausaIMin = $toMin($pausaIni);
        $pausaFMin = $toMin($pausaFin);

        if ($inicioMin !== null && $finMin !== null && $finMin < $inicioMin) {
            return 'La salida no puede ser anterior a la entrada';
        }
        if ($pausaIMin !== null && $pausaFMin !== null && $pausaFMin < $pausaIMin) {
            return 'El fin de pausa no puede ser anterior al inicio de pausa';
        }
        if ($pausaIMin !== null && $inicioMin !== null && $pausaIMin < $inicioMin) {
            return 'El inicio de pausa no puede ser anterior a la entrada';
        }
        if ($pausaIMin !== null && $finMin !== null && $pausaIMin > $finMin) {
            return 'El inicio de pausa no puede ser posterior a la salida';
        }
        if ($pausaFMin !== null && $inicioMin !== null && $pausaFMin < $inicioMin) {
            return 'El fin de pausa no puede ser anterior a la entrada';
        }
        if ($pausaFMin !== null && $finMin !== null && $pausaFMin > $finMin) {
            return 'El fin de pausa no puede ser posterior a la salida';
        }

        return null;
    }
}
