<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VacationmarbellaPwaController extends Controller
{
    // Auth

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

        $user = DB::table('vm_usuarios')
            ->where('admin_user_id', $authUser->id)
            ->where('deleted', 0)
            ->first();

        if (!$user) {
            return response()->json(['error' => 'Usuario sin acceso a la app'], 403);
        }

        $ttl       = $request->boolean('remember') ? 30 : 1;
        $token     = Str::random(64);
        $expiresAt = now()->addDays($ttl);

        DB::table('vm_pwa_tokens')->insert([
            'token'        => $token,
            'user_id'      => $user->id,
            'app'          => 'vm',
            'device'       => substr($request->header('User-Agent', ''), 0, 255),
            'expires_at'   => $expiresAt,
            'last_seen_at' => now(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $rol      = DB::table('vm_roles')->find($user->id_rol);
        $contrato = DB::table('vm_contratos')
            ->where('id_usuarios', $user->id)
            ->where('deleted', 0)
            ->orderByDesc('fecha_alta')
            ->first();

        return response()->json([
            'token'      => $token,
            'expires_at' => $expiresAt->toIso8601String(),
            'user'       => [
                'id'             => $user->id,
                'nombre'         => $user->nombre,
                'mail'           => $user->mail,
                'id_rol'         => $user->id_rol,
                'rol'            => $rol ? $rol->nombre : null,
                'horas_contrato' => $contrato ? (float) $contrato->horas_semana : null,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $token = $this->bearerToken($request);
        if ($token) {
            DB::table('vm_pwa_tokens')->where('token', $token)->delete();
        }
        return response()->json(['ok' => true]);
    }

    // Tareas

    public function tareasHoy(Request $request)
    {
        $user = $this->authenticate($request);
        $hoy  = now()->toDateString();
        $uid  = (string) $user->id;

        $cols = [
            't.id', 't.nombre', 't.descripcion', 't.comentario',
            't.fecha_planificada', 't.fecha_finalizacion',
            't.master_duraciones as id_duraciones',
            'd.nombre as duracion', 'd.minutos as duracion_minutos',
            'p.id as propiedad_id', 'p.nombre as propiedad_nombre',
            'p.icnea_address as direccion', 'p.icnea_city as ciudad',
            'p.icnea_latitude as lat', 'p.icnea_longitude as lng',
            'p.file_foto as propiedad_foto',
        ];

        $limpieza = DB::table('vm_tareas_limpieza as t')
            ->leftJoin('vm_propiedades as p', 'p.id', '=', 't.id_propiedades')
            ->leftJoin('master_duraciones as d', 'd.id', '=', 't.master_duraciones')
            ->where('t.deleted', 0)
            ->where('t.fecha_planificada', $hoy)
            ->whereRaw("t.control_user::jsonb @> ?::jsonb", [json_encode([$uid])])
            ->select(array_merge($cols, [DB::raw("'limpieza' as tipo")]))
            ->get();

        $mantenimiento = DB::table('vm_tareas_mantenimiento as t')
            ->leftJoin('vm_propiedades as p', 'p.id', '=', 't.id_propiedades')
            ->leftJoin('master_duraciones as d', 'd.id', '=', 't.master_duraciones')
            ->where('t.deleted', 0)
            ->where('t.fecha_planificada', $hoy)
            ->whereRaw("t.control_user::jsonb @> ?::jsonb", [json_encode([$uid])])
            ->select(array_merge($cols, [DB::raw("'mantenimiento' as tipo")]))
            ->get();

        $piscinas = DB::table('vm_tareas_piscinas as t')
            ->leftJoin('vm_propiedades as p', 'p.id', '=', 't.id_propiedades')
            ->leftJoin('master_duraciones as d', 'd.id', '=', 't.master_duraciones')
            ->where('t.deleted', 0)
            ->where('t.fecha_planificada', $hoy)
            ->whereRaw("t.control_user::jsonb @> ?::jsonb", [json_encode([$uid])])
            ->select(array_merge($cols, [DB::raw("'piscina' as tipo")]))
            ->get();

        $tareas = $limpieza->merge($mantenimiento)->merge($piscinas)->sortBy('nombre')->values();

        // Adjuntar fotos a cada tarea (con id para poder borrarlas)
        $tareas = $tareas->map(function ($t) {
            $col = match($t->tipo) {
                'limpieza'      => 'id_tareas_limpieza',
                'mantenimiento' => 'id_tareas_mantenimiento',
                'piscina'       => 'id_tareas_piscinas',
            };
            $fotos = DB::table('vm_fotos')
                ->where($col, $t->id)
                ->where('deleted', 0)
                ->get(['id', 'file_foto as path'])
                ->toArray();
            $t->fotos_detalle = $fotos;
            $t->fotos         = array_column($fotos, 'path');
            return $t;
        });

        return response()->json([
            'fecha'      => $hoy,
            'tareas'     => $tareas,
            'pendientes' => $tareas->whereNull('fecha_finalizacion')->count(),
        ]);
    }

    public function fichar(Request $request, string $tipo, int $id)
    {
        $user = $this->authenticate($request);
        $request->validate([
            'duracion' => 'required|integer|min:1',
            'estado'   => 'required|in:pendiente,pausada,finalizada,descartada',
        ]);

        $table = $this->resolveTable($tipo);
        $uid   = (string) $user->id;

        $tarea = DB::table($table)
            ->where('id', $id)
            ->where('deleted', 0)
            ->whereRaw("control_user::jsonb @> ?::jsonb", [json_encode([$uid])])
            ->first();

        if (!$tarea) {
            return response()->json(['error' => 'Tarea no encontrada'], 404);
        }

        DB::table('vm_imputaciones')->insert([
            'tipo'       => $tipo === 'piscina' ? 'piscina' : $tipo,
            'id_tarea'   => $id,
            'id_usuario' => $user->id,
            'duracion'   => $request->duracion,
            'estado'     => $request->estado,
            'fecha'      => now()->toDateString(),
            'createdat'  => now(),
        ]);

        // Comprobar cierre automático si el usuario finaliza o descarta
        if (in_array($request->estado, ['finalizada', 'descartada'])) {
            $this->comprobarCierreTarea($tarea, $table, $id);
        }

        return response()->json(['ok' => true]);
    }

    private function comprobarCierreTarea(object $tarea, string $table, int $id): void
    {
        $usuariosAsignados = json_decode($tarea->control_user ?? '[]', true);
        if (empty($usuariosAsignados)) return;

        // Para cada usuario, obtener su último estado en esta tarea
        $tipo = match($table) {
            'vm_tareas_limpieza'      => 'limpieza',
            'vm_tareas_mantenimiento' => 'mantenimiento',
            'vm_tareas_piscinas'      => 'piscina',
        };

        $hayFinalizada = false;
        foreach ($usuariosAsignados as $uid) {
            $ultima = DB::table('vm_imputaciones')
                ->where('tipo', $tipo)
                ->where('id_tarea', $id)
                ->where('id_usuario', (int) $uid)
                ->orderByDesc('createdat')
                ->value('estado');

            if (!in_array($ultima, ['finalizada', 'descartada'])) return;
            if ($ultima === 'finalizada') $hayFinalizada = true;
        }

        if ($hayFinalizada) {
            DB::table($table)->where('id', $id)->update([
                'fecha_finalizacion' => now()->toDateString(),
                'updatedat'          => now(),
            ]);
        }
    }

    public function completarTarea(Request $request, string $tipo, int $id)
    {
        $user  = $this->authenticate($request);
        $request->validate(['id_duraciones' => 'required|integer']);

        $table = $this->resolveTable($tipo);
        $uid   = (string) $user->id;

        $tarea = DB::table($table)
            ->where('id', $id)
            ->where('deleted', 0)
            ->whereRaw("control_user::jsonb @> ?::jsonb", [json_encode([$uid])])
            ->first();

        if (!$tarea) {
            return response()->json(['error' => 'Tarea no encontrada'], 404);
        }

        $data = [
            'master_duraciones'  => $request->id_duraciones,
            'fecha_finalizacion' => now()->toDateString(),
            'updateuser'         => $user->id,
            'updatedat'          => now(),
        ];

        if ($request->filled('comentario')) {
            $data['comentario'] = $request->comentario;
        }

        DB::table($table)->where('id', $id)->update($data);

        return response()->json(['ok' => true]);
    }

    public function subirFoto(Request $request, string $tipo, int $id)
    {
        $user  = $this->authenticate($request);
        $request->validate(['foto' => 'required|file|max:5120']);

        $table = $this->resolveTable($tipo);
        $uid   = (string) $user->id;

        $tarea = DB::table($table)
            ->where('id', $id)
            ->where('deleted', 0)
            ->whereRaw("control_user::jsonb @> ?::jsonb", [json_encode([$uid])])
            ->first();

        if (!$tarea) {
            return response()->json(['error' => 'Tarea no encontrada'], 404);
        }

        $file     = $request->file('foto');
        $filename = uniqid() . '.jpg';
        $destDir  = storage_path('app/public/vm/fotos');
        $destPath = $destDir . '/' . $filename;

        if (!is_dir($destDir)) mkdir($destDir, 0775, true);

        $this->resizeImage($file->getPathname(), $destPath, 1280, 500 * 1024);

        $path = 'vm/fotos/' . $filename;

        $fotoData = [
            'nombre'     => 'Foto PWA',
            'file_foto'  => $path,
            'createuser' => $user->id,
            'createdat'  => now(),
        ];

        if ($tipo === 'limpieza')      $fotoData['id_tareas_limpieza']      = $id;
        if ($tipo === 'mantenimiento') $fotoData['id_tareas_mantenimiento'] = $id;
        if ($tipo === 'piscina')       $fotoData['id_tareas_piscinas']      = $id;

        DB::table('vm_fotos')->insert($fotoData);

        return response()->json(['ok' => true, 'path' => $path]);
    }

    public function borrarFoto(Request $request, int $id)
    {
        $user = $this->authenticate($request);

        $foto = DB::table('vm_fotos')->where('id', $id)->where('deleted', 0)->first();

        if (!$foto) {
            return response()->json(['error' => 'Foto no encontrada'], 404);
        }

        // Verificar que la tarea pertenece al usuario
        $col   = $foto->id_tareas_limpieza      ? 'id_tareas_limpieza'
               : ($foto->id_tareas_mantenimiento ? 'id_tareas_mantenimiento' : 'id_tareas_piscinas');
        $tabla = $col === 'id_tareas_limpieza'      ? 'vm_tareas_limpieza'
               : ($col === 'id_tareas_mantenimiento' ? 'vm_tareas_mantenimiento' : 'vm_tareas_piscinas');
        $uid   = (string) $user->id;

        $ok = DB::table($tabla)
            ->where('id', $foto->$col)
            ->whereRaw("control_user::jsonb @> ?::jsonb", [json_encode([$uid])])
            ->exists();

        if (!$ok) {
            return response()->json(['error' => 'Sin permiso'], 403);
        }

        // Borrar archivo físico
        $physicalPath = storage_path('app/public/' . $foto->file_foto);
        if (file_exists($physicalPath)) {
            unlink($physicalPath);
        }

        DB::table('vm_fotos')->where('id', $id)->update(['deleted' => 1, 'updatedat' => now()]);

        return response()->json(['ok' => true]);
    }

    public function duraciones()
    {
        $d = DB::table('master_duraciones')->where('deleted', 0)->orderBy('minutos')->get();
        return response()->json($d);
    }

    // Fichaje

    public function fichajeHoy(Request $request)
    {
        $user  = $this->authenticate($request);
        $hoy   = now()->toDateString();
        $desde = now()->startOfMonth()->toDateString();

        $registros = DB::table('vm_fichaje')
            ->where('deleted', 0)
            ->where('control_user', $user->id)
            ->whereBetween('fecha_fichaje', [$desde, $hoy])
            ->orderBy('fecha_fichaje', 'desc')
            ->get();

        $contrato = DB::table('vm_contratos')
            ->where('id_usuarios', $user->id)
            ->where('deleted', 0)
            ->orderByDesc('fecha_alta')
            ->first();

        // Suma de duraciones de tareas completadas hoy
        $tareasHoyMin = 0;
        foreach (['vm_tareas_limpieza', 'vm_tareas_mantenimiento', 'vm_tareas_piscinas'] as $tabla) {
            $sum = DB::table($tabla . ' as t')
                ->join('master_duraciones as d', 'd.id', '=', 't.master_duraciones')
                ->where('t.deleted', 0)
                ->where('t.fecha_planificada', $hoy)
                ->whereNotNull('t.fecha_finalizacion')
                ->whereRaw("t.control_user::jsonb @> ?::jsonb", [json_encode([(string) $user->id])])
                ->sum('d.minutos');
            $tareasHoyMin += (int) $sum;
        }

        return response()->json([
            'hoy'           => $hoy,
            'fichaje'       => $registros->firstWhere('fecha_fichaje', $hoy),
            'mes'           => $registros,
            'horas_contrato' => $contrato ? (float) $contrato->horas_semana : null,
            'tareas_min'    => $tareasHoyMin,
        ]);
    }

    public function fichajeEntrada(Request $request)
    {
        $user = $this->authenticate($request);
        $hoy  = now()->toDateString();

        $existe = DB::table('vm_fichaje')
            ->where('fecha_fichaje', $hoy)
            ->where('deleted', 0)
            ->where('control_user', $user->id)
            ->exists();

        if ($existe) {
            return response()->json(['error' => 'Ya has fichado entrada hoy'], 409);
        }

        DB::table('vm_fichaje')->insert([
            'fecha_fichaje' => $hoy,
            'control_user'  => $user->id,
            'hora_inicio'   => now()->format('H:i:s'),
            'createuser'    => $user->id,
            'createdat'     => now(),
        ]);

        return response()->json(['ok' => true, 'hora_inicio' => now()->format('H:i')]);
    }

    public function fichajeSalida(Request $request)
    {
        $user = $this->authenticate($request);
        $hoy  = now()->toDateString();

        $fichaje = DB::table('vm_fichaje')
            ->where('fecha_fichaje', $hoy)
            ->where('deleted', 0)
            ->where('control_user', $user->id)
            ->first();

        if (!$fichaje) {
            return response()->json(['error' => 'No has fichado entrada hoy'], 404);
        }
        if ($fichaje->hora_fin) {
            return response()->json(['error' => 'Ya has fichado salida hoy'], 409);
        }

        DB::table('vm_fichaje')
            ->where('id', $fichaje->id)
            ->update(['hora_fin' => now()->format('H:i:s'), 'updateuser' => $user->id, 'updatedat' => now()]);

        return response()->json(['ok' => true, 'hora_fin' => now()->format('H:i')]);
    }

    public function fichajePausa(Request $request)
    {
        $user = $this->authenticate($request);
        $hoy  = now()->toDateString();

        $fichaje = DB::table('vm_fichaje')
            ->where('fecha_fichaje', $hoy)
            ->where('deleted', 0)
            ->where('control_user', $user->id)
            ->first();

        if (!$fichaje || !$fichaje->hora_inicio) {
            return response()->json(['error' => 'No has fichado entrada'], 404);
        }

        $update = ['updateuser' => $user->id, 'updatedat' => now()];

        if (!$fichaje->pausa_inicio) {
            $update['pausa_inicio'] = now()->format('H:i:s');
            $msg = 'Pausa iniciada';
        } elseif (!$fichaje->pausa_fin) {
            $update['pausa_fin'] = now()->format('H:i:s');
            $msg = 'Pausa finalizada';
        } else {
            return response()->json(['error' => 'La pausa ya esta registrada'], 409);
        }

        DB::table('vm_fichaje')->where('id', $fichaje->id)->update($update);

        return response()->json(['ok' => true, 'msg' => $msg]);
    }

    // Helpers

    private function resizeImage(string $src, string $dest, int $maxW, int $targetBytes): void
    {
        $info = @getimagesize($src);
        if (!$info) {
            copy($src, $dest);
            return;
        }

        [$origW, $origH, $type] = $info;

        $scale = min(1, $maxW / max($origW, $origH));
        $w     = (int) round($origW * $scale);
        $h     = (int) round($origH * $scale);

        $src_img = match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($src),
            IMAGETYPE_PNG  => imagecreatefrompng($src),
            IMAGETYPE_WEBP => imagecreatefromwebp($src),
            default        => null,
        };

        if (!$src_img) {
            copy($src, $dest);
            return;
        }

        $dst_img = imagecreatetruecolor($w, $h);
        imagefill($dst_img, 0, 0, imagecolorallocate($dst_img, 255, 255, 255));
        imagecopyresampled($dst_img, $src_img, 0, 0, 0, 0, $w, $h, $origW, $origH);
        imagedestroy($src_img);

        // Reduce calidad hasta quedar bajo targetBytes
        foreach ([80, 65, 50, 35] as $quality) {
            ob_start();
            imagejpeg($dst_img, null, $quality);
            $data = ob_get_clean();
            if (strlen($data) <= $targetBytes || $quality === 35) {
                file_put_contents($dest, $data);
                break;
            }
        }

        imagedestroy($dst_img);
    }

    private function authenticate(Request $request): object
    {
        $token = $this->bearerToken($request);

        if (!$token) {
            abort(401, 'Token requerido');
        }

        $row = DB::table('vm_pwa_tokens')
            ->where('token', $token)
            ->where('app', 'vm')
            ->where('expires_at', '>', now())
            ->first();

        if (!$row) {
            abort(401, 'Token invalido o expirado');
        }

        DB::table('vm_pwa_tokens')->where('id', $row->id)->update(['last_seen_at' => now()]);

        $user = DB::table('vm_usuarios')
            ->where('id', $row->user_id)
            ->where('deleted', 0)
            ->first();

        if (!$user) {
            abort(401, 'Usuario no encontrado');
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

    private function resolveTable(string $tipo): string
    {
        if ($tipo === 'limpieza')      return 'vm_tareas_limpieza';
        if ($tipo === 'mantenimiento') return 'vm_tareas_mantenimiento';
        if ($tipo === 'piscina')       return 'vm_tareas_piscinas';
        abort(400, 'Tipo de tarea invalido');
    }
}
