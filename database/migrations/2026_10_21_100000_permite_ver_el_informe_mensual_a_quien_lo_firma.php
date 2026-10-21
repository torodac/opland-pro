<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Da la pantalla "informe_imputaciones" a los cuatro roles que solo tenian su listado.
//
// Esos roles (Limpiadora, Operario mantenimiento, Proveedor limpieza y SSCC: 18 personas)
// pueden abrir /vm/informe-imputaciones_list y ven ahi el boton de firmar su propio informe,
// pero no la pantalla del informe en si. Con las acciones del circuito colgando de esta
// ultima, 'menu.access' las rechazaba con un 403 y nadie podia firmar -- ver el comentario de
// routes/web.php. Las rutas ya se movieron bajo el listado, asi que esto no es lo que
// desbloquea la firma: es para que quien firma pueda ABRIR el informe que esta firmando,
// que hasta hoy no podia.
return new class extends Migration
{
    private const ROLES = [1, 4, 6, 8];   // Limpiadora, Operario mantenimiento, Proveedor limpieza, SSCC
    private const PANTALLA = 'informe_imputaciones';

    public function up(): void
    {
        foreach (self::ROLES as $id) {
            $this->cambiar($id, fn(array $ver) => in_array(self::PANTALLA, $ver, true)
                ? $ver
                : [...$ver, self::PANTALLA]);
        }
    }

    public function down(): void
    {
        foreach (self::ROLES as $id) {
            $this->cambiar($id, fn(array $ver) => array_values(
                array_filter($ver, fn($p) => $p !== self::PANTALLA)
            ));
        }
    }

    private function cambiar(int $id, callable $fn): void
    {
        $fila = DB::table('vm_roles')->where('id', $id)->first(['id', 'nombre', 'ver']);
        if (!$fila) {
            echo "  AVISO - el rol {$id} no existe, se omite." . PHP_EOL;
            return;
        }

        // Una lista vacia significa "sin restriccion": anadir una pantalla la convertiria en
        // una lista de una, que es justo lo contrario de lo que hay hoy.
        $ver = json_decode($fila->ver ?? '[]', true) ?: [];
        if ($ver === []) {
            echo "  AVISO - el rol {$fila->nombre} no tiene restriccion, se deja intacto." . PHP_EOL;
            return;
        }

        $nuevo = $fn($ver);
        if ($nuevo === $ver) {
            echo "  {$fila->nombre}: ya estaba como debe, sin cambios." . PHP_EOL;
            return;
        }

        DB::table('vm_roles')->where('id', $id)->update(['ver' => json_encode($nuevo)]);
        echo "  {$fila->nombre}: " . count($ver) . ' -> ' . count($nuevo) . ' pantallas.' . PHP_EOL;
    }
};
