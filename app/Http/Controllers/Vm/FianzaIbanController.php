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
        if ($motivo = self::ibanError($iban)) {
            return view('vm.fianza-iban', [
                'reserva' => $reserva,
                'yaGuardado' => false,
                'error' => $motivo,
            ]);
        }

        DB::table('vm_reservas')->where('id', $id)->update([
            'fianza_iban_cifrado'     => Crypt::encryptString($iban),
            'fianza_iban_guardado_at' => now(),
            'updatedat'               => now(),
        ]);

        // Constancia del paso, sin el número de cuenta: el IBAN no se escribe en el registro.
        \App\Services\FianzaAuditoria::registrar($id, 'iban_guardado', ['via' => 'enlace']);

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
     * Formatos de los países que se aceptan: longitud total y la estructura del número sin el país
     * ni los dígitos de control. Mismos motivos de error que el comprobador de iban.com.
     */
    public const FORMATOS = [
        'ES' => [24, '^\d{20}$'],
        'GB' => [22, '^[A-Z]{4}\d{14}$'],
        'IE' => [22, '^[A-Z]{4}\d{14}$'],
        'DE' => [22, '^\d{18}$'],
        'FR' => [27, '^\d{10}[A-Z0-9]{11}\d{2}$'],
        'IT' => [27, '^[A-Z]\d{10}[A-Z0-9]{12}$'],
        'NL' => [18, '^[A-Z]{4}\d{10}$'],
        'PT' => [25, '^\d{21}$'],
        'BE' => [16, '^\d{12}$'],
    ];

    /**
     * El motivo por el que un IBAN no vale, en castellano, o null si vale. Comprueba la escritura,
     * el país, la longitud, la estructura y los dígitos de control. No comprueba que la cuenta exista.
     */
    public static function ibanError(string $raw): ?string
    {
        $iban = strtoupper(preg_replace('/\s+/', '', $raw));

        if ($iban === '') {
            return 'Escribe el número de cuenta.';
        }
        if (!preg_match('/^[A-Z0-9]+$/', $iban)) {
            return 'El número solo puede contener letras y cifras.';
        }
        if (!preg_match('/^[A-Z]{2}[0-9]{2}/', $iban)) {
            return 'Debe empezar por el código de país (2 letras) y 2 cifras de control.';
        }

        $pais = substr($iban, 0, 2);
        if (!isset(self::FORMATOS[$pais])) {
            return 'País no compatible con IBAN (' . $pais . ').';
        }

        [$largo, $estructura] = self::FORMATOS[$pais];
        if (strlen($iban) !== $largo) {
            return 'Longitud inválida: ' . $pais . ' debe tener ' . $largo . ' caracteres (tiene ' . strlen($iban) . ').';
        }
        if (!preg_match('/' . $estructura . '/', substr($iban, 4))) {
            return 'Estructura inválida: la cuenta no tiene el formato de ' . $pais . '.';
        }
        if (!self::mod97Valido($iban)) {
            return 'Dígitos de verificación inválidos: revisa el número.';
        }

        return null;
    }

    /** Un IBAN es válido si no tiene ningún motivo de error. */
    public static function ibanValido(string $iban): bool
    {
        return self::ibanError($iban) === null;
    }

    /** Módulo 97 (ISO 13616): dígitos de control. */
    public static function mod97Valido(string $iban): bool
    {

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
