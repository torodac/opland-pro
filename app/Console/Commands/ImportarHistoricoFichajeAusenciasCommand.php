<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// Migración puntual (no recurrente) del histórico de fichajes y ausencias de
// gestion.opland.es (vacationmarbella_fichaje / vacationmarbella_ausencias) hacia
// vm_fichaje / vm_ausencias. Diseñado y acordado con el usuario en sesión.
//
// Regla acordada: solo se importa lo comprendido entre el 01/01/2026 y el 01/08/2026
// (fecha del cambio de sistema), y solo para usuarios actualmente activos en
// vm_usuarios (deleted=0) — los trabajadores dados de baja quedan fuera de este
// import puntual. Lo que ya hubiera en producción para esos usuarios+ventana
// (entradas orgánicas hechas en paralelo durante la transición) se retira
// (deleted=1) y la fila legada pasa a ser la activa, respetando el propio "deleted"
// que traiga cada fila del volcado de Hostinger. El resto de producción (fechas
// anteriores a 2026, o usuarios dados de baja) no se toca.
class ImportarHistoricoFichajeAusenciasCommand extends Command
{
    protected $signature   = 'vm:importar-historico-fichaje-ausencias
                                {--fichaje= : Ruta a vacationmarbella_fichaje_completo.sql}
                                {--ausencias= : Ruta a vacationmarbella_ausencias_completo.sql}
                                {--dry-run : No escribe nada, solo muestra el resumen}';
    protected $description = 'Importa el histórico de fichajes y ausencias de gestion.opland.es (una sola vez)';

    private const FECHA_INICIO_VENTANA = '2026-01-01';
    private const FECHA_CORTE          = '2026-08-01';

    // admin_users.id de trodriguez@opland.es — autor de la importación.
    private const ADMIN_IMPORT_ID = 1;

    // vacationmarbella_usuarios.id (legado) => vm_usuarios.id (PRO). Confirmado con el
    // usuario en sesión cruzando por email/nombre contra el volcado de Hostinger.
    private const USUARIOS_MAP = [
        73 => 1, 69 => 2, 182 => 3, 282 => 4, 278 => 5, 291 => 6, 313 => 7, 56 => 8,
        245 => 9, 79 => 10, 344 => 11, 196 => 12, 281 => 13, 337 => 14, 301 => 15,
        342 => 16, 343 => 17, 345 => 18, 348 => 19, 350 => 20, 310 => 21, 181 => 22,
        347 => 23, 57 => 24, 318 => 25, 147 => 26, 346 => 27, 349 => 28, 331 => 29,
        247 => 30, 68 => 31, 351 => 32, 259 => 34, 286 => 35, 302 => 36, 299 => 37,
        314 => 38, 60 => 39, 324 => 40, 326 => 41, 323 => 42, 305 => 43, 330 => 44,
        335 => 45, 339 => 46, 341 => 47, 322 => 48, 336 => 49, 353 => 50, 354 => 51,
    ];

    // vacationmarbella_ausencias_tipo.id => texto tal cual lo espera vm_ausencias.tipo
    // (opciones configuradas en admin_table_fields para la tabla "ausencias"). El tipo
    // 5 "Festivo" está obsoleto (deleted=1 en el propio catálogo legado) y no tiene
    // equivalente en el desplegable nuevo: se descarta.
    private const TIPO_MAP = [
        2 => 'Compensación',
        3 => 'Vacaciones',
        4 => 'Baja',
        6 => 'Asuntos propios',
        7 => 'Comp. festivo',
        8 => 'Comp. horas',
        9 => 'Absentismo',
    ];

    private bool $dryRun;
    private array $usuariosActivosProIds = [];

    public function handle(): int
    {
        $ficheroFichaje   = $this->option('fichaje');
        $ficheroAusencias = $this->option('ausencias');
        if (!$ficheroFichaje || !file_exists($ficheroFichaje)) {
            $this->error('Fichero de fichajes no encontrado: ' . $ficheroFichaje);
            return self::FAILURE;
        }
        if (!$ficheroAusencias || !file_exists($ficheroAusencias)) {
            $this->error('Fichero de ausencias no encontrado: ' . $ficheroAusencias);
            return self::FAILURE;
        }
        $this->dryRun = (bool) $this->option('dry-run');

        // Solo usuarios mapeados que HOY están activos (deleted=0) en vm_usuarios.
        $this->usuariosActivosProIds = DB::table('vm_usuarios')
            ->whereIn('id', array_values(self::USUARIOS_MAP))
            ->where('deleted', 0)
            ->pluck('id')
            ->all();
        $this->info('Usuarios activos dentro del mapeo: ' . count($this->usuariosActivosProIds) . ' de ' . count(self::USUARIOS_MAP));

        try {
            DB::transaction(function () use ($ficheroFichaje, $ficheroAusencias) {
                $this->importarFichajes($ficheroFichaje);
                $this->importarAusencias($ficheroAusencias);

                if ($this->dryRun) {
                    throw new \RuntimeException('__DRY_RUN_ROLLBACK__');
                }
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== '__DRY_RUN_ROLLBACK__') throw $e;
            $this->warn('[DRY-RUN] Rollback aplicado, no se ha escrito nada.');
        }

        return self::SUCCESS;
    }

    private function importarFichajes(string $fichero): void
    {
        $this->info('--- Fichajes ---');

        $retiradas = DB::table('vm_fichaje')
            ->where('deleted', 0)
            ->whereIn('control_user', $this->usuariosActivosProIds)
            ->where('fecha_fichaje', '>=', self::FECHA_INICIO_VENTANA)
            ->where('fecha_fichaje', '<', self::FECHA_CORTE)
            ->update(['deleted' => 1, 'updateuser' => self::ADMIN_IMPORT_ID, 'updatedat' => now()]);
        $this->info("Filas de producción retiradas (deleted=1) en [" . self::FECHA_INICIO_VENTANA . ", " . self::FECHA_CORTE . ") de usuarios activos: {$retiradas}");

        $filas = $this->parseInsertTuples($fichero, 'vacationmarbella_fichaje');
        $this->info("Filas leídas del volcado: " . count($filas));

        // Filtra por ventana de fechas y usuario mapeado+activo.
        $candidatas = [];
        $sinUsuario = [];
        foreach ($filas as $f) {
            [$id, $fecha, $controlUser, $horaInicio, $horaFin, $pausaDesde, $pausaHasta, $observacion,
                $nombre, $horasExtras, $kilometros, $diaFestivo, $turnoRotatorio, $deleted, $archived,
                $createuser, $createdat, $updateuser, $updatedat] = $f;

            if ($fecha === null || $fecha < self::FECHA_INICIO_VENTANA || $fecha >= self::FECHA_CORTE) continue;

            $idUsuario = $controlUser !== null ? (self::USUARIOS_MAP[(int) $controlUser] ?? null) : null;
            if (!$idUsuario || !in_array($idUsuario, $this->usuariosActivosProIds, true)) {
                $sinUsuario[(int) $controlUser] = true;
                continue;
            }

            $candidatas[] = [
                'legacy_id'    => (int) $id,
                'fecha'        => $fecha,
                'control_user' => $idUsuario,
                'hora_inicio'  => $horaInicio,
                'hora_fin'     => $horaFin,
                'pausa_inicio' => $pausaDesde,
                'pausa_fin'    => $pausaHasta,
                'observacion'  => $observacion,
                'trayecto'     => $nombre,
                'km'           => $kilometros,
                'festivo'      => (int) $diaFestivo,
                'deleted'      => (int) $deleted,
                'updatedat'    => $updatedat ?: $createdat,
            ];
        }

        // Dedupe intra-volcado: mismo (usuario, fecha) -> gana la que no tenga
        // deleted=1 y, en empate, la más reciente por updatedat/createdat.
        $porClave = [];
        foreach ($candidatas as $c) {
            $clave = $c['control_user'] . '|' . $c['fecha'];
            if (!isset($porClave[$clave])) {
                $porClave[$clave] = $c;
                continue;
            }
            $actual = $porClave[$clave];
            $mejor = $this->elegirMejorDuplicado($actual, $c);
            $porClave[$clave] = $mejor;
        }

        $creadas = 0; $yaExistian = 0;
        foreach ($porClave as $c) {
            $yaExiste = DB::table('vm_fichaje')->where('legacy_id', $c['legacy_id'])->exists();
            if ($yaExiste) { $yaExistian++; continue; }

            DB::table('vm_fichaje')->insert([
                'legacy_id'    => $c['legacy_id'],
                'nombre'       => $c['fecha'] . '_legado',
                'control_user' => $c['control_user'],
                'fecha_fichaje'=> $c['fecha'],
                'hora_inicio'  => $c['hora_inicio'],
                'hora_fin'     => $c['hora_fin'],
                'pausa_inicio' => $c['pausa_inicio'],
                'pausa_fin'    => $c['pausa_fin'],
                'festivo'      => $c['festivo'],
                'fuera_de_turno' => 0,
                'km'           => $c['km'],
                'trayecto'     => $c['trayecto'],
                'observacion'  => $c['observacion'],
                'deleted'      => $c['deleted'],
                'hidden'       => 0, 'blocked' => 0,
                'createuser'   => self::ADMIN_IMPORT_ID,
                'updateuser'   => self::ADMIN_IMPORT_ID,
                'createdat'    => now(),
                'updatedat'    => now(),
            ]);
            $creadas++;
        }

        $this->info("Fichajes creados: {$creadas} | ya existían (legacy_id repetido): {$yaExistian}");
        if ($sinUsuario) {
            $this->warn('control_user (legado) sin mapear: ' . implode(', ', array_keys($sinUsuario)));
        }
    }

    private function importarAusencias(string $fichero): void
    {
        $this->info('--- Ausencias ---');

        $retiradas = DB::table('vm_ausencias')
            ->where('deleted', 0)
            ->whereIn('id_usuarios', $this->usuariosActivosProIds)
            ->where('fecha_inicio', '<', self::FECHA_CORTE)
            ->where('fecha_fin', '>=', self::FECHA_INICIO_VENTANA)
            ->update(['deleted' => 1, 'updateuser' => self::ADMIN_IMPORT_ID, 'updatedat' => now()]);
        $this->info("Filas de producción retiradas (deleted=1) que tocan [" . self::FECHA_INICIO_VENTANA . ", " . self::FECHA_CORTE . ") de usuarios activos: {$retiradas}");

        $filas = $this->parseInsertTuples($fichero, 'vacationmarbella_ausencias');
        $this->info("Filas leídas del volcado: " . count($filas));

        $creadas = 0; $yaExistian = 0; $sinUsuario = []; $sinTipo = []; $descartadasTipo = 0;
        foreach ($filas as $f) {
            [$id, $nombre, $idTipo, $fechaInicio, $fechaFin, $anyoDevengo, $idUsuarios,
                $createdat, $createuser, $updatedat, $updateuser, $fichero_, $deleted, $comentario] = $f;

            // Se importa si el rango de la ausencia toca la ventana [01/01/2026, 01/08/2026).
            if ($fechaFin === null || $fechaInicio === null) continue;
            if ($fechaFin < self::FECHA_INICIO_VENTANA || $fechaInicio >= self::FECHA_CORTE) continue;

            $idUsuario = $idUsuarios !== null ? (self::USUARIOS_MAP[(int) $idUsuarios] ?? null) : null;
            if (!$idUsuario || !in_array($idUsuario, $this->usuariosActivosProIds, true)) {
                $sinUsuario[(int) $idUsuarios] = true;
                continue;
            }

            $tipo = self::TIPO_MAP[(int) $idTipo] ?? null;
            if (!$tipo) {
                if ((int) $idTipo === 5) { $descartadasTipo++; }
                else { $sinTipo[(int) $idTipo] = true; }
                continue;
            }

            $yaExiste = DB::table('vm_ausencias')->where('legacy_id', (int) $id)->exists();
            if ($yaExiste) { $yaExistian++; continue; }

            DB::table('vm_ausencias')->insert([
                'legacy_id'    => (int) $id,
                'nombre'       => mb_substr((string) $nombre, 0, 255),
                'id_usuarios'  => $idUsuario,
                'tipo'         => $tipo,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin'    => $fechaFin,
                'anyo_devengo' => $anyoDevengo,
                'comentario'   => $comentario ?: 'Migrado de gestion.opland.es (histórico).',
                'file_fichero' => null, // ruta del legado no accesible en este servidor
                'deleted'      => (int) $deleted,
                'hidden'       => 0, 'blocked' => 0,
                'createuser'   => self::ADMIN_IMPORT_ID,
                'updateuser'   => self::ADMIN_IMPORT_ID,
                'createdat'    => now(),
                'updatedat'    => now(),
            ]);
            $creadas++;
        }

        $this->info("Ausencias creadas: {$creadas} | ya existían (legacy_id repetido): {$yaExistian} | descartadas por tipo obsoleto (Festivo): {$descartadasTipo}");
        if ($sinUsuario) {
            $this->warn('id_usuarios (legado) sin mapear: ' . implode(', ', array_keys($sinUsuario)));
        }
        if ($sinTipo) {
            $this->warn('id_ausencias_tipo sin mapear: ' . implode(', ', array_keys($sinTipo)));
        }
    }

    private function elegirMejorDuplicado(array $a, array $b): array
    {
        if ($a['deleted'] !== $b['deleted']) {
            return $a['deleted'] === 0 ? $a : $b;
        }
        return ($b['updatedat'] ?? '') >= ($a['updatedat'] ?? '') ? $b : $a;
    }

    // Extrae las tuplas de un INSERT INTO `$tabla` (...) VALUES de un volcado
    // phpMyAdmin donde cada fila ocupa su propia línea. Devuelve cada fila como
    // array de valores en el orden original de columnas, ya tipados (int/string/null).
    private function parseInsertTuples(string $path, string $tabla): array
    {
        $handle = fopen($path, 'r');
        if (!$handle) return [];

        // El volcado trocea las tablas grandes en varias sentencias INSERT INTO
        // consecutivas: hay que recorrer todo el fichero, no solo la primera.
        $dentro = false;
        $filas = [];
        while (($linea = fgets($handle)) !== false) {
            if (!$dentro) {
                if (str_starts_with($linea, "INSERT INTO `{$tabla}`")) {
                    $dentro = true;
                }
                continue;
            }
            $linea = rtrim($linea, "\n\r");
            if ($linea === '') continue;
            if (!str_starts_with($linea, '(')) {
                // fin de este bloque VALUES; sigue buscando el siguiente INSERT INTO
                $dentro = false;
                continue;
            }
            $terminaSentencia = str_ends_with($linea, ';');
            $cuerpo = rtrim($linea, ",;");
            $cuerpo = substr($cuerpo, 1, -1); // quita paréntesis externos
            $filas[] = $this->tokenizarTupla($cuerpo);
            if ($terminaSentencia) $dentro = false;
        }
        fclose($handle);
        return $filas;
    }

    // Divide una tupla "1, 'texto, con comas', NULL, 3.5" respetando comillas y
    // escapes (\' y '') propios del formato de volcado de phpMyAdmin/MySQL.
    private function tokenizarTupla(string $cuerpo): array
    {
        $valores = [];
        $actual = '';
        $enString = false;
        $len = strlen($cuerpo);
        for ($i = 0; $i < $len; $i++) {
            $c = $cuerpo[$i];
            if ($enString) {
                if ($c === '\\' && $i + 1 < $len) {
                    $actual .= $c . $cuerpo[$i + 1];
                    $i++;
                    continue;
                }
                if ($c === "'") {
                    if ($i + 1 < $len && $cuerpo[$i + 1] === "'") {
                        $actual .= "'";
                        $i++;
                        continue;
                    }
                    $enString = false;
                    continue;
                }
                $actual .= $c;
                continue;
            }
            if ($c === "'") { $enString = true; continue; }
            if ($c === ',') { $valores[] = $this->castValor(trim($actual)); $actual = ''; continue; }
            $actual .= $c;
        }
        $valores[] = $this->castValor(trim($actual));
        return $valores;
    }

    private function castValor(string $token): int|string|null
    {
        if ($token === 'NULL') return null;
        if (preg_match('/^-?\d+$/', $token)) return (int) $token;
        return stripcslashes($token);
    }
}
