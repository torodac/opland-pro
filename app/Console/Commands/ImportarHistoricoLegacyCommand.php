<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

// Migración puntual (no recurrente) del export de tareas+imputaciones del sistema legado
// gestion.opland.es hacia vm_tareas_mantenimiento/vm_tareas_limpieza/vm_imputaciones.
// Diseñado y acordado con el usuario en sesión: ver DOC_TECNICO.md para el detalle del análisis.
class ImportarHistoricoLegacyCommand extends Command
{
    protected $signature   = 'vm:importar-historico-legacy
                                {--file= : Ruta al fichero .xlsx exportado de gestion.opland.es}
                                {--dry-run : No escribe nada, solo muestra el resumen}';
    protected $description = 'Importa el histórico de tareas+imputaciones exportado de gestion.opland.es (una sola vez)';

    // Overrides acordados explícitamente con el usuario para nombres que no resuelven por
    // coincidencia exacta (o que resolverían mal/ambiguo sin esta lista).
    private const PROPIEDADES_OVERRIDE = [
        'villa campanario' => 100, // 2 registros duplicados en BD, ambos inactivos; éste tiene la licencia turística correcta (VUT/MA/89259)
        'white orchid'     => 113, // = Villa Cartuja, confirmado por licencia turística (VUT/MA/95443)
        'villa address'    => 306, // = Villa The Address
    ];

    private const USUARIOS_OVERRIDE = [
        'externa'         => 4,  // Selian S.L.
        'mohamed'         => 7,  // Mohammed (doble m)
        'miram ruiz'      => 16, // typo de "Miriam Ruiz"
        'miriam'          => 15, // Miriam Caballero (NO Miriam Ruiz - confirmado por CSV legado)
        'riccardo'        => 10, // Riccardo Abbene
        'alberto'         => 9,  // Alberto Fernández Díaz
        'nabila'          => 3,  // Nabila Al Sayed
        'auxi'            => 22, // Auxiliadora Alba Guerrero
        // Trabajadores dados de baja en el sistema legado, creados como vm_usuarios deleted=1
        // específicamente para esta migración (ver sesión): sin cuenta de acceso (admin_user_id null).
        'josé javier'       => 34, // José Javier Núñez
        'estefania gonzalez'=> 35, // Estefanya Gonzalez
        'saida el asri'     => 36,
        'rosie'             => 37, // Rosie Despi
        'andres nicolas'    => 38,
        'griselda'          => 39, // Griselda Veronica Paez Barreto
        'simone'            => 40, // Siomone
        'ronald'            => 41,
        'sehaam'            => 42,
        'monica andrea'     => 43,
        'nayari'            => 44,
        'yassine'           => 45,
        'angela'            => 46,
        'samara'            => 47,
        'rafael'            => 48, // rafaelcorreal@vm.es (de entre 3 "Rafael" del legado, confirmado por el usuario)
        'lucia'             => 49, // luciabernal@vm.es (de entre 2 "Lucia" del legado, confirmado por el usuario)
    ];

    // Filas cuyo trabajador es este valor se descartan enteras (cuenta de pruebas del sistema legado).
    private const TRABAJADOR_DESCARTAR = ['usuario prueba opland'];

    // legacy_task_id => fecha correcta ('Y-m-d'). Solo la fila con año corrupto ("206" en vez de
    // "2026"); la reconstrucción vía Date::excelToDateTimeObject() para un serial tan fuera de rango
    // no es fiable (desfase de 1 día comprobado), así que se fija a mano usando la fecha real que
    // aparece en el propio texto de la tarea ("14/06 mando garage cambiar pilas").
    private const FECHA_OVERRIDE = [12619 => '2026-06-14'];

    private bool $dryRun;
    private array $propiedadesMap = [];
    private array $usuariosMap = [];

    public function handle(): int
    {
        $file = $this->option('file');
        if (!$file || !file_exists($file)) {
            $this->error('Fichero no encontrado: ' . $file);
            return self::FAILURE;
        }
        $this->dryRun = (bool) $this->option('dry-run');

        $this->propiedadesMap = DB::table('vm_propiedades')->select('id', DB::raw('LOWER(nombre) as clave'))->get()->pluck('id', 'clave')->all();
        $this->usuariosMap    = DB::table('vm_usuarios')->select('id', DB::raw('LOWER(nombre) as clave'))->get()->pluck('id', 'clave')->all();

        $spreadsheet = IOFactory::load($file);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);

        $hoy = now()->startOfDay();
        $stats = [
            'total' => 0, 'creadas' => 0, 'ya_existian' => 0, 'descartadas_prueba' => 0,
            'descartadas_futuras' => 0, 'imputaciones_creadas' => 0, 'sin_propiedad' => 0, 'sin_usuario' => 0,
        ];
        $sinPropiedadNombres = [];
        $sinUsuarioNombres = [];

        try {
        DB::transaction(function () use ($rows, $hoy, &$stats, &$sinPropiedadNombres, &$sinUsuarioNombres) {
            for ($i = 1; $i < count($rows); $i++) {
                $row = $rows[$i];
                $url = trim((string) ($row[0] ?? ''));
                if ($url === '') continue;
                $stats['total']++;

                [$tipo, $legacyId] = $this->parseUrl($url);
                if (!$tipo || !$legacyId) continue;

                $trabajador = trim((string) ($row[7] ?? ''));
                if (in_array(mb_strtolower($trabajador), self::TRABAJADOR_DESCARTAR, true)) {
                    $stats['descartadas_prueba']++;
                    continue;
                }

                $fechaPrevista = isset(self::FECHA_OVERRIDE[$legacyId])
                    ? \Illuminate\Support\Carbon::parse(self::FECHA_OVERRIDE[$legacyId])
                    : $this->parseFecha($row[4] ?? null, $legacyId);
                if ($fechaPrevista && $fechaPrevista->gt($hoy)) {
                    $stats['descartadas_futuras']++;
                    continue;
                }

                $tabla = $tipo === 'mantenimiento' ? 'vm_tareas_mantenimiento' : 'vm_tareas_limpieza';
                $yaExiste = DB::table($tabla)->where('legacy_task_id', $legacyId)->exists();
                if ($yaExiste) {
                    $stats['ya_existian']++;
                    continue;
                }

                $inmueble = trim((string) ($row[2] ?? ''));
                $idPropiedad = $this->resolverPropiedad($inmueble);
                if (!$idPropiedad && $inmueble !== '') {
                    $stats['sin_propiedad']++;
                    $sinPropiedadNombres[$inmueble] = true;
                }

                $idUsuario = $trabajador !== '' ? $this->resolverUsuario($trabajador) : null;
                if (!$idUsuario && $trabajador !== '') {
                    $stats['sin_usuario']++;
                    $sinUsuarioNombres[$trabajador] = true;
                }

                $nombreTarea = trim((string) ($row[3] ?? ''));
                $fechaImputacion = $this->parseFecha($row[5] ?? null, $legacyId);
                $minutos = $this->parseTiempo((string) ($row[6] ?? ''));
                $tipoLimpieza = trim((string) ($row[9] ?? ''));
                $reserva = trim((string) ($row[8] ?? ''));
                [$codigoReserva, $idReserva] = $this->resolverReserva($reserva);

                $datosComunes = [
                    'nombre'             => mb_substr($nombreTarea, 0, 255) ?: "Tarea legado #{$legacyId}",
                    'descripcion'        => $nombreTarea . ($reserva !== '' && $tipo === 'mantenimiento' ? " [Reserva: {$reserva}]" : ''),
                    'fecha_planificada'  => $fechaPrevista?->toDateString(),
                    'fecha_finalizacion' => $fechaImputacion?->toDateString(),
                    'control_user'       => $idUsuario ? json_encode([(int) $idUsuario]) : json_encode([]),
                    'id_propiedades'     => $idPropiedad,
                    'legacy_task_id'     => $legacyId,
                    'deleted'            => 0, 'hidden' => 0, 'blocked' => 0,
                    'createdat'          => now(), 'updatedat' => now(),
                    'comentario'         => 'Migrado de gestion.opland.es (histórico).',
                ];

                if ($tipo === 'mantenimiento') {
                    $idTarea = DB::table('vm_tareas_mantenimiento')->insertGetId($datosComunes);
                } else {
                    $idTarea = DB::table('vm_tareas_limpieza')->insertGetId(array_merge($datosComunes, [
                        'Tipo'        => $tipoLimpieza ?: null,
                        'id_reservas' => $idReserva,
                    ]));
                }
                $stats['creadas']++;

                if ($minutos !== null && $minutos > 0 && $idUsuario) {
                    DB::table('vm_imputaciones')->insert([
                        'tipo'             => $tipo,
                        'id_tarea'         => $idTarea,
                        'id_usuario'       => $idUsuario,
                        'duracion'         => $minutos,
                        'tiempo'           => sprintf('%02d:%02d:00', intdiv($minutos, 60), $minutos % 60),
                        'estado'           => 'finalizada',
                        'fecha_imputacion' => ($fechaImputacion ?? $fechaPrevista ?? now())->toDateString(),
                        'observacion'      => 'Migrado de gestion.opland.es (histórico).',
                        'createdat'        => now(),
                        'updatedat'        => now(),
                    ]);
                    $stats['imputaciones_creadas']++;
                }
            }

            if ($this->dryRun) {
                throw new \RuntimeException('__DRY_RUN_ROLLBACK__');
            }
        });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== '__DRY_RUN_ROLLBACK__') throw $e;
        }

        $this->info(($this->dryRun ? '[DRY-RUN] ' : '') . 'Resumen: ' . json_encode($stats, JSON_UNESCAPED_UNICODE));
        if ($sinPropiedadNombres) $this->warn('Inmuebles sin resolver: ' . implode(', ', array_keys($sinPropiedadNombres)));
        if ($sinUsuarioNombres) $this->warn('Trabajadores sin resolver: ' . implode(', ', array_keys($sinUsuarioNombres)));

        return self::SUCCESS;
    }

    // [tipo, legacyId] a partir de ".../tareas_mantenimiento/12619" o ".../tareas_limpieza/16213"
    private function parseUrl(string $url): array
    {
        if (preg_match('#/tareas_(mantenimiento|limpieza)/(\d+)#', $url, $m)) {
            return [$m[1], (int) $m[2]];
        }
        return [null, null];
    }

    // toArray(..., formatData:false) devuelve las fechas como número de serie de Excel en bruto
    // (float), no como texto -- hay que convertirlas explícitamente, no vale Carbon::parse() sobre
    // el string del float (y además evita la ambigüedad DD/MM vs MM/DD de parsear una fecha
    // formateada como texto). Corrige también la fila con año "206" en vez de "2026" (error de
    // tecleo aislado en el sistema original, legacy_task_id 12619).
    private function parseFecha($valor, int $legacyId): ?\Illuminate\Support\Carbon
    {
        if ($valor === null || $valor === '') return null;
        try {
            if (is_numeric($valor)) {
                $fecha = \Illuminate\Support\Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $valor));
            } elseif ($valor instanceof \DateTimeInterface) {
                $fecha = \Illuminate\Support\Carbon::instance($valor);
            } else {
                $fecha = \Illuminate\Support\Carbon::parse((string) $valor);
            }
        } catch (\Throwable) {
            return null;
        }
        if ($fecha->year < 2000) {
            $fecha = $fecha->setYear(2026); // corrección acordada (fila 3438, legacy_task_id 12619)
        }
        return $fecha;
    }

    // "1h 00min" / "8h 00min" / "45min" / "" -> minutos (null si no hay duración)
    private function parseTiempo(string $tiempo): ?int
    {
        $tiempo = trim($tiempo);
        if ($tiempo === '') return null;
        $horas = 0; $minutos = 0;
        if (preg_match('/(\d+)\s*h/i', $tiempo, $m)) $horas = (int) $m[1];
        if (preg_match('/(\d+)\s*min/i', $tiempo, $m)) $minutos = (int) $m[1];
        $total = $horas * 60 + $minutos;
        return $total > 0 ? $total : null;
    }

    private function resolverPropiedad(string $inmueble): ?int
    {
        if ($inmueble === '') return null;
        $clave = mb_strtolower($inmueble);
        return self::PROPIEDADES_OVERRIDE[$clave] ?? $this->propiedadesMap[$clave] ?? null;
    }

    private function resolverUsuario(string $trabajador): ?int
    {
        $clave = mb_strtolower($trabajador);
        return self::USUARIOS_OVERRIDE[$clave] ?? $this->usuariosMap[$clave] ?? null;
    }

    // "129321_26/12>>02/01" -> ['129321', id de vm_reservas o null]. Solo aplica de verdad a
    // vm_tareas_limpieza (única con columna id_reservas); para mantenimiento el código queda
    // igualmente en la descripción como texto, sin FK.
    private function resolverReserva(string $reserva): array
    {
        if ($reserva === '' || !str_contains($reserva, '_')) return [null, null];
        $codigo = strtok($reserva, '_');
        $id = DB::table('vm_reservas')->where('booking_id', $codigo)->value('id');
        return [$codigo, $id];
    }
}
