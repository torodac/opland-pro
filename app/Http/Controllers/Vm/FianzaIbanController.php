<?php

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

// Número de cuenta del huésped para una devolución por transferencia.
//
// Filipinas recibe un enlace firmado y sin login. Dos cosas lo protegen: la firma temporal de
// Laravel (ruta con middleware `signed`) y un token aleatorio guardado en la reserva, que se
// regenera al reenviar el enlace. Sin las dos no se llega al formulario.
//
// El IBAN se guarda cifrado y solo lo ven Contabilidad, Dirección general y admin. Una vez
// guardado, no se vuelve a editar desde el enlace.
class FianzaIbanController extends Controller
{
    // Roles de VM que pueden leer el IBAN (vm_roles): Dirección general (3) y Contabilidad (7).
    public const ROLES_VER_IBAN = [3, 7];

    /** Enlace firmado y caducable para Filipinas. Regenera el token si no existe. */
    public static function enlaceParaFilipinas(int $reservaId): string
    {
        $token = DB::table('vm_reservas')->where('id', $reservaId)->value('fianza_token_iban');
        if (!$token) {
            $token = Str::random(40);
            DB::table('vm_reservas')->where('id', $reservaId)->update(['fianza_token_iban' => $token]);
        }

        return URL::temporarySignedRoute(
            'vm.fianza-iban',
            now()->addDays((int) config('services.vm_fianzas.iban_enlace_dias', 7)),
            ['id' => $reservaId, 't' => $token],
        );
    }

    /** Quién puede ver el IBAN en claro: admin o un usuario de VM con rol de dirección o contabilidad. */
    public static function puedeVerIban(?User $user): bool
    {
        if (!$user) {
            return false;
        }
        if ($user->isAdmin()) {
            return true;
        }

        return DB::table('vm_usuarios')
            ->where('admin_user_id', $user->id)
            ->where(fn($q) => $q->where('deleted', 0)->orWhereNull('deleted'))
            ->whereIn('id_rol', self::ROLES_VER_IBAN)
            ->exists();
    }

    /** El IBAN descifrado, o null si el usuario no puede verlo o no hay. */
    public static function ibanParaVer(int $reservaId, ?User $user): ?string
    {
        if (!self::puedeVerIban($user)) {
            return null;
        }

        $cifrado = DB::table('vm_reservas')->where('id', $reservaId)->value('fianza_iban_cifrado');

        return $cifrado ? Crypt::decryptString($cifrado) : null;
    }

    public function formulario(Request $request, int $id)
    {
        $reserva = $this->reservaConToken($request, $id);

        return view('vm.fianza-iban', [
            'reserva' => $reserva,
            'yaGuardado' => !empty($reserva->fianza_iban_guardado_at),
            'error' => null,
        ]);
    }

    public function guardar(Request $request, int $id)
    {
        $reserva = $this->reservaConToken($request, $id);

        if (!empty($reserva->fianza_iban_guardado_at)) {
            return view('vm.fianza-iban', ['reserva' => $reserva, 'yaGuardado' => true, 'error' => null]);
        }

        $iban = strtoupper(preg_replace('/\s+/', '', (string) $request->input('iban', '')));
        if (!self::ibanValido($iban)) {
            return view('vm.fianza-iban', [
                'reserva' => $reserva,
                'yaGuardado' => false,
                'error' => 'El número de cuenta no es válido. Revísalo: tiene que ser un IBAN completo.',
            ]);
        }

        DB::table('vm_reservas')->where('id', $id)->update([
            'fianza_iban_cifrado'     => Crypt::encryptString($iban),
            'fianza_iban_guardado_at' => now(),
            'updatedat'               => now(),
        ]);

        // La tarea de devolución de Carol nace aquí, porque por transferencia no hay nada que
        // mover hasta que se sabe a qué cuenta.
        $retencion = $reserva->estado_fianza === DevFianzasController::ESTADO_RETENER;
        app(DevFianzasController::class)->crearTareaSscc(
            $id,
            null,
            7,
            $retencion ? DevFianzasController::SSCC_RETENCION : DevFianzasController::SSCC_DEVOLUCION,
            'Transferir la devolución a la cuenta indicada por el huésped (IBAN ya en Opland).',
        );

        return view('vm.fianza-iban', ['reserva' => $reserva, 'yaGuardado' => true, 'error' => null]);
    }

    /** Comprueba el token y devuelve la reserva. Sin token válido, 404: no se revela si existe. */
    private function reservaConToken(Request $request, int $id)
    {
        $token = (string) $request->query('t', '');

        $reserva = DB::table('vm_reservas as r')
            ->leftJoin('vm_propiedades as p', 'p.id', '=', 'r.id_propiedades')
            ->where('r.id', $id)
            ->first(['r.id', 'r.booking_id', 'r.check_in_date', 'r.check_out_date', 'r.estado_fianza',
                     'r.fianza_token_iban', 'r.fianza_iban_guardado_at', 'p.nombre as propiedad']);

        abort_unless($reserva && $reserva->fianza_token_iban && $token !== ''
            && hash_equals($reserva->fianza_token_iban, $token), 404);

        return $reserva;
    }

    /**
     * Valida un IBAN: estructura por país (ES = 24 caracteres) y el módulo 97 de ISO 13616.
     * Comprueba que está bien escrito; no que la cuenta exista ni sea del huésped.
     */
    public static function ibanValido(string $iban): bool
    {
        if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban)) {
            return false;
        }
        if (str_starts_with($iban, 'ES') && strlen($iban) !== 24) {
            return false;
        }

        $reordenado = substr($iban, 4) . substr($iban, 0, 4);
        $resto = 0;
        foreach (str_split($reordenado) as $c) {
            // Las letras se pasan a dos cifras (A=10 … Z=35); el resto se va acumulando sin
            // construir el número entero, que no cabe en un int.
            $digitos = ctype_alpha($c) ? (string) (ord($c) - 55) : $c;
            foreach (str_split($digitos) as $d) {
                $resto = ($resto * 10 + (int) $d) % 97;
            }
        }

        return $resto === 1;
    }
}
