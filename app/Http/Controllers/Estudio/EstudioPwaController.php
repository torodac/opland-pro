<?php

namespace App\Http\Controllers\Estudio;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * API de la PWA de estudio (proyecto `estudio`).
 *
 * Jerarquía: asignatura → tema → tarea. El tiempo se imputa siempre a una tarea (estudio_sesiones),
 * y el estado de cada tema se calcula a partir de sus tareas (no se guarda).
 * Todo se filtra por admin_user_id del usuario logado: cada usuario solo ve sus datos.
 */
class EstudioPwaController extends Controller
{
    private const ESTADOS = ['Pendiente', 'En curso', 'Estudiado'];
    private const MAX_CRONOMETRO_MIN = 720; // tope de una sesión de cronómetro (12 h)

    // ── Auth ─────────────────────────────────────────────────────────────

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $authUser = DB::table('admin_users')
            ->whereRaw('LOWER(email) = LOWER(?)', [$request->email])
            ->first();

        if (!$authUser || !password_verify($request->password, $authUser->password)) {
            return response()->json(['error' => 'Credenciales incorrectas'], 401);
        }

        $tieneAcceso = DB::table('admin_user_roles')
            ->where('user_id', $authUser->id)
            ->whereIn('role', ['admin', 'admin_estudio', 'estudio_usuarios'])
            ->where(function ($q) {
                $q->whereNull('fecha_baja')->orWhere('fecha_baja', '>', now());
            })
            ->exists();

        if (!$tieneAcceso) {
            return response()->json(['error' => 'Usuario sin acceso a estudio'], 403);
        }

        $ttl   = $request->boolean('remember') ? 30 : 1;
        $token = Str::random(64);

        DB::table('estudio_pwa_tokens')->insert([
            'token'         => $token,
            'admin_user_id' => $authUser->id,
            'device'        => substr($request->header('User-Agent', ''), 0, 255),
            'expires_at'    => now()->addDays($ttl),
            'last_seen_at'  => now(),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return response()->json([
            'token'  => $token,
            'expira' => now()->addDays($ttl)->toIso8601String(),
            'user'   => $this->perfil($authUser),
        ]);
    }

    public function logout(Request $request)
    {
        $token = $this->bearerToken($request);
        if ($token) {
            DB::table('estudio_pwa_tokens')->where('token', $token)->delete();
        }
        return response()->json(['ok' => true]);
    }

    public function me(Request $request)
    {
        return response()->json($this->perfil($this->authenticate($request)));
    }

    // ── Árbol: asignaturas → temas → tareas ──────────────────────────────

    public function arbol(Request $request)
    {
        $user = $this->authenticate($request);
        $uid  = $user->id;

        $asignaturas = DB::table('estudio_asignaturas')
            ->where('admin_user_id', $uid)->where('deleted', 0)
            ->orderBy('curso')->orderBy('nombre')->get();

        $temas = DB::table('estudio_temas')
            ->where('admin_user_id', $uid)->where('deleted', 0)
            ->orderBy('nombre')->get()->groupBy('id_asignatura');

        $tareas = DB::table('estudio_tareas')
            ->where('admin_user_id', $uid)->where('deleted', 0)
            ->orderBy('id')->get()->groupBy('id_tema');

        // Minutos totales por tarea (sesiones cerradas)
        $minutosPorTarea = DB::table('estudio_sesiones')
            ->where('admin_user_id', $uid)->where('deleted', 0)
            ->whereNotNull('minutos')
            ->selectRaw('id_tarea, SUM(minutos) AS total')
            ->groupBy('id_tarea')
            ->pluck('total', 'id_tarea');

        $out = $asignaturas->map(function ($a) use ($temas, $tareas, $minutosPorTarea) {
            $temasA = ($temas[$a->id] ?? collect())->map(function ($t) use ($tareas, $minutosPorTarea) {
                $tareasT = ($tareas[$t->id] ?? collect())->map(fn ($k) => [
                    'id'                  => $k->id,
                    'nombre'              => $k->nombre,
                    'estado'              => $k->estado,
                    'tiempo_estimado_min' => $k->tiempo_estimado_min,
                    'minutos'             => (int) ($minutosPorTarea[$k->id] ?? 0),
                ])->values();

                return [
                    'id'      => $t->id,
                    'nombre'  => $t->nombre,
                    'estado'  => $this->estadoTema($tareasT->pluck('estado')->all()),
                    'tareas'  => $tareasT,
                    'minutos' => $tareasT->sum('minutos'),
                ];
            })->values();

            return [
                'id'      => $a->id,
                'nombre'  => $a->nombre,
                'curso'   => $a->curso,
                'color'   => $a->color,
                'minutos' => $temasA->sum('minutos'),
                'temas'   => $temasA,
            ];
        });

        return response()->json([
            'asignaturas' => $out,
            'cronometro'  => $this->cronometroActivo($uid),
        ]);
    }

    // ── Asignaturas ──────────────────────────────────────────────────────

    public function crearAsignatura(Request $request)
    {
        $user = $this->authenticate($request);
        $data = $request->validate([
            'nombre' => 'required|string|max:120',
            'curso'  => 'required|string|max:40',
            'color'  => 'nullable|string|max:20',
        ]);

        $id = $this->insertar('estudio_asignaturas', $user, $data);
        return response()->json(['id' => $id], 201);
    }

    public function editarAsignatura(Request $request, int $id)
    {
        $user = $this->authenticate($request);
        $this->propio('estudio_asignaturas', $user, $id);
        $data = $request->validate([
            'nombre' => 'sometimes|string|max:120',
            'curso'  => 'sometimes|string|max:40',
            'color'  => 'sometimes|nullable|string|max:20',
        ]);

        $this->actualizar('estudio_asignaturas', $user, $id, $data);
        return response()->json(['ok' => true]);
    }

    public function borrarAsignatura(Request $request, int $id)
    {
        $user = $this->authenticate($request);
        $this->propio('estudio_asignaturas', $user, $id);

        $temas = DB::table('estudio_temas')->where('id_asignatura', $id)->where('admin_user_id', $user->id)->pluck('id');
        DB::table('estudio_tareas')->whereIn('id_tema', $temas)->where('admin_user_id', $user->id)->update(['deleted' => 1, 'updatedat' => now()]);
        DB::table('estudio_temas')->whereIn('id', $temas)->update(['deleted' => 1, 'updatedat' => now()]);
        $this->actualizar('estudio_asignaturas', $user, $id, ['deleted' => 1]);

        return response()->json(['ok' => true]);
    }

    // ── Temas ────────────────────────────────────────────────────────────

    public function crearTema(Request $request)
    {
        $user = $this->authenticate($request);
        $data = $request->validate([
            'id_asignatura' => 'required|integer',
            'nombre'        => 'required|string|max:200',
        ]);
        $this->propio('estudio_asignaturas', $user, $data['id_asignatura']);

        $id = $this->insertar('estudio_temas', $user, $data);
        return response()->json(['id' => $id], 201);
    }

    public function editarTema(Request $request, int $id)
    {
        $user = $this->authenticate($request);
        $this->propio('estudio_temas', $user, $id);
        $data = $request->validate(['nombre' => 'required|string|max:200']);

        $this->actualizar('estudio_temas', $user, $id, $data);
        return response()->json(['ok' => true]);
    }

    public function borrarTema(Request $request, int $id)
    {
        $user = $this->authenticate($request);
        $this->propio('estudio_temas', $user, $id);

        DB::table('estudio_tareas')->where('id_tema', $id)->where('admin_user_id', $user->id)->update(['deleted' => 1, 'updatedat' => now()]);
        $this->actualizar('estudio_temas', $user, $id, ['deleted' => 1]);

        return response()->json(['ok' => true]);
    }

    // ── Tareas ───────────────────────────────────────────────────────────

    public function crearTarea(Request $request)
    {
        $user = $this->authenticate($request);
        $data = $request->validate([
            'id_tema'             => 'required|integer',
            'nombre'              => 'required|string|max:200',
            'estado'              => 'sometimes|string|in:' . implode(',', self::ESTADOS),
            'tiempo_estimado_min' => 'nullable|integer|min:0|max:100000',
        ]);
        $this->propio('estudio_temas', $user, $data['id_tema']);
        $data['estado'] = $data['estado'] ?? 'Pendiente';

        $id = $this->insertar('estudio_tareas', $user, $data);
        return response()->json(['id' => $id], 201);
    }

    public function editarTarea(Request $request, int $id)
    {
        $user = $this->authenticate($request);
        $this->propio('estudio_tareas', $user, $id);
        $data = $request->validate([
            'nombre'              => 'sometimes|string|max:200',
            'estado'              => 'sometimes|string|in:' . implode(',', self::ESTADOS),
            'tiempo_estimado_min' => 'sometimes|nullable|integer|min:0|max:100000',
        ]);

        $this->actualizar('estudio_tareas', $user, $id, $data);
        return response()->json(['ok' => true]);
    }

    public function borrarTarea(Request $request, int $id)
    {
        $user = $this->authenticate($request);
        $this->propio('estudio_tareas', $user, $id);
        $this->actualizar('estudio_tareas', $user, $id, ['deleted' => 1]);

        return response()->json(['ok' => true]);
    }

    // ── Tiempo: cronómetro y registro manual ─────────────────────────────

    public function cronometroIniciar(Request $request)
    {
        $user = $this->authenticate($request);
        $data = $request->validate(['id_tarea' => 'required|integer']);
        $tarea = $this->tareaConAsignatura($user, $data['id_tarea']);

        // Solo puede haber un cronómetro: el que esté en marcha se cierra antes
        $this->cerrarCronometro($user->id);

        $ahora = now();
        DB::table('estudio_sesiones')->insert([
            'fecha'         => $ahora->toDateString(),
            'inicio'        => $ahora,
            'fin'           => null,
            'minutos'       => null,
            'origen'        => 'cronometro',
            'id_asignatura' => $tarea->id_asignatura,
            'id_tarea'      => $tarea->id,
            'admin_user_id' => $user->id,
            'createuser'    => $user->id,
            'updateuser'    => $user->id,
            'createdat'    => $ahora,
            'updatedat'    => $ahora,
        ]);

        return response()->json(['cronometro' => $this->cronometroActivo($user->id)], 201);
    }

    public function cronometroDetener(Request $request)
    {
        $user = $this->authenticate($request);
        $sesion = $this->cerrarCronometro($user->id);

        return response()->json(['sesion' => $sesion]);
    }

    public function registrarManual(Request $request)
    {
        $user = $this->authenticate($request);
        $data = $request->validate([
            'id_tarea' => 'required|integer',
            'fecha'    => 'required|date_format:Y-m-d',
            'minutos'  => 'required|integer|min:1|max:' . self::MAX_CRONOMETRO_MIN,
        ]);
        $tarea = $this->tareaConAsignatura($user, $data['id_tarea']);

        $ahora = now();
        $id = DB::table('estudio_sesiones')->insertGetId([
            'fecha'         => $data['fecha'],
            'inicio'        => null,
            'fin'           => null,
            'minutos'       => $data['minutos'],
            'origen'        => 'manual',
            'id_asignatura' => $tarea->id_asignatura,
            'id_tarea'      => $tarea->id,
            'admin_user_id' => $user->id,
            'createuser'    => $user->id,
            'updateuser'    => $user->id,
            'createdat'    => $ahora,
            'updatedat'    => $ahora,
        ]);

        return response()->json(['id' => $id], 201);
    }

    public function borrarSesion(Request $request, int $id)
    {
        $user = $this->authenticate($request);
        $this->propio('estudio_sesiones', $user, $id);
        $this->actualizar('estudio_sesiones', $user, $id, ['deleted' => 1]);

        return response()->json(['ok' => true]);
    }

    // ── Informes ─────────────────────────────────────────────────────────

    /** Minutos por día (incluye días sin estudio), con desglose por asignatura. */
    public function informeDiario(Request $request)
    {
        $user  = $this->authenticate($request);
        $desde = Carbon::parse($request->query('desde', now()->subDays(13)->toDateString()))->startOfDay();
        $hasta = Carbon::parse($request->query('hasta', now()->toDateString()))->startOfDay();

        $sesiones = $this->sesionesEntre($user->id, $desde, $hasta);
        $porDia   = $sesiones->groupBy('fecha');

        $dias = [];
        for ($d = $hasta->copy(); $d->gte($desde); $d->subDay()) {
            $fecha = $d->toDateString();
            $delDia = $porDia[$fecha] ?? collect();
            $dias[] = [
                'fecha'       => $fecha,
                'minutos'     => (int) $delDia->sum('minutos'),
                'asignaturas' => $delDia->groupBy('id_asignatura')->map(fn ($g) => [
                    'id'      => $g->first()->id_asignatura,
                    'nombre'  => $g->first()->nombre,
                    'minutos' => (int) $g->sum('minutos'),
                ])->values(),
            ];
        }

        return response()->json([
            'desde'         => $desde->toDateString(),
            'hasta'         => $hasta->toDateString(),
            'total_minutos' => (int) $sesiones->sum('minutos'),
            'dias'          => $dias,
        ]);
    }

    /** Últimas N semanas (lunes a domingo), con minutos totales y días en los que se estudió. */
    public function informeSemanal(Request $request)
    {
        $user    = $this->authenticate($request);
        $semanas = min(max((int) $request->query('semanas', 8), 1), 52);
        $inicio  = now()->startOfWeek(Carbon::MONDAY)->subWeeks($semanas - 1);
        $hasta   = now()->endOfWeek(Carbon::SUNDAY);

        $sesiones = $this->sesionesEntre($user->id, $inicio, $hasta);

        $out = [];
        for ($i = 0; $i < $semanas; $i++) {
            $ini = $inicio->copy()->addWeeks($i);
            $fin = $ini->copy()->endOfWeek(Carbon::SUNDAY);
            $delta = $sesiones->filter(fn ($s) => $s->fecha >= $ini->toDateString() && $s->fecha <= $fin->toDateString());
            $out[] = [
                'inicio'          => $ini->toDateString(),
                'fin'             => $fin->toDateString(),
                'minutos'         => (int) $delta->sum('minutos'),
                'dias_estudiados' => $delta->pluck('fecha')->unique()->count(),
            ];
        }

        return response()->json(['semanas' => $out]);
    }

    /** Tiempo por asignatura en un rango y progreso de sus temas (temas con todas las tareas Estudiado). */
    public function informePorAsignatura(Request $request)
    {
        $user  = $this->authenticate($request);
        $desde = Carbon::parse($request->query('desde', now()->subDays(29)->toDateString()))->startOfDay();
        $hasta = Carbon::parse($request->query('hasta', now()->toDateString()))->startOfDay();

        $minutos = $this->sesionesEntre($user->id, $desde, $hasta)
            ->groupBy('id_asignatura')
            ->map(fn ($g) => (int) $g->sum('minutos'));

        $asignaturas = DB::table('estudio_asignaturas')
            ->where('admin_user_id', $user->id)->where('deleted', 0)
            ->orderBy('curso')->orderBy('nombre')->get();

        $out = $asignaturas->map(function ($a) use ($user, $minutos) {
            $temas = DB::table('estudio_temas')
                ->where('id_asignatura', $a->id)->where('admin_user_id', $user->id)->where('deleted', 0)
                ->get()->map(function ($t) use ($user) {
                    $estados = DB::table('estudio_tareas')
                        ->where('id_tema', $t->id)->where('admin_user_id', $user->id)->where('deleted', 0)
                        ->pluck('estado')->all();
                    return $this->estadoTema($estados);
                });

            return [
                'id'               => $a->id,
                'nombre'           => $a->nombre,
                'curso'            => $a->curso,
                'minutos'          => $minutos[$a->id] ?? 0,
                'temas_total'      => $temas->count(),
                'temas_estudiados' => $temas->filter(fn ($e) => $e === 'Estudiado')->count(),
            ];
        })->values();

        return response()->json([
            'desde'       => $desde->toDateString(),
            'hasta'       => $hasta->toDateString(),
            'asignaturas' => $out,
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** Estado del tema calculado a partir de los estados de sus tareas. */
    private function estadoTema(array $estadosTareas): string
    {
        if (!$estadosTareas || collect($estadosTareas)->every(fn ($e) => $e === 'Pendiente')) {
            return 'Pendiente';
        }
        if (collect($estadosTareas)->every(fn ($e) => $e === 'Estudiado')) {
            return 'Estudiado';
        }
        return 'En curso';
    }

    /** Sesiones cerradas del usuario entre dos fechas (inclusive), con nombre de asignatura. */
    private function sesionesEntre(int $uid, Carbon $desde, Carbon $hasta)
    {
        return DB::table('estudio_sesiones as s')
            ->join('estudio_asignaturas as a', 'a.id', '=', 's.id_asignatura')
            ->where('s.admin_user_id', $uid)->where('s.deleted', 0)
            ->whereNotNull('s.minutos')
            ->whereBetween('s.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->select('s.fecha', 's.minutos', 's.id_asignatura', 'a.nombre')
            ->get();
    }

    private function cronometroActivo(int $uid): ?array
    {
        $s = DB::table('estudio_sesiones as s')
            ->join('estudio_tareas as t', 't.id', '=', 's.id_tarea')
            ->where('s.admin_user_id', $uid)->where('s.deleted', 0)
            ->whereNull('s.fin')->where('s.origen', 'cronometro')
            ->select('s.id', 's.inicio', 's.id_tarea', 't.nombre as tarea')
            ->first();

        return $s ? [
            'id'        => $s->id,
            'id_tarea'  => $s->id_tarea,
            'tarea'     => $s->tarea,
            'inicio'    => Carbon::parse($s->inicio)->toIso8601String(),
        ] : null;
    }

    /** Cierra el cronómetro en marcha (si hay) y calcula los minutos, con tope de MAX_CRONOMETRO_MIN. */
    private function cerrarCronometro(int $uid): ?object
    {
        $activa = DB::table('estudio_sesiones')
            ->where('admin_user_id', $uid)->where('deleted', 0)
            ->whereNull('fin')->where('origen', 'cronometro')
            ->first();

        if (!$activa) {
            return null;
        }

        $ahora   = now();
        $minutos = (int) max(1, min(self::MAX_CRONOMETRO_MIN, round(Carbon::parse($activa->inicio)->diffInSeconds($ahora) / 60)));

        DB::table('estudio_sesiones')->where('id', $activa->id)->update([
            'fin'        => $ahora,
            'minutos'    => $minutos,
            'updatedat' => $ahora,
        ]);

        return DB::table('estudio_sesiones')->where('id', $activa->id)->first();
    }

    /** Tarea del usuario con su asignatura (id_asignatura viene del tema). 404 si no es suya. */
    private function tareaConAsignatura(object $user, int $idTarea): object
    {
        $tarea = DB::table('estudio_tareas as t')
            ->join('estudio_temas as tm', 'tm.id', '=', 't.id_tema')
            ->where('t.id', $idTarea)->where('t.admin_user_id', $user->id)->where('t.deleted', 0)
            ->select('t.id', 'tm.id_asignatura')
            ->first();

        if (!$tarea) abort(404, 'Tarea no encontrada');
        return $tarea;
    }

    /** Comprueba que un registro existe, no está borrado y es del usuario. 404 si no. */
    private function propio(string $tabla, object $user, int $id): object
    {
        $row = DB::table($tabla)->where('id', $id)->where('admin_user_id', $user->id)->where('deleted', 0)->first();
        if (!$row) abort(404, 'Registro no encontrado');
        return $row;
    }

    private function insertar(string $tabla, object $user, array $data): int
    {
        $ahora = now();
        return DB::table($tabla)->insertGetId(array_merge($data, [
            'admin_user_id' => $user->id,
            'createuser'    => $user->id,
            'updateuser'    => $user->id,
            'createdat'    => $ahora,
            'updatedat'    => $ahora,
        ]));
    }

    private function actualizar(string $tabla, object $user, int $id, array $data): void
    {
        DB::table($tabla)->where('id', $id)->update(array_merge($data, [
            'updateuser' => $user->id,
            'updatedat' => now(),
        ]));
    }

    private function perfil(object $authUser): array
    {
        return [
            'id'     => $authUser->id,
            'nombre' => $authUser->name ?? $authUser->email,
            'email'  => $authUser->email,
        ];
    }

    private function bearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');
        return str_starts_with($header, 'Bearer ') ? substr($header, 7) : null;
    }

    /** Valida el token Bearer y devuelve el admin_users del usuario. 401 si no es válido o caducó. */
    private function authenticate(Request $request): object
    {
        $token = $this->bearerToken($request);
        if (!$token) abort(401, 'Token requerido');

        $row = DB::table('estudio_pwa_tokens')
            ->where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (!$row) abort(401, 'Token invalido o expirado');

        DB::table('estudio_pwa_tokens')->where('id', $row->id)->update(['last_seen_at' => now()]);

        $user = DB::table('admin_users')->where('id', $row->admin_user_id)->first();
        if (!$user) abort(401, 'Usuario no encontrado');

        return $user;
    }
}
