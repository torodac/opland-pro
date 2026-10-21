<?php

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// Ficha de una reserva, con vista propia como la de fichaje. Los datos de la reserva vienen de Icnea
// y se muestran en solo lectura. Lo único que se edita aquí es la decisión de fianza (estado,
// retención, motivo, quién decidió y cuándo), y se hace con el permiso de Dev. fianzas.
//
// Ojo: cambiar la decisión a mano no crea tareas SSCC ni envía correos. Eso solo ocurre al decidir
// desde Dev. fianzas. Aquí queda para corregir o rehacer, no como vía normal de decisión.
class ReservaController extends Controller
{
    public const ESTADOS_EDITABLES = ['Pte. devolución', 'Pte. retención'];

    public function show(Project $project, int $id)
    {
        abort_unless(Auth::user()?->canViewTable($project, 'reservas'), 403);

        $reserva = DB::table('vm_reservas as r')
            ->leftJoin('vm_propiedades as p', 'p.id', '=', 'r.id_propiedades')
            ->where('r.id', $id)
            ->where(fn($q) => $q->where('r.deleted', 0)->orWhereNull('r.deleted'))
            ->first(['r.*', 'p.nombre as propiedad']);
        abort_unless($reserva, 404);

        $limpiezas = DB::table('vm_tareas_limpieza')
            ->where('id_reservas', $id)
            ->whereIn('Tipo', DevFianzasController::TIPOS_SALIDA)
            ->where(fn($q) => $q->where('deleted', 0)->orWhereNull('deleted'))
            ->orderBy('fecha_planificada')
            ->get(['id', 'nombre', 'fecha_planificada', 'estado']);

        $sscc = DB::table('vm_tareas_sscc')
            ->where('id_reservas', $id)
            ->where(fn($q) => $q->where('deleted', 0)->orWhereNull('deleted'))
            ->orderBy('id')
            ->get(['id', 'nombre', 'Tipo', 'estado']);

        // El estado que se enseña es el derivado, igual que en Dev. fianzas.
        $reserva->sscc_finalizada = ($sscc->count() > 0 && $sscc->every(fn($t) => $t->estado === 'Completada')) ? 1 : 0;

        $nombres = DB::table('admin_users')->pluck('name', 'id')->all();

        $secciones = [];
        foreach (self::SECCIONES as $titulo => $campos) {
            $filas = [];
            foreach ($campos as $campo) {
                $valor = match ($campo) {
                    'fianza_iban_cifrado' => $this->valorIban($id, $reserva),
                    'estancia'            => $this->estancia($reserva),
                    'ocupacion'           => $this->ocupacion($reserva),
                    default               => $this->formatea($campo, $reserva->$campo ?? null, $nombres),
                };
                $filas[] = [
                    'campo'     => $campo,
                    'etiqueta'  => self::ETIQUETAS[$campo] ?? $campo,
                    'valor'     => $valor,
                    'editable'  => in_array($campo, self::CAMPOS_EDITABLES, true),
                    // El canal se muestra con su logo, igual que en Dev. fianzas.
                    'logo'      => $campo === 'channel' ? iconoCanal($reserva->channel) : null,
                    // Icono de Tabler que va delante del valor de algunos campos de la reserva.
                    'icono'     => self::ICONOS[$campo] ?? null,
                ];
            }
            $secciones[$titulo] = $filas;
        }

        // La traza de cambios viene de la sincronización con Icnea: una entrada por línea,
        // «fecha · campo: antes → después». La más reciente, primero.
        $traza = array_values(array_reverse(array_filter(array_map('trim', explode("\n", (string) $reserva->trace)))));

        return view('vm.reserva', [
            'project'     => $project,
            'reserva'     => $reserva,
            'estado'      => DevFianzasController::estadoFianza($reserva),
            'secciones'   => $secciones,
            'traza'       => $traza,
            'auditoria'   => $this->auditoria($id),
            'limpiezas'   => $limpiezas,
            'sscc'        => $sscc,
            'usuarios'    => DB::table('admin_users')->orderBy('name')->get(['id', 'name']),
            'estados'     => self::ESTADOS_EDITABLES,
            'puedeEditar' => Auth::user()?->canEditTable($project, 'dev_fianzas_default') ?? false,
        ]);
    }

    /** Adultos, niños y bebés en un único campo: «2 adultos · 1 niño · 0 bebés». */
    private function ocupacion(object $reserva): string
    {
        $partes = [
            ((int) $reserva->number_of_adults) . ' ' . (((int) $reserva->number_of_adults) === 1 ? 'adulto' : 'adultos'),
            ((int) $reserva->number_of_children) . ' ' . (((int) $reserva->number_of_children) === 1 ? 'niño' : 'niños'),
            ((int) $reserva->number_of_infants) . ' ' . (((int) $reserva->number_of_infants) === 1 ? 'bebé' : 'bebés'),
        ];

        return implode(' · ', $partes);
    }

    /** Entrada y salida en un único campo: «dd/mm/aaaa → dd/mm/aaaa». */
    private function estancia(object $reserva): string
    {
        if (empty($reserva->check_in_date) && empty($reserva->check_out_date)) {
            return '—';
        }
        $entrada = $reserva->check_in_date ? \Carbon\Carbon::parse($reserva->check_in_date)->format('d/m/Y') : '—';
        $salida  = $reserva->check_out_date ? \Carbon\Carbon::parse($reserva->check_out_date)->format('d/m/Y') : '—';

        return $entrada . ' → ' . $salida;
    }

    /**
     * Valor que se enseña del número de cuenta. El descifrado solo lo ve quien puede: el resto ve que
     * está guardado. El token del enlace de Filipinas nunca se muestra; no aparece en SECCIONES.
     */
    private function valorIban(int $id, object $reserva): string
    {
        if (empty($reserva->fianza_iban_cifrado)) {
            return '—';
        }
        $iban = FianzaIbanController::ibanParaVer($id, Auth::user());

        return $iban ?? 'Guardado (solo lo ven Dirección general, Contabilidad y admin)';
    }

    public const ACCIONES_AUDITORIA = [
        'decision_conforme'   => 'Conformidad (devolución íntegra)',
        'decision_retencion'  => 'Retención',
        'foto_marcada'        => 'Foto marcada para retención',
        'foto_quitada'        => 'Foto desmarcada',
        'tarea_creada'        => 'Tarea SSCC creada',
        'tarea_completada'    => 'Tarea SSCC marcada como hecha',
        'tarea_hermana_borrada' => 'Tarea gemela borrada (la otra ya está hecha)',
        'correo_enviado'      => 'Correo enviado',
        'enlace_iban'         => 'Enlace para el número de cuenta generado',
        'iban_guardado'       => 'Número de cuenta guardado desde el enlace',
        'edicion_manual'      => 'Edición manual de la fianza',
    ];

    /**
     * Los pasos del proceso de devolución de la reserva, lo más reciente primero. Solo lectura: el
     * registro no se edita ni se borra desde ninguna pantalla.
     */
    private function auditoria(int $id): array
    {
        $filas = DB::table('vm_reservas_auditoria as a')
            ->leftJoin('admin_users as u', 'u.id', '=', 'a.id_usuario')
            ->where('a.id_reservas', $id)
            ->orderByDesc('a.id')
            ->get(['a.accion', 'a.detalle', 'a.created_at', 'u.name as usuario']);

        return $filas->map(fn($f) => [
            'fecha'   => \Carbon\Carbon::parse($f->created_at)->format('d/m/Y H:i'),
            'usuario' => $f->usuario ?: 'Automático',
            'accion'  => self::ACCIONES_AUDITORIA[$f->accion] ?? $f->accion,
            'texto'   => $this->detalleTexto(json_decode((string) $f->detalle, true) ?: []),
        ])->all();
    }

    /** Un detalle del registro como texto legible: «clave: valor», y los cambios como «campo: antes → después». */
    private function detalleTexto(array $detalle): string
    {
        $partes = [];
        foreach ($detalle as $clave => $valor) {
            if ($clave === 'campos' && is_array($valor)) {
                foreach ($valor as $campo => $cambio) {
                    $partes[] = (self::ETIQUETAS[$campo] ?? $campo) . ': '
                        . ($cambio['antes'] ?? '—') . ' → ' . ($cambio['despues'] ?? '—');
                }
                continue;
            }
            if ($valor === null || $valor === '') {
                continue;
            }
            $texto = is_bool($valor) ? ($valor ? 'Sí' : 'No') : (string) $valor;
            $partes[] = match ($clave) {
                'canal' => 'canal ' . $texto,
                'importe', 'retencion', 'fianza' => $clave . ' ' . number_format((float) $texto, 2, ',', '.') . ' €',
                'destinatario' => 'a ' . $texto,
                'caduca_dias' => 'caduca en ' . $texto . ' días',
                'sin_evidencias' => $valor ? 'sin evidencias' : null,
                'motivo' => 'motivo: ' . $texto,
                default => $clave . ': ' . $texto,
            };
        }

        return implode(' · ', array_filter($partes));
    }

    /** Un valor de la reserva en el formato que se enseña en la ficha. */
    private function formatea(string $campo, mixed $valor, array $nombres): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }
        if (in_array($campo, ['createuser', 'updateuser', 'fianza_usuario', 'novacion_user'], true)) {
            return $nombres[$valor] ?? ('#' . $valor);
        }
        if (in_array($campo, ['liquidado', 'novacion', 'deleted', 'hidden', 'blocked'], true)) {
            return ((int) $valor) === 1 ? 'Sí' : 'No';
        }
        if (in_array($campo, ['fianza', 'fianza_retencion', 'base_propietario', 'base_calculo'], true)) {
            return number_format((float) $valor, 2, ',', '.') . ' €';
        }
        if (in_array($campo, ['novacion_iva_pct', 'novacion_porc_honorarios'], true)) {
            return number_format((float) $valor, 2, ',', '.') . ' %';
        }
        if (in_array($campo, ['booking_date', 'check_in_date', 'check_out_date'], true)) {
            return \Carbon\Carbon::parse($valor)->format('d/m/Y');
        }
        if (in_array($campo, ['createdat', 'updatedat', 'icnea_updatedat', 'novacion_at', 'fianza_fecha', 'fianza_iban_guardado_at'], true)) {
            return \Carbon\Carbon::parse($valor)->format('d/m/Y H:i');
        }

        return (string) $valor;
    }

    // Campos de la reserva agrupados como se muestran. Todos los de vm_reservas salen aquí, salvo la
    // traza (que va aparte) y el token del enlace de Filipinas (que nunca se muestra).
    public const SECCIONES = [
        'Reserva' => ['booking_id', 'booking_status', 'booking_date', 'estancia',
                      'ocupacion', 'checkin_status',
                      'guest_name', 'guest_email', 'guest_phone', 'guest_language',
                      'propiedad', 'vm_propiedades_nombre', 'id_propiedades', 'channel', 'channel_booking_id', 'icnea_lodging_id'],
        'Importes reserva' => ['fianza', 'base_propietario', 'base_calculo', 'novacion_iva_pct', 'novacion_porc_honorarios'],
        'Fianza' => ['estado_fianza', 'fianza_retencion', 'fianza_motivo_retencion', 'fianza_usuario',
                     'fianza_fecha', 'fianza_canal_cobro', 'fianza_iban_cifrado', 'fianza_iban_guardado_at', 'fianza_departamento'],
        'Novación y liquidación' => ['liquidado', 'novacion', 'novacion_at', 'novacion_user'],
        // Registro va al final de la ficha: lo ve quien necesita la trazabilidad, no quien decide.
        'Registro' => ['id', 'nombre', 'createdat', 'createuser', 'updatedat', 'updateuser', 'icnea_updatedat',
                       'deleted', 'hidden', 'blocked'],
    ];

    public const ICONOS = [
        'guest_name' => 'ti-user', 'guest_phone' => 'ti-phone', 'guest_email' => 'ti-mail',
        'guest_language' => 'ti-language', 'propiedad' => 'ti-home',
    ];

    public const CAMPOS_EDITABLES =['estado_fianza', 'fianza_retencion', 'fianza_motivo_retencion', 'fianza_usuario', 'fianza_fecha'];

    public const ETIQUETAS = [
        'booking_id' => 'Booking', 'booking_status' => 'Estado de la reserva', 'booking_date' => 'Fecha de reserva',
        'estancia' => 'Estancia', 'ocupacion' => 'Ocupación',
        'check_in_date' => 'Entrada', 'check_out_date' => 'Salida', 'number_of_adults' => 'Adultos',
        'number_of_children' => 'Niños', 'number_of_infants' => 'Bebés', 'checkin_status' => 'Estado del check-in',
        'guest_name' => 'Nombre', 'guest_email' => 'Correo', 'guest_phone' => 'Teléfono', 'guest_language' => 'Idioma',
        'propiedad' => 'Propiedad', 'vm_propiedades_nombre' => 'Propiedad (Icnea)', 'id_propiedades' => 'Id propiedad',
        'channel' => 'Canal', 'channel_booking_id' => 'Id en el canal', 'icnea_lodging_id' => 'Id alojamiento Icnea',
        'fianza' => 'Fianza', 'estado_fianza' => 'Estado fianza', 'fianza_retencion' => 'Retención',
        'fianza_motivo_retencion' => 'Motivo de la retención', 'fianza_usuario' => 'Decidido por',
        'fianza_fecha' => 'Fecha de decisión', 'fianza_canal_cobro' => 'Canal de cobro',
        'fianza_iban_cifrado' => 'Número de cuenta (IBAN)', 'fianza_iban_guardado_at' => 'IBAN guardado el',
        'fianza_departamento' => 'Departamento de la fianza (anterior)',
        'liquidado' => 'Liquidado', 'novacion' => 'Novación', 'base_propietario' => 'Base del propietario',
        'base_calculo' => 'Base de cálculo', 'novacion_iva_pct' => 'IVA de la novación',
        'novacion_porc_honorarios' => 'Honorarios de la novación', 'novacion_at' => 'Novada el', 'novacion_user' => 'Novada por',
        'id' => 'Id', 'nombre' => 'Nombre interno', 'createdat' => 'Creada', 'createuser' => 'Creada por',
        'updatedat' => 'Modificada', 'updateuser' => 'Modificada por', 'icnea_updatedat' => 'Actualizada en Icnea',
        'deleted' => 'Borrada', 'hidden' => 'Oculta', 'blocked' => 'Bloqueada',
    ];

    /** Guarda la decisión de fianza tal como se edita en la ficha. Solo cambia los cinco campos de fianza. */
    public function guardarFianza(Request $request, Project $project, int $id)
    {
        abort_unless(Auth::user()?->canEditTable($project, 'dev_fianzas_default'), 403);

        $reserva = DB::table('vm_reservas')->where('id', $id)->first(['id', 'fianza', ...self::CAMPOS_EDITABLES]);
        abort_unless($reserva, 404);

        $datos = $request->validate([
            'estado_fianza'           => 'nullable|in:' . implode(',', self::ESTADOS_EDITABLES),
            'fianza_retencion'        => 'nullable|numeric|min:0',
            'fianza_motivo_retencion' => 'nullable|string|max:500',
            'fianza_usuario'          => 'nullable|integer|exists:admin_users,id',
            'fianza_fecha'            => 'nullable|date',
        ], [], [
            'estado_fianza' => 'estado de la fianza', 'fianza_retencion' => 'retención',
            'fianza_motivo_retencion' => 'motivo', 'fianza_usuario' => 'decidido por', 'fianza_fecha' => 'fecha de decisión',
        ]);

        $fianza = (float) ($reserva->fianza ?? 0);
        $retencion = round((float) ($datos['fianza_retencion'] ?? 0), 2);

        if ($fianza > 0 && $retencion > $fianza + 0.005) {
            return back()->withErrors(['fianza_retencion' => 'La retención no puede superar la fianza (' . number_format($fianza, 2, ',', '.') . ' €).'])->withInput();
        }
        if (($datos['estado_fianza'] ?? null) === 'Pte. retención' && $retencion <= 0) {
            return back()->withErrors(['fianza_retencion' => 'Una retención necesita un importe mayor que cero.'])->withInput();
        }

        // Un campo vacío del formulario llega como cadena vacía: se guarda como nulo, no como ''.
        $vacioANulo = fn($valor) => ($valor === '' || $valor === null) ? null : $valor;

        DB::table('vm_reservas')->where('id', $id)->update([
            'estado_fianza'           => $vacioANulo($datos['estado_fianza'] ?? null),
            'fianza_retencion'        => $retencion > 0 ? $retencion : null,
            'fianza_motivo_retencion' => $vacioANulo($datos['fianza_motivo_retencion'] ?? null),
            'fianza_usuario'          => $vacioANulo($datos['fianza_usuario'] ?? null),
            'fianza_fecha'            => $vacioANulo($datos['fianza_fecha'] ?? null),
            'updatedat'               => now(),
        ]);

        // Lo que cambió a mano, con su valor antes y después. Si no cambia nada, no se registra.
        $cambios = [];
        foreach (self::CAMPOS_EDITABLES as $campo) {
            $antes = $reserva->$campo;
            $despues = DB::table('vm_reservas')->where('id', $id)->value($campo);
            if ((string) $antes !== (string) $despues) {
                $cambios[$campo] = ['antes' => $antes, 'despues' => $despues];
            }
        }
        if ($cambios) {
            \App\Services\FianzaAuditoria::registrar($id, 'edicion_manual', ['campos' => $cambios]);
        }

        return redirect()->route('vm.reserva_form', [$project->slug, $id])->with('ok', 'Fianza guardada.');
    }
}
