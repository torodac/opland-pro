<?php

namespace App\Console\Commands;

use App\Mail\InformeFallosDiarioMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class AdminInformeFallosDiarioCommand extends Command
{
    protected $signature   = 'admin:informe-fallos-diario {--fecha= : Fecha YYYY-MM-DD a revisar, por defecto ayer}';
    protected $description = 'Revisa storage/logs/laravel.log en busca de errores/warnings del día anterior y envía un correo en lenguaje llano si encuentra alguno';

    // Todo lo que se registra con Log::warning()/error()/critical() en cualquier comando o
    // controlador de la app pasa por aquí (canal "stack" -> single -> storage/logs/laravel.log),
    // así que no hace falta listar cada comando por separado -- ver el caso real que motivó esto:
    // IcneaSyncReservationsCommand llevaba 18 días fallando con "Wrong API key" y solo se detectó
    // preguntando manualmente por una reserva que faltaba.
    private const NIVELES = ['ERROR', 'WARNING', 'CRITICAL', 'ALERT', 'EMERGENCY'];

    public function handle(): int
    {
        $fecha = $this->option('fecha') ?: now()->subDay()->toDateString();
        $path  = storage_path('logs/laravel.log');

        if (!is_file($path)) {
            $this->info("No existe {$path}, nada que revisar.");
            return self::SUCCESS;
        }

        $lineas = $this->leerLineasDelDia($path, $fecha);
        if (empty($lineas)) {
            $this->info("Sin errores/warnings el {$fecha}.");
            return self::SUCCESS;
        }

        $hallazgos = $this->traducir($lineas);

        $destino = env('MAIL_INFORME_FALLOS_TO', 'trodriguez@opland.es');
        Mail::to($destino)->send(new InformeFallosDiarioMail($fecha, $hallazgos));

        $this->info("Enviado informe de fallos del {$fecha}: " . count($hallazgos) . ' hallazgo(s) a partir de ' . count($lineas) . ' línea(s).');

        return self::SUCCESS;
    }

    /** @return array<int, array{timestamp:string,nivel:string,mensaje:string}> */
    private function leerLineasDelDia(string $path, string $fecha): array
    {
        $nivelesPattern = implode('|', self::NIVELES);
        $lineas = [];

        $handle = fopen($path, 'r');
        while (($linea = fgets($handle)) !== false) {
            if (!str_starts_with($linea, "[{$fecha}")) {
                continue;
            }
            if (!preg_match('/^\[([^\]]+)\]\s+\S+\.(' . $nivelesPattern . '):\s*(.*)$/', $linea, $m)) {
                continue;
            }
            [, $timestamp, $nivel, $mensaje] = $m;
            $lineas[] = ['timestamp' => $timestamp, 'nivel' => $nivel, 'mensaje' => trim($mensaje)];
        }
        fclose($handle);

        return $lineas;
    }

    /**
     * Convierte las líneas técnicas del log en hallazgos en lenguaje llano, uno por asunto real
     * (no uno por línea). Va consumiendo líneas según las reconoce; lo que sobra al final se
     * describe de forma genérica pero sin volcar trazas técnicas.
     *
     * @param array<int, array{timestamp:string,nivel:string,mensaje:string}> $lineas
     * @return array<int, array{titulo:string, cuerpo:string}>
     */
    private function traducir(array $lineas): array
    {
        $pendientes = $lineas;
        $hallazgos  = [];

        // 1) Icnea: reservas -- clave de acceso rechazada.
        $icneaApiKey = $this->extraer($pendientes, fn ($l) =>
            str_contains($l['mensaje'], 'IcneaSyncReservations') && str_contains($l['mensaje'], 'Wrong API key'));
        if ($icneaApiKey) {
            $n = count($icneaApiKey);
            $hallazgos[] = [
                'titulo' => 'Las reservas de Icnea no se están sincronizando',
                'cuerpo' => "El proceso que trae las reservas nuevas y sus cambios desde Icnea falló hoy en las {$n} propiedades que intentó consultar: todas devuelven que la clave de acceso es incorrecta. Mientras esto no se arregle, ninguna reserva creada o modificada en Icnea llegará a Opland. Hace falta pedir a Icnea una clave de acceso válida para este proceso.",
            ];
        }

        // 2) Pantallas huérfanas (aviso rutinario semanal, no crítico).
        $huerfanas = $this->extraer($pendientes, fn ($l) =>
            str_contains($l['mensaje'], 'audit-orphan-screens') && str_contains($l['mensaje'], 'huerfanas'));
        // El propio Laravel registra además un "Scheduled command ... failed" para este mismo
        // comando, porque devuelve un código distinto de éxito a propósito cuando encuentra algo
        // que avisar -- se absorbe aquí para no duplicar el aviso.
        $huerfanasFallo = $this->extraer($pendientes, fn ($l) =>
            str_contains($l['mensaje'], 'audit-orphan-screens') && str_starts_with($l['mensaje'], 'Scheduled command'));
        if ($huerfanas) {
            $etiquetas = [];
            foreach ($huerfanas as $l) {
                if (preg_match('/(\{.*\})\s*$/', $l['mensaje'], $mj)) {
                    $data = json_decode($mj[1], true);
                    foreach ($data['huerfanas'] ?? [] as $h) {
                        $props = $h['stdClass'] ?? $h;
                        if (!empty($props['label'])) {
                            $etiquetas[] = $props['label'];
                        }
                    }
                }
            }
            $n = count($etiquetas) ?: count($huerfanas);
            $listado = $etiquetas ? ('"' . implode('", "', array_slice($etiquetas, 0, 6)) . '"' . (count($etiquetas) > 6 ? '…' : '')) : '';
            $hallazgos[] = [
                'titulo' => 'Hay pantallas sin configurar en /config/projects',
                'cuerpo' => "La revisión semanal encontró {$n} pantalla(s) que funcionan por URL directa pero no aparecen en /config/projects" . ($listado ? ": {$listado}" : '') . '. No es urgente — es un aviso rutinario para darlas de alta cuando convenga.',
            ];
        }

        // 3) Cualquier otro "Scheduled command ... failed" -- un comando programado no terminó bien.
        $restoScheduled = $this->extraer($pendientes, fn ($l) => str_starts_with($l['mensaje'], 'Scheduled command'));
        foreach ($this->agruparPorComandoProgramado($restoScheduled) as $cmd => $veces) {
            $hallazgos[] = [
                'titulo' => "El proceso programado \"{$cmd}\" no terminó bien",
                'cuerpo' => "Se intentó ejecutar automáticamente {$veces} vez/veces y no terminó correctamente. Puede que necesite revisión manual.",
            ];
        }

        // 4) Lo que quede: agrupar de forma genérica, sin volcar detalle técnico.
        $resto = [];
        foreach ($pendientes as $l) {
            $proceso = preg_match('/^([A-Za-z][A-Za-z0-9:_-]*)/', $l['mensaje'], $pm) ? $pm[1] : 'Otro proceso';
            $resto[$proceso][] = $l;
        }
        foreach ($resto as $proceso => $ls) {
            $n = count($ls);
            $ejemplo = mb_substr($ls[0]['mensaje'], 0, 160);
            $hallazgos[] = [
                'titulo' => "\"{$proceso}\" registró {$n} aviso(s) técnico(s) que aún no reconozco en detalle",
                'cuerpo' => "Ejemplo: \"{$ejemplo}\"" . (mb_strlen($ls[0]['mensaje']) > 160 ? '…' : '') . ' Dímelo y lo reviso contigo con más detalle.',
            ];
        }

        return $hallazgos;
    }

    /**
     * Saca de $pendientes (por referencia) las líneas que cumplen $filtro y las devuelve.
     * @param array<int, array{timestamp:string,nivel:string,mensaje:string}> $pendientes
     */
    private function extraer(array &$pendientes, \Closure $filtro): array
    {
        $encontradas = [];
        $resto = [];
        foreach ($pendientes as $l) {
            if ($filtro($l)) {
                $encontradas[] = $l;
            } else {
                $resto[] = $l;
            }
        }
        $pendientes = $resto;
        return $encontradas;
    }

    /** @return array<string,int> nombre del comando => nº de veces que falló */
    private function agruparPorComandoProgramado(array $lineas): array
    {
        $conteo = [];
        foreach ($lineas as $l) {
            if (preg_match("/Scheduled command \[.*?'artisan'\s+([a-z0-9:_-]+)\]/i", $l['mensaje'], $m)) {
                $conteo[$m[1]] = ($conteo[$m[1]] ?? 0) + 1;
            }
        }
        return $conteo;
    }
}
