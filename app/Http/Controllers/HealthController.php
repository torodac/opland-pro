<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HealthController extends Controller
{
    // ── Auth ─────────────────────────────────────────────────────────────────

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $authUser = DB::table('admin_users')
            ->where('email', $request->email)
            ->first();

        if (!$authUser || !password_verify($request->password, $authUser->password)) {
            return response()->json(['error' => 'Credenciales incorrectas'], 401);
        }

        // Nivel 1: role + fecha_baja
        $tieneRol = DB::table('admin_user_roles')
            ->where('user_id', $authUser->id)
            ->where('role', 'health_usuarios')
            ->where(function($q) { $q->whereNull('fecha_baja')->orWhere('fecha_baja', '>', now()); })
            ->exists();
        if (!$tieneRol) {
            return response()->json(['error' => 'Sin acceso a Health'], 403);
        }

        // Nivel 2: campo acceso en health_usuarios
        $acceso = DB::table('health_usuarios')
            ->where('admin_user_id', $authUser->id)
            ->where('deleted', 0)
            ->value('acceso');
        if (in_array($acceso, ['web', 'sin acceso'])) {
            return response()->json(['error' => 'Sin acceso a la app'], 403);
        }

        $ttl       = $request->boolean('remember') ? 30 : 1;
        $token     = Str::random(64);
        $expiresAt = now()->addDays($ttl);

        DB::table('health_pwa_tokens')->insert([
            'token'         => $token,
            'admin_user_id' => $authUser->id,
            'device'        => substr($request->header('User-Agent', ''), 0, 255),
            'expires_at'    => $expiresAt,
            'last_seen_at'  => now(),
            'createdat'    => now(),
            'updatedat'    => now(),
        ]);

        return response()->json([
            'token'      => $token,
            'expires_at' => $expiresAt->toIso8601String(),
            'user'       => [
                'nombre' => $authUser->name ?? $authUser->email,
                'mail'   => $authUser->email,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $token = $this->bearerToken($request);
        if ($token) {
            DB::table('health_pwa_tokens')->where('token', $token)->delete();
        }
        return response()->json(['ok' => true]);
    }

    // ── Daily log ─────────────────────────────────────────────────────────────

    public function getLog(Request $request, ?string $date = null)
    {
        $user = $this->authenticate($request);
        $date = $date ?? now()->toDateString();

        $log = DB::table('health_daily_logs')
            ->where('admin_user_id', $user->id)
            ->where('log_date', $date)
            ->first();

        return response()->json($log ?? (object)[
            'log_date'        => $date,
            'weight_kg'       => null,
            'breakfast'       => null, 'breakfast_bad'   => false,
            'mid_morning'     => null, 'mid_morning_bad' => false,
            'lunch'           => null, 'lunch_bad'       => false,
            'snack'           => null, 'snack_bad'       => false,
            'dinner'          => null, 'dinner_bad'      => false,
            'sport'           => null,
        ]);
    }

    public function upsertLog(Request $request, string $date)
    {
        $user = $this->authenticate($request);

        $fields = $request->only([
            'weight_kg',
            'breakfast',  'breakfast_bad',
            'mid_morning','mid_morning_bad',
            'lunch',      'lunch_bad',
            'snack',      'snack_bad',
            'dinner',     'dinner_bad',
            'sport',
        ]);

        // Castear booleans que llegan como string desde JSON
        foreach (['breakfast_bad','mid_morning_bad','lunch_bad','snack_bad','dinner_bad'] as $k) {
            if (array_key_exists($k, $fields)) {
                $fields[$k] = filter_var($fields[$k], FILTER_VALIDATE_BOOLEAN);
            }
        }

        $existing = DB::table('health_daily_logs')
            ->where('admin_user_id', $user->id)
            ->where('log_date', $date)
            ->first();

        if ($existing) {
            DB::table('health_daily_logs')
                ->where('id', $existing->id)
                ->update(array_merge($fields, ['updatedat' => now()]));
        } else {
            DB::table('health_daily_logs')->insert(array_merge($fields, [
                'admin_user_id' => $user->id,
                'log_date'      => $date,
                'createdat'    => now(),
                'updatedat'    => now(),
            ]));
        }

        return response()->json(['ok' => true]);
    }

    // ── Weight history ────────────────────────────────────────────────────────

    public function weightHistory(Request $request)
    {
        $user = $this->authenticate($request);
        $from = now()->subDays(14)->toDateString();

        $rows = DB::table('health_daily_logs')
            ->where('admin_user_id', $user->id)
            ->where('log_date', '>=', $from)
            ->orderBy('log_date')
            ->get([
                'log_date', 'weight_kg', 'sport',
                'breakfast_bad','mid_morning_bad','lunch_bad','snack_bad','dinner_bad',
            ]);

        $data = $rows->map(function ($r) {
            $anyBad = $r->breakfast_bad || $r->mid_morning_bad
                   || $r->lunch_bad    || $r->snack_bad
                   || $r->dinner_bad;
            return [
                'date'      => $r->log_date,
                'weight'    => $r->weight_kg ? (float) $r->weight_kg : null,
                'has_sport' => !empty($r->sport),
                'any_bad'   => (bool) $anyBad,
            ];
        });

        return response()->json($data);
    }


    // ── Push subscriptions ────────────────────────────────────────────────────

    public function pushSubscribe(Request $request)
    {
        $user = $this->authenticate($request);
        $request->validate([
            'endpoint' => 'required|string|max:1000',
            'p256dh'   => 'required|string|max:500',
            'auth'     => 'required|string|max:200',
        ]);

        DB::table('health_push_subscriptions')->updateOrInsert(
            ['admin_user_id' => $user->id, 'endpoint' => $request->endpoint],
            [
                'p256dh'    => $request->p256dh,
                'auth'      => $request->auth,
                'updatedat' => now(),
                'createdat' => now(),
            ]
        );

        return response()->json(['ok' => true]);
    }


    // ── Exercise ──────────────────────────────────────────────────────────────

    public function muscleGroups(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authenticate($request);
        $groups    = DB::table('health_muscle_groups')->orderBy('orden')->get();
        $exercises = DB::table('health_exercises')->orderBy('muscle_group_id')->orderBy('orden')->get();
        return response()->json(['groups' => $groups, 'exercises' => $exercises]);
    }

    public function exerciseLog(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $this->authenticate($request);
        $date = $request->query('date', now()->toDateString());
        $rows = DB::table('health_exercise_log')
            ->where('admin_user_id', $user->id)
            ->where('log_date', $date)
            ->get(['exercise_id', 'count', 'weight_kg']);
        return response()->json(['log' => $rows]);
    }

    public function toggleExercise(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $this->authenticate($request);
        $request->validate(['date' => 'required|date', 'exercise_id' => 'required|integer',
            'weight_kg' => 'nullable|numeric|min:0|max:999']);
        DB::statement(
            'INSERT INTO health_exercise_log (admin_user_id, log_date, exercise_id, count, created_at, updated_at)
             VALUES (?, ?, ?, 1, now(), now())
             ON CONFLICT (admin_user_id, log_date, exercise_id)
             DO UPDATE SET count = health_exercise_log.count + 1, updated_at = now()',
            [$user->id, $request->date, $request->exercise_id]
        );
        // Actualizar peso si se ha enviado
        if ($request->filled('weight_kg')) {
            DB::table('health_exercise_log')
                ->where('admin_user_id', $user->id)
                ->where('log_date', $request->date)
                ->where('exercise_id', $request->exercise_id)
                ->update(['weight_kg' => $request->weight_kg]);
        }
        $row = DB::table('health_exercise_log')
            ->where('admin_user_id', $user->id)
            ->where('log_date', $request->date)
            ->where('exercise_id', $request->exercise_id)
            ->first(['count', 'weight_kg']);
        return response()->json(['count' => $row->count, 'weight_kg' => $row->weight_kg]);
    }

    public function updateExerciseWeight(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $this->authenticate($request);
        $request->validate([
            'date'        => 'required|date',
            'exercise_id' => 'required|integer',
            'weight_kg'   => 'nullable|numeric|min:0|max:999',
        ]);
        $updated = DB::table('health_exercise_log')
            ->where('admin_user_id', $user->id)
            ->where('log_date', $request->date)
            ->where('exercise_id', $request->exercise_id)
            ->update(['weight_kg' => $request->weight_kg, 'updated_at' => now()]);
        return response()->json(['ok' => (bool)$updated]);
    }

    public function removeExercise(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $this->authenticate($request);
        $date = $request->query('date');
        $eid  = $request->query('exercise_id');
        DB::table('health_exercise_log')
            ->where('admin_user_id', $user->id)
            ->where('log_date', $date)
            ->where('exercise_id', $eid)
            ->delete();
        return response()->json(['ok' => true]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function authenticate(Request $request): object
    {
        $token = $this->bearerToken($request);

        if (!$token) {
            abort(401, 'Token requerido');
        }

        $row = DB::table('health_pwa_tokens')
            ->where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (!$row) {
            abort(401, 'Token invalido o expirado');
        }

        DB::table('health_pwa_tokens')
            ->where('id', $row->id)
            ->update(['last_seen_at' => now()]);

        $user = DB::table('admin_users')->where('id', $row->admin_user_id)->first();

        if (!$user) {
            abort(401, 'Usuario no encontrado');
        }

        $tieneAcceso = DB::table('admin_user_roles')
            ->where('user_id', $user->id)
            ->where('role', 'health_usuarios')
            ->where(function($q) { $q->whereNull('fecha_baja')->orWhere('fecha_baja', '>', now()); })
            ->exists();

        if (!$tieneAcceso) {
            abort(401, 'Acceso revocado');
        }

        // Nivel 2: campo acceso en health_usuarios
        $acceso = DB::table('health_usuarios')
            ->where('admin_user_id', $user->id)
            ->where('deleted', 0)
            ->value('acceso');
        if (in_array($acceso, ['web', 'sin acceso'])) {
            abort(401, 'Acceso revocado');
        }

        return $user;
    }

    private function bearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }
        return null;
    }
}
