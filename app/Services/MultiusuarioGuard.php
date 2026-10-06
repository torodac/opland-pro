<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectTable;
use App\Models\TableField;
use Illuminate\Support\Facades\DB;

// Valida al guardar lo que la lista de opciones de un campo "multiusuario" solo hace improbable.
//
// Por qué existe: un campo multiusuario se puede editar desde dos puertas -- la ficha genérica
// del no-code y, cuando la hay, una pantalla propia del proyecto. Filtrar las opciones solo
// protege la primera, y ni siquiera eso: un POST directo se salta cualquier filtro. Es la misma
// asimetría botón/endpoint que ya resolvimos en el menú (ver EnforceMenuTableAccess): lo que no
// está en el endpoint, no está.
//
// El caso que lo motiva es vm_usuarios.supervisados ("Supervisa a"), la lista de personas cuyo
// informe mensual firma alguien. Si una persona acabara en DOS listas, el síntoma no sería un
// error: el circuito de firmas asume un único aprobador, así que su informe lo firmaría quien
// saliera primero en la consulta. Silencioso y no reproducible.
//
// Las cuatro reglas salen de la propia configuración del campo, no de código a medida:
//
//   existencia  siempre -- un id borrado o inexistente no vale.
//   roles:a,b,c lista blanca de roles que pueden figurar como valor. Ya la usaba el cargador
//               de opciones para filtrar; aquí se comprueba además al guardar.
//   (propia)    si los valores viven en la MISMA tabla que el registro, ninguno puede ser el
//               registro mismo. Es AutoreferenciaGuard aplicado a listas: aquella detecta los
//               campos por su "ref:" y compara con un entero, así que un multiusuario se le
//               escapaba entero.
//   exclusivo   un valor no puede estar a la vez en la lista de otro registro. Es opcional
//               porque no toda lista lo quiere: vm_roles.roles_supervisados, por ejemplo, no
//               tiene por qué repartir los roles en particiones.
//
// Los tokens de "extras" van separados por '|', igual que ya hacía TableField::getRefFullTable().
class MultiusuarioGuard
{
    /** @return string[] */
    public static function tokens(?string $extras): array
    {
        return array_values(array_filter(array_map('trim', explode('|', (string) $extras))));
    }

    public static function tiene(TableField $campo, string $token): bool
    {
        return in_array($token, self::tokens($campo->extras), true);
    }

    /**
     * Ids de rol permitidos como valor, declarados con "roles:1,2,3". null = sin restricción.
     *
     * @return int[]|null
     */
    public static function rolesPermitidos(TableField $campo): ?array
    {
        foreach (self::tokens($campo->extras) as $t) {
            if (str_starts_with($t, 'roles:')) {
                return array_map('intval', array_filter(explode(',', substr($t, 6)), 'strlen'));
            }
        }

        return null;
    }

    /**
     * Tabla a la que apuntan los VALORES del campo: los roles del proyecto si declara
     * "source:roles", y sus usuarios en cualquier otro caso.
     */
    public static function tablaDestino(Project $project, TableField $campo): string
    {
        return self::tiene($campo, 'source:roles')
            ? $project->slug . '_roles'
            : $project->slug . '_usuarios';
    }

    /** Campos de tipo multiusuario de esta tabla. */
    public static function campos(ProjectTable $projectTable)
    {
        return $projectTable->fields->where('type', 'multiusuario');
    }

    /**
     * Convierte a int[] lo que llega del formulario: un array de ids, una cadena JSON (que es
     * como lo deja filterData() antes de escribir) o nada.
     *
     * @return int[]
     */
    public static function normalizar($valor): array
    {
        if (is_string($valor)) $valor = json_decode($valor, true);
        if (!is_array($valor)) return [];

        return array_values(array_unique(array_map('intval', $valor)));
    }

    /**
     * Mensaje de error del primer problema encontrado, o null si todo bien.
     *
     * @param array $datos        datos a guardar (solo se miran las claves presentes)
     * @param array $registroIds  ids que se están actualizando (varios en edición masiva)
     */
    public static function error(Project $project, ProjectTable $projectTable, array $datos, array $registroIds): ?string
    {
        $propia      = $projectTable->getFullTableName();
        $registroIds = array_map('intval', $registroIds);

        foreach (self::campos($projectTable) as $campo) {
            if (!array_key_exists($campo->name, $datos)) continue;

            $ids = self::normalizar($datos[$campo->name]);
            if (!$ids) continue;

            $destino = self::tablaDestino($project, $campo);
            $etiqueta = $campo->label ?: $campo->name;

            // ── Existencia ──────────────────────────────────────────────────────────
            $filas = DB::table($destino)->whereIn('id', $ids)
                ->where(fn($q) => $q->where('deleted', 0)->orWhereNull('deleted'))
                ->get(['id', 'nombre'])->keyBy('id');

            foreach ($ids as $uid) {
                if (!$filas->has($uid)) {
                    return "\"{$etiqueta}\": el registro #{$uid} no existe o está borrado.";
                }
            }

            // ── Autorreferencia ─────────────────────────────────────────────────────
            if ($destino === $propia) {
                $propios = array_intersect($ids, $registroIds);
                if ($propios) {
                    return "\"{$etiqueta}\": un registro no puede incluirse a sí mismo en su propia lista.";
                }
            }

            // ── Lista blanca de roles ───────────────────────────────────────────────
            $roles = self::rolesPermitidos($campo);
            if ($roles !== null && $destino === $project->slug . '_usuarios') {
                $fuera = DB::table($destino)->whereIn('id', $ids)
                    ->where(fn($q) => $q->whereNotIn('id_rol', $roles)->orWhereNull('id_rol'))
                    ->get(['id', 'nombre']);
                if ($fuera->isNotEmpty()) {
                    $nombres = $fuera->pluck('nombre')->implode(', ');
                    return "\"{$etiqueta}\": el rol de {$nombres} no puede figurar en esta lista.";
                }
            }

            // ── Exclusividad entre registros ────────────────────────────────────────
            if (self::tiene($campo, 'exclusivo')) {
                // Edicion masiva: poner la misma lista en varios registros es crear la
                // duplicacion de golpe, y el bucle de abajo no lo veria porque todos los
                // registros afectados quedan excluidos de "otros".
                if (count($registroIds) > 1) {
                    return "\"{$etiqueta}\": no se puede poner la misma lista en varios registros a la vez, "
                         . 'porque un valor solo puede estar en una.';
                }

                $otros = DB::table($propia)
                    ->where(fn($q) => $q->where('deleted', 0)->orWhereNull('deleted'))
                    ->whereNotIn('id', $registroIds ?: [0])
                    ->whereNotNull($campo->name)
                    ->get(['id', 'nombre', $campo->name]);

                foreach ($otros as $otro) {
                    $suyos  = self::normalizar($otro->{$campo->name});
                    $choque = array_intersect($ids, $suyos);
                    if ($choque) {
                        $nombres = $filas->only($choque)->pluck('nombre')->implode(', ');
                        return "\"{$etiqueta}\": {$nombres} ya está en la lista de {$otro->nombre}. "
                             . 'Quítalo de ahí primero.';
                    }
                }
            }
        }

        return null;
    }
}
