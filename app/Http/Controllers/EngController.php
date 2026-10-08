<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EngController extends Controller
{
    private function authUser(Request $request): ?object
    {
        $token = $request->bearerToken();
        if (!$token) return null;
        $row = DB::table('eng_pwa_tokens')
            ->where('token', $token)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();
        if (!$row) return null;
        DB::table('eng_pwa_tokens')
            ->where('token', $token)
            ->update(['last_seen_at' => now()]);
        return DB::table('admin_users')->where('id', $row->admin_user_id)->first();
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);
        $user = DB::table('admin_users')->where('email', $data['email'])->first();
        if (!$user || !\Hash::check($data['password'], $user->password)) {
            return response()->json(['error' => 'Credenciales incorrectas'], 401);
        }
        $token = Str::random(64);
        DB::table('eng_pwa_tokens')->insert([
            'admin_user_id' => $user->id,
            'token'         => $token,
            'device'        => $request->userAgent() ?? 'unknown',
            'expires_at'    => now()->addYear(),
            'last_seen_at'  => now(),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
        return response()->json([
            'token' => $token,
            'user'  => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ]);
    }

    public function logout(Request $request)
    {
        $token = $request->bearerToken();
        if ($token) DB::table('eng_pwa_tokens')->where('token', $token)->delete();
        return response()->json(['ok' => true]);
    }

    // Libraries the user owns or has been shared
    public function libraries(Request $request)
    {
        $user = $this->authUser($request);
        if (!$user) return response()->json(['error' => 'No autorizado'], 401);

        $libs = DB::table('eng_librerias as l')
            ->leftJoin('eng_librerias_usuarios as lu', function ($j) use ($user) {
                $j->on('lu.libreria_id', '=', 'l.id')
                  ->where('lu.admin_user_id', $user->id);
            })
            ->where(function ($q) use ($user) {
                $q->where('l.admin_user_id', $user->id)
                  ->orWhereNotNull('lu.id');
            })
            ->select(
                'l.id', 'l.nombre', 'l.descripcion', 'l.admin_user_id',
                DB::raw('(l.admin_user_id = ' . $user->id . ') as is_owner')
            )
            ->get();

        // Add word counts
        foreach ($libs as $lib) {
            $lib->total = DB::table('eng_vocabulario')->where('libreria_id', $lib->id)->count();
        }

        return response()->json($libs);
    }

    public function shareLibrary(Request $request, int $id)
    {
        $user = $this->authUser($request);
        if (!$user) return response()->json(['error' => 'No autorizado'], 401);

        $lib = DB::table('eng_librerias')->where('id', $id)->where('admin_user_id', $user->id)->first();
        if (!$lib) return response()->json(['error' => 'Biblioteca no encontrada o no eres el propietario'], 404);

        $data = $request->validate(['email' => 'required|email']);
        $target = DB::table('admin_users')->where('email', $data['email'])->first();
        if (!$target) return response()->json(['error' => 'Usuario no encontrado'], 404);
        if ($target->id === $user->id) return response()->json(['error' => 'No puedes compartir contigo mismo'], 422);

        DB::table('eng_librerias_usuarios')->updateOrInsert(
            ['libreria_id' => $id, 'admin_user_id' => $target->id],
            ['created_at' => now(), 'updated_at' => now()]
        );

        return response()->json(['ok' => true, 'shared_with' => $target->name]);
    }

    public function card(Request $request)
    {
        $user = $this->authUser($request);
        if (!$user) return response()->json(['error' => 'No autorizado'], 401);

        $libId = $request->query('libreria_id');

        // Build accessible library IDs
        $ownedIds = DB::table('eng_librerias')
            ->where('admin_user_id', $user->id)->pluck('id');
        $sharedIds = DB::table('eng_librerias_usuarios')
            ->where('admin_user_id', $user->id)->pluck('libreria_id');
        $accessIds = $ownedIds->merge($sharedIds)->unique()->values();

        if ($libId) {
            if (!$accessIds->contains((int)$libId)) {
                return response()->json(['error' => 'Sin acceso a esta biblioteca'], 403);
            }
            $accessIds = collect([(int)$libId]);
        }

        $card = DB::table('eng_vocabulario as v')
            ->leftJoin('eng_progress as p', function ($j) use ($user) {
                $j->on('p.vocab_id', '=', 'v.id')
                  ->where('p.admin_user_id', $user->id);
            })
            ->whereIn('v.libreria_id', $accessIds)
            ->where(function ($q) {
                $q->whereNull('p.next_due')
                  ->orWhere('p.next_due', '<=', now());
            })
            ->select(
                'v.id', 'v.nombre', 'v.frase', 'v.traduccion',
                'p.seen_count', 'p.error_count', 'p.interval_days', 'p.ease_factor'
            )
            ->orderByRaw("COALESCE(p.next_due, '1970-01-01') ASC")
            ->orderByRaw('RANDOM()')
            ->limit(1)
            ->first();

        if (!$card) {
            return response()->json(['done' => true]);
        }

        return response()->json(['done' => false] + (array)$card);
    }

    public function answer(Request $request, int $vocabId)
    {
        $user = $this->authUser($request);
        if (!$user) return response()->json(['error' => 'No autorizado'], 401);

        $data   = $request->validate(['result' => 'required|in:ok,ko']);
        $result = $data['result'];

        $prog = DB::table('eng_progress')
            ->where('admin_user_id', $user->id)
            ->where('vocab_id', $vocabId)
            ->first();

        $interval = $prog ? $prog->interval_days : 0;
        $ease     = $prog ? (float)$prog->ease_factor : 2.50;

        if ($result === 'ok') {
            $interval = match (true) {
                $interval == 0 => 1,
                $interval == 1 => 3,
                default        => (int)round($interval * $ease),
            };
            $ease = min(2.5, $ease + 0.05);
        } else {
            $interval = 1;
            $ease     = max(1.3, $ease - 0.15);
        }

        $nextDue    = now()->addDays($interval);
        $isKo       = $result === 'ko' ? 1 : 0;
        $userId     = $user->id;
        $nowTs      = now()->toDateTimeString();
        $nextDueStr = $nextDue->toDateTimeString();

        // INSERT ... ON CONFLICT DO UPDATE: seguro tanto en insert como en update.
        // updateOrInsert con DB::raw falla en INSERT porque PostgreSQL no permite
        // referenciar la columna propia en VALUES cuando la fila aún no existe.
        DB::statement("
            INSERT INTO eng_progress
                (admin_user_id, vocab_id, seen_count, error_count,
                 interval_days, ease_factor, last_seen, next_due, created_at, updated_at)
            VALUES
                (?, ?, 1, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT (admin_user_id, vocab_id) DO UPDATE SET
                seen_count    = eng_progress.seen_count + 1,
                error_count   = eng_progress.error_count + ?,
                interval_days = ?,
                ease_factor   = ?,
                last_seen     = ?,
                next_due      = ?,
                updated_at    = ?
        ", [
            $userId, $vocabId, $isKo, $interval, $ease, $nowTs, $nextDueStr, $nowTs, $nowTs,
            // ON CONFLICT SET params
            $isKo, $interval, $ease, $nowTs, $nextDueStr, $nowTs,
        ]);

        return response()->json(['ok' => true, 'interval' => $interval]);
    }

    public function stats(Request $request)
    {
        $user = $this->authUser($request);
        if (!$user) return response()->json(['error' => 'No autorizado'], 401);

        $libId = $request->query('libreria_id');

        $ownedIds = DB::table('eng_librerias')
            ->where('admin_user_id', $user->id)->pluck('id');
        $sharedIds = DB::table('eng_librerias_usuarios')
            ->where('admin_user_id', $user->id)->pluck('libreria_id');
        $accessIds = $ownedIds->merge($sharedIds)->unique()->values();

        if ($libId && $accessIds->contains((int)$libId)) {
            $accessIds = collect([(int)$libId]);
        }

        $total = DB::table('eng_vocabulario')->whereIn('libreria_id', $accessIds)->count();

        $due_now = DB::table('eng_vocabulario as v')
            ->leftJoin('eng_progress as p', function ($j) use ($user) {
                $j->on('p.vocab_id', '=', 'v.id')->where('p.admin_user_id', $user->id);
            })
            ->whereIn('v.libreria_id', $accessIds)
            ->where(function ($q) {
                $q->whereNull('p.next_due')->orWhere('p.next_due', '<=', now());
            })
            ->count();

        $learned = DB::table('eng_progress')
            ->where('admin_user_id', $user->id)
            ->where('interval_days', '>=', 7)
            ->whereIn('vocab_id', DB::table('eng_vocabulario')->whereIn('libreria_id', $accessIds)->pluck('id'))
            ->count();

        $studied_today = DB::table('eng_progress')
            ->where('admin_user_id', $user->id)
            ->whereDate('last_seen', today())
            ->count();

        return response()->json(compact('total', 'due_now', 'learned', 'studied_today'));
    }

    public function wordList(Request $request)
    {
        $user = $this->authUser($request);
        if (!$user) return response()->json(['error' => 'No autorizado'], 401);

        $libId = $request->query('libreria_id');

        $ownedIds  = DB::table('eng_librerias')->where('admin_user_id', $user->id)->pluck('id');
        $sharedIds = DB::table('eng_librerias_usuarios')->where('admin_user_id', $user->id)->pluck('libreria_id');
        $accessIds = $ownedIds->merge($sharedIds)->unique()->values();

        if ($libId) {
            if (!$accessIds->contains((int)$libId)) {
                return response()->json(['error' => 'Sin acceso a esta biblioteca'], 403);
            }
            $accessIds = collect([(int)$libId]);
        }

        $words = DB::table('eng_vocabulario as v')
            ->leftJoin('eng_progress as p', function ($j) use ($user) {
                $j->on('p.vocab_id', '=', 'v.id')
                  ->where('p.admin_user_id', $user->id);
            })
            ->whereIn('v.libreria_id', $accessIds)
            ->where(function ($q) {
                $q->whereNull('p.next_due')
                  ->orWhere('p.next_due', '<=', now());
            })
            ->select(
                'v.id', 'v.nombre', 'v.traduccion',
                'p.seen_count', 'p.error_count', 'p.interval_days', 'p.ease_factor', 'p.next_due'
            )
            ->orderByRaw("COALESCE(p.next_due, '1970-01-01') ASC")
            ->orderByRaw('RANDOM()')
            ->limit(40)
            ->get();

        if ($words->isEmpty()) {
            return response()->json(['done' => true, 'words' => []]);
        }

        return response()->json(['done' => false, 'words' => $words]);
    }


    public function resetProgress(\Illuminate\Http\Request $request)
    {
        $user = $this->authUser($request);
        if (!$user) return response()->json(['error' => 'No autorizado'], 401);

        $libId = $request->query('libreria_id');
        $ownedIds  = DB::table('eng_librerias')->where('admin_user_id', $user->id)->pluck('id');
        $sharedIds = DB::table('eng_librerias_usuarios')->where('admin_user_id', $user->id)->pluck('libreria_id');
        $accessIds = $ownedIds->merge($sharedIds)->unique()->values();
        if ($libId && $accessIds->contains((int)$libId)) {
            $accessIds = collect([(int)$libId]);
        }
        $vocabIds = DB::table('eng_vocabulario')->whereIn('libreria_id', $accessIds)->pluck('id');
        $deleted  = DB::table('eng_progress')
            ->where('admin_user_id', $user->id)
            ->whereIn('vocab_id', $vocabIds)
            ->delete();
        return response()->json(['ok' => true, 'reset' => $deleted]);
    }


    public function updateVocab(\Illuminate\Http\Request $request, int $id)
    {
        $user = $this->authUser($request);
        if (!$user) return response()->json(['error' => 'No autorizado'], 401);

        // Only the owner of the library can edit
        $word = DB::table('eng_vocabulario as v')
            ->join('eng_librerias as l', 'l.id', '=', 'v.libreria_id')
            ->where('v.id', $id)
            ->select('v.id', 'l.admin_user_id as owner_id')
            ->first();

        if (!$word) return response()->json(['error' => 'Palabra no encontrada'], 404);
        if ($word->owner_id != $user->id) return response()->json(['error' => 'Solo el propietario puede editar'], 403);

        $data = $request->validate([
            'nombre'     => 'sometimes|string|max:500',
            'traduccion' => 'sometimes|nullable|string|max:1000',
            'frase'      => 'sometimes|nullable|string|max:2000',
        ]);

        DB::table('eng_vocabulario')->where('id', $id)->update(array_merge($data, ['updated_at' => now()]));

        return response()->json(['ok' => true]);
    }

}
