<?php

namespace App\Http\Controllers\Vm;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

// Importador del "Resumen Contable" (nóminas) en PDF hacia vm_nominas. El PDF es una tabla de
// ancho fijo (una fila por trabajador, con NIF, Devengado, Líquido y Coste Total entre otras
// columnas) -- se extrae con `pdftotext -layout` (ya disponible en el servidor, sin añadir una
// dependencia de Composer) y se localizan las 3 columnas que nos interesan por la posición de
// caracter de su cabecera, no por orden de aparición: muchas columnas intermedias vienen vacías
// en according a cada trabajador y un split por espacios simple desalinearía los valores.
class NominasImportController extends Controller
{
    public function index(Project $project)
    {
        // Resumen por mes ya importado -- mismo patrón que PygController::index() (tabla de
        // períodos con sus totales, debajo de la zona de subida).
        $meses = DB::table('vm_nominas')
            ->where('deleted', 0)
            ->selectRaw('mes, count(*) as num_nominas, sum(devengado) as devengado, sum(liquido) as liquido, sum(coste_total) as coste_total')
            ->groupBy('mes')
            ->orderByDesc('mes')
            ->get();

        return view('vm.nominas-import', [
            'project'    => $project,
            'meses'      => $meses,
            'breadcrumb' => [
                ['label' => 'Importar nóminas', 'url' => ''],
            ],
        ]);
    }

    // Solo lee y cruza el PDF -- no escribe nada. El front muestra el resumen (cuantas se crean/
    // actualizan, y sobre todo quien no tiene usuario en Opland) y el usuario decide si continuar.
    public function previsualizar(Request $request, Project $project)
    {
        [$error, $resumen] = $this->procesarPdf($request, aplicar: false);
        if ($error) {
            return response()->json(['error' => $error], 422);
        }
        return response()->json(['ok' => true] + $resumen);
    }

    // Mismo calculo que previsualizar(), pero esta vez sí escribe en vm_nominas. Se vuelve a
    // parsear el PDF entero en vez de reutilizar un resultado en cache -- es determinista y
    // barato (igual que el "Ejecutar" de mb/movs_mapeo), no hace falta guardar estado intermedio.
    public function aplicar(Request $request, Project $project)
    {
        [$error, $resumen] = $this->procesarPdf($request, aplicar: true);
        if ($error) {
            return response()->json(['error' => $error], 422);
        }
        return response()->json(['ok' => true] + $resumen);
    }

    private function procesarPdf(Request $request, bool $aplicar): array
    {
        $request->validate(['file' => 'required|file|mimes:pdf']);

        $texto = $this->pdfATexto($request->file('file')->getRealPath());
        if ($texto === null) {
            return ['No se ha podido leer el PDF (pdftotext no disponible o fichero ilegible).', null];
        }

        $filas = $this->parsearFilas($texto);
        if (empty($filas)) {
            return ['No se ha reconocido ninguna fila de trabajador en el PDF. ¿Es un "Resumen Contable" con el mismo formato de siempre?', null];
        }

        // Sin filtrar deleted=0: las nominas son historico financiero, un trabajador de baja
        // sigue teniendo derecho a que su nomina de cuando trabajaba quede registrada.
        // Normalizado a mayusculas por ambos lados: algun dni en BD esta guardado en minusculas
        // (comprobado, ej. "79043264s"), y el NIF del PDF siempre viene en mayusculas.
        $usuariosPorDni = DB::table('vm_usuarios')
            ->whereNotNull('dni')
            ->where('dni', '!=', '')
            ->get(['id', 'dni'])
            ->mapWithKeys(fn($u) => [mb_strtoupper($u->dni) => $u->id]);

        $importadas   = 0;
        $actualizadas = 0;
        $sinMatch     = [];
        $now          = now();

        DB::transaction(function () use ($filas, $usuariosPorDni, $aplicar, &$importadas, &$actualizadas, &$sinMatch, $now) {
            foreach ($filas as $fila) {
                $idUsuario = $usuariosPorDni[$fila['nif']] ?? null;
                if (!$idUsuario) {
                    $sinMatch[] = $fila;
                    continue;
                }

                $existente = DB::table('vm_nominas')
                    ->where('id_usuario', $idUsuario)
                    ->where('mes', $fila['mes'])
                    ->first(['id']);

                if ($existente) {
                    $actualizadas++;
                } else {
                    $importadas++;
                }

                if (!$aplicar) continue;

                $data = [
                    'devengado'   => $fila['devengado'],
                    'liquido'     => $fila['liquido'],
                    'coste_total' => $fila['coste_total'],
                    'updatedat'   => $now,
                ];

                if ($existente) {
                    DB::table('vm_nominas')->where('id', $existente->id)->update($data);
                } else {
                    DB::table('vm_nominas')->insert($data + [
                        'id_usuario' => $idUsuario,
                        'mes'        => $fila['mes'],
                        'deleted'    => 0,
                        'createdat'  => $now,
                    ]);
                }
            }
        });

        return [null, [
            'total_filas'  => count($filas),
            'importadas'   => $importadas,
            'actualizadas' => $actualizadas,
            'sin_match'    => array_map(fn($f) => [
                'nif'       => $f['nif'],
                'nombre'    => $f['nombre'],
                'devengado' => $f['devengado'],
            ], $sinMatch),
            'anomalos'     => $this->detectarAnomalias($filas),
        ]];
    }

    // Filas cuya relación Devengado/Líquido/Coste Total se sale de lo normal para este mismo
    // lote -- pensado para pillar en el momento errores de lectura del PDF (como el bug real de
    // columnas desalineadas que truncaba Líquido/Coste Total a un número pequeño), no solo
    // trabajadores sin usuario en Opland. Dos reglas fijas (imposibles en cualquier nómina real)
    // más una comparación contra la mediana del propio lote (la pista que dio el usuario: "la
    // proporción entre los 3 importes tiene que ser similar para todos los usuarios"). Es
    // orientativo, no bloquea la importación -- el usuario decide si continúa.
    private function detectarAnomalias(array $filas): array
    {
        $ratiosLiq = $ratiosCoste = [];
        foreach ($filas as $f) {
            if ($f['devengado'] > 0) {
                $ratiosLiq[]   = $f['liquido'] / $f['devengado'];
                $ratiosCoste[] = $f['coste_total'] / $f['devengado'];
            }
        }
        $medianaLiq   = $this->mediana($ratiosLiq);
        $medianaCoste = $this->mediana($ratiosCoste);

        $anomalos = [];
        foreach ($filas as $f) {
            $motivos = [];

            if ($f['coste_total'] > 0 && $f['coste_total'] < $f['liquido']) {
                $motivos[] = 'Coste Total menor que Líquido (imposible)';
            }
            if ($f['devengado'] > 0 && $f['liquido'] > $f['devengado']) {
                $motivos[] = 'Líquido mayor que Devengado';
            }
            if ($f['devengado'] > 0) {
                $ratioLiq   = $f['liquido'] / $f['devengado'];
                $ratioCoste = $f['coste_total'] / $f['devengado'];
                if ($medianaLiq > 0 && abs($ratioLiq - $medianaLiq) > $medianaLiq * 0.5) {
                    $motivos[] = 'Proporción Líquido/Devengado atípica frente al resto del PDF';
                }
                if ($medianaCoste > 0 && abs($ratioCoste - $medianaCoste) > $medianaCoste * 0.5) {
                    $motivos[] = 'Proporción Coste Total/Devengado atípica frente al resto del PDF';
                }
            }

            if (!empty($motivos)) {
                $anomalos[] = [
                    'nif'         => $f['nif'],
                    'nombre'      => $f['nombre'],
                    'devengado'   => $f['devengado'],
                    'liquido'     => $f['liquido'],
                    'coste_total' => $f['coste_total'],
                    'motivos'     => $motivos,
                ];
            }
        }

        return $anomalos;
    }

    private function mediana(array $valores): float
    {
        if (empty($valores)) return 0.0;
        sort($valores);
        $n = count($valores);
        $mitad = intdiv($n, 2);
        return $n % 2 === 0 ? ($valores[$mitad - 1] + $valores[$mitad]) / 2 : $valores[$mitad];
    }

    private function pdfATexto(string $path): ?string
    {
        $process = new Process(['pdftotext', '-layout', $path, '-']);
        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        return $process->getOutput();
    }

    // Devuelve un array de ['nif'=>, 'nombre'=>, 'mes'=>'YYYY-MM-01', 'devengado'=>, 'liquido'=>,
    // 'coste_total'=>] por cada fila de trabajador reconocida.
    private function parsearFilas(string $texto): array
    {
        $lineas = preg_split('/\r\n|\r|\n/', $texto);

        // Localiza la fila de cabecera ("NIF ... Devengado ... Líquido ... Coste Total ... Mes")
        // para anclar las columnas por posición de caracter -- las columnas intermedias (P.Product,
        // H.Extras, Cotizable, etc.) vienen vacías en muchas filas, así que un split por espacios
        // desplazaría los valores; la posición del caracter no se mueve nunca DENTRO de una misma
        // cabecera. Pero el PDF trae una cabecera repetida por página/sección, y su ancho de
        // columna varía (p.ej. "Nombre Trabajador" se ensancha según el nombre más largo de esa
        // página) -- usar solo la primera cabecera del documento entero para todas las filas
        // desalinea las columnas de las páginas siguientes (bug real: Líquido/Coste Total salían
        // truncados a partir de la 2ª página). Por eso se recalculan los offsets cada vez que
        // aparece una cabecera nueva, y se usan los de la ÚLTIMA vista para las filas siguientes.
        $offsets = null;
        $filas = [];

        foreach ($lineas as $linea) {
            if (str_starts_with(trim($linea), 'NIF') && str_contains($linea, 'Devengado')) {
                // Si esta cabecera concreta viniera con un formato inesperado (offsetsCabecera
                // devuelve null), se mantienen los offsets de la última cabecera válida en vez
                // de perderlos -- más seguro que dejar de parsear el resto del documento.
                $offsets = $this->offsetsCabecera($linea) ?? $offsets;
                continue;
            }

            if ($offsets === null) {
                continue; // todavía no hemos visto ninguna cabecera
            }

            if (!preg_match('/^([XYZ]?\d{7,8}[A-Z])\s+(.+?)\s{2,}/u', $linea, $m)) {
                continue; // no es el inicio de una fila de trabajador (continuacion de nombre, Dpto:, Centro:, etc.)
            }

            $nif    = $m[1];
            $nombre = trim($m[2]);

            // Devengado y Líquido: no se cortan por substring en un offset fijo -- pdftotext
            // -layout no da una rejilla de caracteres realmente fija (la posición real de cada
            // número se desplaza unos caracteres según lo anchas que sean las columnas
            // anteriores de ESA fila en concreto, no solo según la página), así que un corte
            // fijo a veces caía a mitad de número y se comía sus primeros dígitos (bug real:
            // "2.067,70" salía importado como "67,70"). En su lugar se localizan TODOS los
            // números de la fila con su posición real, y se elige para cada columna el más
            // cercano a la posición que marca la cabecera -- eso nunca trunca un número, como
            // mucho elige el equivocado si dos números están igual de cerca (no visto en la
            // práctica: Devengado y Líquido siempre vienen pegados el uno al otro).
            $tokens = $this->tokensNumericos($linea);
            $devToken = $this->tokenMasCercano($tokens, $offsets['devengado']);
            $devengado = $devToken ? $this->normalizarImporte($devToken['val']) : null;
            $liqToken = $this->tokenMasCercano($tokens, $offsets['liquido'], $devToken['end'] ?? 0);
            $liquido = $liqToken ? $this->normalizarImporte($liqToken['val']) : null;

            // Mes/año y Coste Total de la propia fila, anclados al final de la línea ("...
            // 1.909,65   N 03/26") en vez de a la posición de caracter de la cabecera. "Coste
            // Total" es la última etiqueta de la cabecera antes de "Tipo Mes", pero para cuando
            // se llega tan lejos a la derecha el offset de la cabecera ya no coincide con la
            // columna real de la fila (bug real: la fuente en negrita de la cabecera tiene un
            // ancho de caracter distinto al de los datos, y el desajuste se acumula columna a
            // columna hasta desalinearse del todo en las últimas -- Devengado/Líquido están lo
            // bastante cerca del principio como para que ese desajuste siga siendo pequeño y no
            // llegue a comerse el hueco en blanco de alrededor, pero Coste Total ya no). Anclar
            // por regex al final de la línea es inmune a ese desajuste.
            if (!preg_match('/([\d.,]+)\s+[A-Za-z]{1,3}\s+(\d{2})\/(\d{2})\s*$/', trim($linea), $mm)) {
                continue;
            }
            $costeTotal = $this->normalizarImporte($mm[1]);
            $mes = sprintf('20%s-%s-01', $mm[3], $mm[2]);

            if ($devengado === null && $liquido === null && $costeTotal === null) {
                continue; // fila sin ningun importe relevante (ej. alta sin actividad ese mes)
            }

            $filas[] = [
                'nif'         => $nif,
                'nombre'      => $nombre,
                'mes'         => $mes,
                'devengado'   => $devengado ?? 0,
                'liquido'     => $liquido ?? 0,
                'coste_total' => $costeTotal ?? 0,
            ];
        }

        return $filas;
    }

    // Offsets de columna (en caracteres) de una fila de cabecera concreta. Puede devolver null
    // si la cabecera no trae alguna de las columnas esperadas (formato inesperado) -- en ese
    // caso el llamador debe seguir usando la última cabecera válida, no una vacía.
    private function offsetsCabecera(string $cabecera): ?array
    {
        $posDevengado  = mb_strpos($cabecera, 'Devengado');
        $posLiquido    = mb_strpos($cabecera, 'quido'); // "Líquido"/"Liquido" segun la codificacion
        $posPDif       = mb_strpos($cabecera, 'P.Dif.');
        $posCosteTotal = mb_strpos($cabecera, 'Coste Total');
        $posTipo       = mb_strpos($cabecera, 'Tipo');

        if ($posDevengado === false || $posLiquido === false || $posCosteTotal === false || $posTipo === false) {
            return null;
        }

        return [
            'devengado'  => $posDevengado,
            'liquido'    => max(0, $posLiquido - 1), // "quido" empieza 1 caracter despues de "L/Líquido"
            'pDif'       => $posPDif,
            'costeTotal' => $posCosteTotal,
            'tipo'       => $posTipo,
        ];
    }

    // Todos los números con formato "1.234,56" (o "56", sin miles) de una línea, con su
    // posición real en caracteres -- para poder elegir el más cercano a una columna en vez de
    // cortar por substring a un offset fijo.
    private function tokensNumericos(string $linea): array
    {
        preg_match_all('/-?\d{1,3}(?:\.\d{3})*,\d{2}/u', $linea, $m, PREG_OFFSET_CAPTURE);
        $tokens = [];
        foreach ($m[0] as [$val, $byteOffset]) {
            $charOffset = mb_strlen(substr($linea, 0, $byteOffset)); // PREG_OFFSET_CAPTURE da bytes, no caracteres
            $tokens[] = ['val' => $val, 'pos' => $charOffset, 'end' => $charOffset + mb_strlen($val)];
        }
        return $tokens;
    }

    // El token cuyo inicio está más cerca de $posEsperada, descartando los que empiecen antes
    // de $minPos (para no dejar que Líquido "vuelva hacia atrás" y reelija el mismo token que
    // ya se usó para Devengado).
    private function tokenMasCercano(array $tokens, int $posEsperada, int $minPos = 0): ?array
    {
        $mejor = null;
        $mejorDistancia = null;
        foreach ($tokens as $token) {
            if ($token['pos'] < $minPos) continue;
            $distancia = abs($token['pos'] - $posEsperada);
            if ($mejorDistancia === null || $distancia < $mejorDistancia) {
                $mejorDistancia = $distancia;
                $mejor = $token;
            }
        }
        return $mejor;
    }

    // "1.909,65" (formato es) -> 1909.65
    private function normalizarImporte(string $numero): ?float
    {
        $numero = str_replace('.', '', $numero);
        $numero = str_replace(',', '.', $numero);
        return is_numeric($numero) ? (float) $numero : null;
    }
}
