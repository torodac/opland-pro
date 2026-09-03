<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

// Reparte el coste laboral (vm_nominas.coste_total) de cada trabajador de Limpieza/Mantenimiento
// entre las propiedades donde imputó tiempo ese mes (vm_imputaciones -> tarea -> id_propiedades),
// de forma proporcional a los minutos. Si un trabajador no tiene ninguna imputación ese mes
// (vacaciones, baja, tareas sin propiedad...), su coste se reparte con el peso agregado que
// tiene cada propiedad ese mes dentro de su mismo departamento (suma de minutos de todo el
// equipo), en vez de perderse o repartirse a partes iguales. Persiste en
// vm_costes_laborales_propiedad (una fila por trabajador+propiedad+mes+tipo); recalcular()
// siempre borra y regenera el mes entero, para que sea idempotente ante reintentos o datos
// que cambiaron (nóminas importadas después, imputaciones corregidas...).
class CostesLaboralesPropiedadService
{
    private const DEPARTAMENTOS = ['limpieza' => 1, 'mantenimiento' => 2];
    private const TAREA_TABLE   = ['limpieza' => 'vm_tareas_limpieza', 'mantenimiento' => 'vm_tareas_mantenimiento'];

    public function recalcular(int $anio, int $mes): array
    {
        $filas   = [];
        $resumen = [];

        foreach (self::DEPARTAMENTOS as $tipo => $deptId) {
            [$filasTipo, $sinDatos] = $this->calcularTipo($tipo, $deptId, $anio, $mes);
            $filas = array_merge($filas, $filasTipo);
            $resumen[$tipo] = [
                'trabajadores_repartidos' => count(array_unique(array_column($filasTipo, 'id_usuario'))),
                'coste_total'             => round(array_sum(array_column($filasTipo, 'coste')), 2),
                'sin_datos'               => $sinDatos, // trabajadores con nómina pero sin ninguna base de reparto (ni propia ni de grupo)
            ];
        }

        $ahora = now();
        DB::transaction(function () use ($anio, $mes, $filas, $ahora) {
            DB::table('vm_costes_laborales_propiedad')->where('anio', $anio)->where('mes', $mes)->delete();
            if (empty($filas)) return;

            foreach ($filas as &$f) {
                $f['anio'] = $anio;
                $f['mes'] = $mes;
                $f['hidden'] = 0;
                $f['deleted'] = 0;
                $f['createuser'] = auth()->id();
                $f['createdat'] = $ahora;
                $f['updatedat'] = $ahora;
            }
            unset($f);

            foreach (array_chunk($filas, 500) as $chunk) {
                DB::table('vm_costes_laborales_propiedad')->insert($chunk);
            }
        });

        return $resumen;
    }

    private function calcularTipo(string $tipo, int $deptId, int $anio, int $mes): array
    {
        $inicio = Carbon::create($anio, $mes, 1);
        $fin    = $inicio->copy()->endOfMonth();
        $mesNomina = $inicio->toDateString();
        $tareaTabla = self::TAREA_TABLE[$tipo];

        // Peso agregado del grupo por propiedad ese mes (todo el departamento, todas las
        // imputaciones de este tipo) -- es el criterio de repato de fallback.
        $pesoGrupo = DB::table('vm_imputaciones as i')
            ->join("{$tareaTabla} as t", 't.id', '=', 'i.id_tarea')
            ->where('i.tipo', $tipo)
            ->whereBetween('i.fecha_imputacion', [$inicio->toDateString(), $fin->toDateString()])
            ->whereNotNull('t.id_propiedades')
            ->groupBy('t.id_propiedades')
            ->selectRaw('t.id_propiedades as id_propiedades, sum(i.duracion) as mins')
            ->get()
            ->keyBy('id_propiedades');

        $totalGrupo = (int) $pesoGrupo->sum('mins');
        $pesoNormalizado = $totalGrupo > 0
            ? $pesoGrupo->map(fn($r) => $r->mins / $totalGrupo)
            : collect();

        $nominas = DB::table('vm_nominas as n')
            ->join('vm_usuarios as u', 'u.id', '=', 'n.id_usuario')
            ->where('u.id_departamento', $deptId)
            ->where('n.mes', $mesNomina)
            ->where('n.deleted', 0)
            ->get(['n.id_usuario', 'n.coste_total']);

        // Imputaciones propias de TODOS los trabajadores con nómina este mes, en una sola
        // consulta (antes se lanzaba una consulta por trabajador dentro del foreach -- con
        // meses de más plantilla, ese N+1 es lo que hacía que "Calcular este mes" tardase minutos
        // en vez de segundos).
        $propiosPorUsuario = DB::table('vm_imputaciones as i')
            ->join("{$tareaTabla} as t", 't.id', '=', 'i.id_tarea')
            ->where('i.tipo', $tipo)
            ->whereIn('i.id_usuario', $nominas->pluck('id_usuario'))
            ->whereBetween('i.fecha_imputacion', [$inicio->toDateString(), $fin->toDateString()])
            ->whereNotNull('t.id_propiedades')
            ->groupBy('i.id_usuario', 't.id_propiedades')
            ->selectRaw('i.id_usuario, t.id_propiedades, sum(i.duracion) as mins')
            ->get()
            ->groupBy('id_usuario');

        $filas = [];
        $sinDatos = [];

        foreach ($nominas as $n) {
            $costeTotal = (float) $n->coste_total;
            $propios = $propiosPorUsuario->get($n->id_usuario, collect());
            $totalPropio = (int) $propios->sum('mins');

            if ($totalPropio > 0) {
                $filas = array_merge($filas, $this->repartirCoste($n->id_usuario, $costeTotal, $propios->keyBy('id_propiedades')->map(fn($r) => $r->mins), $tipo, 'propio', true));
            } elseif ($pesoNormalizado->isNotEmpty()) {
                $filas = array_merge($filas, $this->repartirCoste($n->id_usuario, $costeTotal, $pesoNormalizado, $tipo, 'peso_grupo', false));
            } else {
                $sinDatos[] = (int) $n->id_usuario; // ni datos propios ni de grupo este mes: no hay base de reparto
            }
        }

        return [$filas, $sinDatos];
    }

    // $pesos: id_propiedades => minutos (si $sonMinutos) o => proporción 0..1 (si no).
    // Ajusta el redondeo en la última fila para que la suma cuadre exactamente con $costeTotal.
    private function repartirCoste(int $idUsuario, float $costeTotal, $pesos, string $tipo, string $origen, bool $sonMinutos): array
    {
        $total = $sonMinutos ? $pesos->sum() : 1.0;
        $filas = [];
        $acumulado = 0.0;
        $idsPropiedades = $pesos->keys()->values();

        foreach ($idsPropiedades as $i => $idPropiedad) {
            $peso = $sonMinutos ? ($pesos[$idPropiedad] / $total) : $pesos[$idPropiedad];
            $esUltima = $i === $idsPropiedades->count() - 1;
            $coste = $esUltima ? round($costeTotal - $acumulado, 2) : round($costeTotal * $peso, 2);
            $acumulado += $coste;

            $filas[] = [
                'tipo'               => $tipo,
                'id_usuario'         => $idUsuario,
                'id_propiedades'     => $idPropiedad,
                'coste'              => $coste,
                'minutos'            => $sonMinutos ? (int) $pesos[$idPropiedad] : null,
                'origen'             => $origen,
                'nomina_coste_total' => $costeTotal,
            ];
        }

        return $filas;
    }
}
