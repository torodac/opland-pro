<?php

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

// Alarmas de las propiedades. Pantalla aparte y no un bloque de la ficha de la propiedad, porque
// lleva la palabra clave de la central receptora y las credenciales de la app del instalador: ahi
// lo habria visto cualquiera con permiso sobre propiedades.
//
// Tres cosas que no son de adorno:
//
//  1. El acceso de lectura lo da menu.access desde la entrada del menu (tabla alarmas). Editar
//     exige ademas permiso de EDICION sobre esa tabla.
//  2. La palabra clave y la contrasena NO viajan nunca en el HTML del listado ni de la ficha. Si
//     viajaran, bastaria con mirar el codigo fuente para leerlas sin dejar rastro, y el registro
//     de accesos seria decorativo. Se piden a revelar(), que apunta primero y devuelve despues.
//  3. Van cifradas en reposo. El proyecto no usa modelos Eloquent para las tablas de VM, asi que
//     el cifrado se hace aqui con Crypt, en los dos sitios que escriben y en el que revela.
class AlarmasController extends Controller
{
    private const TABLA = 'alarmas';

    /** Los dos campos que se pueden revelar, y que por tanto van cifrados. */
    private const CAMPOS_SENSIBLES = ['palabra_clave', 'app_password'];

    private const ETIQUETAS_SENSIBLES = [
        'palabra_clave' => 'Palabra clave',
        'app_password'  => 'Contraseña en la app',
    ];

    public function index(Request $request, Project $project)
    {
        $empresa = trim((string) $request->input('empresa', ''));

        // Las tarjetas de cabecera: una por empresa de seguridad, con cuantas propiedades lleva.
        // Salen de los datos, no de una lista fija, para que una empresa nueva aparezca sola.
        $empresas = DB::table('vm_alarmas')
            ->where(fn($x) => $x->where('deleted', 0)->orWhereNull('deleted'))
            ->whereNotNull('empresa')
            ->where('empresa', '<>', '')
            ->groupBy('empresa')
            ->orderBy('empresa')
            ->get([DB::raw('empresa'), DB::raw('count(*) as propiedades')]);

        $alarmas = $this->baseQuery()
            ->when($empresa !== '', fn($q) => $q->where('a.empresa', $empresa))
            ->orderBy('p.nombre')
            ->get();

        return view('vm.alarmas', [
            'project'     => $project,
            'empresas'    => $empresas,
            'logos'       => $this->logos($empresas),
            'empresa'     => $empresa,
            'alarmas'     => $alarmas,
            'total'       => $empresas->sum('propiedades'),
            'puedeEditar' => Auth::user()?->canEditTable($project, self::TABLA) ?? false,
        ]);
    }

    public function show(Request $request, Project $project, int $id)
    {
        $alarma = $this->baseQuery()->where('a.id', $id)->first();
        abort_unless($alarma, 404);

        return view('vm.alarma', [
            'project'     => $project,
            'alarma'      => $alarma,
            'accesos'     => $this->accesos($id),
            'sensibles'   => self::ETIQUETAS_SENSIBLES,
            'puedeEditar' => Auth::user()?->canEditTable($project, self::TABLA) ?? false,
        ]);
    }

    /**
     * Devuelve en claro uno de los dos campos sensibles, dejando constancia de quien lo pidio.
     *
     * El orden importa: se apunta el acceso ANTES de devolver el valor. Si se apuntara despues y
     * algo fallara en medio, el valor habria salido sin rastro, que es justo lo que esta pantalla
     * existe para evitar.
     */
    public function revelar(Request $request, Project $project, int $id)
    {
        $campo = (string) $request->input('campo', '');
        abort_unless(in_array($campo, self::CAMPOS_SENSIBLES, true), 422);

        $alarma = DB::table('vm_alarmas')->where('id', $id)->first(['id', ...self::CAMPOS_SENSIBLES]);
        abort_unless($alarma, 404);

        $cifrado = $alarma->{$campo};
        if ($cifrado === null || $cifrado === '') {
            return response()->json(['ok' => true, 'valor' => null, 'vacio' => true]);
        }

        $acceso = [
            'id_alarmas' => $id,
            'campo'      => $campo,
            'id_usuario' => Auth::id(),
            'fecha'      => now(),
        ];
        DB::table('vm_alarmas_accesos')->insert($acceso);

        return response()->json([
            'ok'      => true,
            'valor'   => $this->descifrar($cifrado, $id, $campo),
            'acceso'  => [
                'usuario' => Auth::user()?->name ?? '—',
                'fecha'   => now()->format('d/m/Y H:i'),
                'campo'   => self::ETIQUETAS_SENSIBLES[$campo],
            ],
        ]);
    }

    /** Formulario de alta o de edicion. */
    public function form(Request $request, Project $project, ?int $id = null)
    {
        abort_unless(Auth::user()?->canEditTable($project, self::TABLA), 403);

        $alarma = null;
        if ($id !== null) {
            $alarma = $this->baseQuery()->where('a.id', $id)->first();
            abort_unless($alarma, 404);
        }

        // En el alta, solo propiedades sin alarma: la regla "una por propiedad" la impone el
        // indice unico, y ofrecer las que ya tienen seria ofrecer un error.
        $propiedades = DB::table('vm_propiedades as p')
            ->where(fn($x) => $x->where('p.deleted', 0)->orWhereNull('p.deleted'))
            ->when($id === null, fn($q) => $q->whereNotExists(fn($sub) => $sub
                ->selectRaw('1')->from('vm_alarmas as a')
                ->whereColumn('a.id_propiedades', 'p.id')
                ->where(fn($x) => $x->where('a.deleted', 0)->orWhereNull('a.deleted'))))
            ->orderBy('p.nombre')
            ->get(['p.id', 'p.nombre']);

        return view('vm.alarma-form', [
            'project'     => $project,
            'alarma'      => $alarma,
            'propiedades' => $propiedades,
        ]);
    }

    public function guardar(Request $request, Project $project, ?int $id = null)
    {
        abort_unless(Auth::user()?->canEditTable($project, self::TABLA), 403);

        $reglas = [
            'empresa'      => 'required|string|max:255',
            'titular'      => 'nullable|string|max:255',
            'titular_doc'  => 'nullable|string|max:20',
            'tipo_alarma'  => 'nullable|string|max:255',
            'num_contrato' => 'nullable|string|max:255',
            'app_usuario'  => 'nullable|string|max:255',
            'observaciones' => 'nullable|string|max:4000',
            'palabra_clave' => 'nullable|string|max:255',
            'app_password'  => 'nullable|string|max:255',
        ];
        foreach ([1, 2, 3] as $n) {
            $reglas["contacto{$n}_nombre"]   = 'nullable|string|max:255';
            $reglas["contacto{$n}_telefono"] = 'nullable|string|max:60';
        }
        // La propiedad solo se elige al crear: moverla despues convertiria el historial de
        // accesos de una casa en el de otra.
        if ($id === null) {
            $reglas['id_propiedades'] = 'required|integer|exists:vm_propiedades,id|unique:vm_alarmas,id_propiedades';
        }

        $datos = $request->validate($reglas, [], [
            'empresa'        => 'empresa de seguridad',
            'id_propiedades' => 'propiedad',
        ]);

        $fila = collect($datos)->except(['palabra_clave', 'app_password'])->all();

        // Un campo sensible que llega vacio en una edicion significa "no lo toques", no "borralo":
        // el formulario nunca muestra el valor actual, asi que el usuario no lo ha podido retecrear
        // y guardar sin mas no puede perderlo. Para vaciarlo esta la casilla de borrado.
        foreach (self::CAMPOS_SENSIBLES as $campo) {
            $valor = (string) $request->input($campo, '');
            if ($request->boolean("borrar_{$campo}")) {
                $fila[$campo] = null;
            } elseif ($valor !== '') {
                $fila[$campo] = Crypt::encryptString($valor);
            } elseif ($id === null) {
                $fila[$campo] = null;
            }
        }

        if ($id === null) {
            $fila['createuser'] = Auth::id();
            $fila['createdat']  = now();
            $nuevo = DB::table('vm_alarmas')->insertGetId($fila);

            return redirect()->route('vm.alarma', [$project->slug, $nuevo])
                ->with('status', 'Alarma creada.');
        }

        abort_unless(DB::table('vm_alarmas')->where('id', $id)->exists(), 404);
        $fila['updateuser'] = Auth::id();
        $fila['updatedat']  = now();
        DB::table('vm_alarmas')->where('id', $id)->update($fila);

        return redirect()->route('vm.alarma', [$project->slug, $id])
            ->with('status', 'Alarma actualizada.');
    }

    /**
     * El logo de cada empresa de seguridad, por convencion de nombre de fichero:
     * public/img/alarmas/<empresa-en-slug>.(svg|png|jpg|jpeg|webp).
     *
     * Por convencion y no por un campo en la base porque "empresa" es texto libre y no un
     * catalogo: en cuanto haya que gestionar mas cosas de la empresa (telefono de la central,
     * contrato marco) tendra que ser una tabla, y entonces el logo se movera con ella.
     *
     * Una empresa sin fichero no sale sin nada: la vista pone el logo de Opland.
     */
    private function logos(iterable $empresas): array
    {
        $mapa = [];

        foreach ($empresas as $e) {
            $slug = Str::slug((string) $e->empresa);
            if ($slug === '') {
                continue;
            }
            foreach (['svg', 'png', 'jpg', 'jpeg', 'webp'] as $ext) {
                $rel = "img/alarmas/{$slug}.{$ext}";
                if (is_file(public_path($rel))) {
                    $mapa[$e->empresa] = asset($rel);
                    break;
                }
            }
        }

        return $mapa;
    }

    // ── Consultas compartidas ────────────────────────────────────────────────

    /**
     * La alarma con los datos de su propiedad. El nombre y la direccion se leen de
     * vm_propiedades y no se guardan aqui: la propiedad es su dueno.
     *
     * No selecciona palabra_clave ni app_password a proposito, para que no haya forma de que una
     * vista los pinte por descuido.
     */
    private function baseQuery()
    {
        return DB::table('vm_alarmas as a')
            ->join('vm_propiedades as p', 'p.id', '=', 'a.id_propiedades')
            ->leftJoin('admin_users as cu', 'cu.id', '=', 'a.createuser')
            ->leftJoin('admin_users as uu', 'uu.id', '=', 'a.updateuser')
            ->where(fn($x) => $x->where('a.deleted', 0)->orWhereNull('a.deleted'))
            ->selectRaw("
                a.id, a.id_propiedades, a.empresa, a.titular, a.titular_doc, a.tipo_alarma,
                a.num_contrato, a.app_usuario, a.observaciones,
                a.contacto1_nombre, a.contacto1_telefono,
                a.contacto2_nombre, a.contacto2_telefono,
                a.contacto3_nombre, a.contacto3_telefono,
                a.createdat, a.updatedat,
                cu.name AS creado_por, uu.name AS modificado_por,
                p.nombre AS propiedad,
                nullif(trim(concat_ws(', ', nullif(p.icnea_address, ''), nullif(p.icnea_city, ''))), '') AS direccion,
                (a.palabra_clave IS NOT NULL AND a.palabra_clave <> '') AS tiene_palabra_clave,
                (a.app_password  IS NOT NULL AND a.app_password  <> '') AS tiene_app_password
            ");
    }

    /** Las consultas de los campos sensibles de una alarma, las mas recientes primero. */
    private function accesos(int $id)
    {
        return DB::table('vm_alarmas_accesos as x')
            ->leftJoin('admin_users as u', 'u.id', '=', 'x.id_usuario')
            ->where('x.id_alarmas', $id)
            ->orderByDesc('x.fecha')
            ->limit(100)
            ->get(['x.campo', 'x.fecha', DB::raw("coalesce(u.name, '—') as usuario")]);
    }

    /**
     * Un valor que no se puede descifrar casi siempre significa que se escribio en claro antes de
     * que esta pantalla existiera, o que APP_KEY cambio. Se avisa y se devuelve null en vez de
     * reventar la ficha: el resto de los datos de la alarma siguen sirviendo.
     */
    private function descifrar(string $cifrado, int $id, string $campo): ?string
    {
        try {
            return Crypt::decryptString($cifrado);
        } catch (\Throwable $e) {
            Log::warning('vm_alarmas: no se puede descifrar un campo sensible', [
                'alarma' => $id,
                'campo'  => $campo,
            ]);

            return null;
        }
    }
}
