<?php

namespace App\Http\Middleware;

use App\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Aplica el permiso "Puede ver" del rol (vm_roles.ver y equivalentes en el resto de proyectos)
// a TODAS las rutas de una pantalla, derivándolo del propio menú lateral.
//
// El problema que resuelve: hasta ahora la lista "Puede ver" solo decidía si se dibujaba la
// entrada del sidebar. Cada pantalla a medida (dashboard, informes, liquidación, novaciones,
// km, horarios, jerarquías…) tenía que acordarse de comprobar el permiso por su cuenta, con
// 'table.access:x' en la ruta o un abort_unless en el controlador, y quien no lo hacía quedaba
// accesible escribiendo la URL a mano. Las tablas genéricas (listado/ficha) sí lo comprobaban;
// las pantallas propias, unas sí y otras no.
//
// Ahora el permiso sale del mismo dato que pinta el menú -- el ítem de menú y su tabla -- así
// que el botón y el endpoint no pueden discrepar, y cualquier pantalla nueva queda protegida en
// el momento en que se le crea su entrada de menú, sin tocar rutas ni controladores.
//
// Criterio de coincidencia: la URL pedida es la del ítem de menú, o cuelga de ella separada por
// "/" o por "_" (así entran /vm/informe-imputaciones/pdf, /vm/fichaje_list, /vm/usuarios_form/27
// o /vm/dashboard/validar-fichaje). Cuando varios ítems encajan gana el más largo, para que
// /vm/km/informe se resuelva contra "informe kilómetros" y no contra "consulta kilometraje".
//
// Si NINGÚN ítem de menú encaja, la petición pasa: este middleware endurece lo que el menú
// define, no inventa permisos para rutas que no son una pantalla (perfil, exportaciones sueltas,
// endpoints internos). Para ver qué queda fuera está el comando `opland:auditar-acceso`.
class EnforceMenuTableAccess
{
    // Caché por petición: el sidebar vuelve a leer estos mismos ítems al renderizar.
    private static array $cache = [];

    public function handle(Request $request, Closure $next): mixed
    {
        $project = $request->route('project');
        if (!$project instanceof Project) {
            $slug    = is_string($project) ? $project : ($project->slug ?? '');
            $project = $slug ? Project::where('slug', $slug)->first() : null;
        }
        if (!$project || !auth()->check()) {
            return $next($request);
        }

        $tabla = self::tablaDeLaUrl($project, '/' . ltrim($request->path(), '/'));

        if ($tabla !== null && !auth()->user()->canViewTable($project, $tabla)) {
            abort(403, 'No tienes acceso a esta sección.');
        }

        return $next($request);
    }

    // Nombre de la tabla (física o virtual) de la pantalla a la que pertenece $path, o null si
    // ninguna entrada del menú de este proyecto la cubre. Público para que lo comparta el
    // comando de auditoría, que necesita la misma resolución exacta.
    public static function tablaDeLaUrl(Project $project, string $path): ?string
    {
        $mejor = null;
        $largo = -1;

        foreach (self::pantallas($project) as $base => $tabla) {
            if ($path !== $base && !str_starts_with($path, $base . '/') && !str_starts_with($path, $base . '_')) {
                continue;
            }
            if (strlen($base) > $largo) {
                $largo = strlen($base);
                $mejor = $tabla;
            }
        }

        return $mejor;
    }

    // [ruta base => nombre de tabla] de cada entrada del menú del proyecto que tenga tabla
    // asociada. Las entradas sin tabla (enlaces externos, la PWA) no definen permiso y se
    // ignoran. Incluye submenús.
    public static function pantallas(Project $project): array
    {
        $key = 'p' . $project->id;
        if (isset(self::$cache[$key])) return self::$cache[$key];

        $filas = DB::table('admin_menu_items as m')
            ->join('admin_project_tables as t', 't.id', '=', 'm.project_table_id')
            ->where('m.project_id', $project->id)
            ->get(['m.url', 't.name']);

        $pantallas = [];
        $porSeccion = [];   // primer segmento => [tablas distintas que lo usan]

        foreach ($filas as $f) {
            // Ítem con URL propia (pantalla a medida) o tabla genérica servida en /{slug}/{tabla}.
            $base = $f->url
                ? '/' . trim(parse_url($f->url, PHP_URL_PATH) ?? '', '/')
                : '/' . $project->slug . '/' . $f->name;

            $base = rtrim($base, '/');
            if ($base === '' || $base === '/') continue;

            // Dos ítems con la misma URL base: se queda el primero, el permiso es el mismo.
            $pantallas[$base] ??= $f->name;

            // Muchas pantallas guardan en el menú una URL más profunda que su raíz real
            // (/vm/horario/planificar), y sus demás rutas cuelgan de la raíz (/vm/horario,
            // /vm/horario/publicar) sin colgar de la URL del menú. Se indexa también esa
            // sección, pero SOLO si es inequívoca: si dos pantallas distintas comparten el
            // primer segmento (p. ej. /vm/km y /vm/km/informe, o los tres Power BI), no hay
            // forma de saber a cuál pertenece una ruta suelta y se deja fuera.
            $partes = explode('/', ltrim($base, '/'));
            if (count($partes) > 2) {
                $seccion = '/' . $partes[0] . '/' . $partes[1];
                $porSeccion[$seccion][$f->name] = true;
            }
        }

        foreach ($porSeccion as $seccion => $tablas) {
            if (count($tablas) !== 1) continue;            // ambigua: no se indexa
            if (isset($pantallas[$seccion])) continue;      // ya es una pantalla por sí misma
            $pantallas[$seccion] = array_key_first($tablas);
        }

        return self::$cache[$key] = $pantallas;
    }
}
