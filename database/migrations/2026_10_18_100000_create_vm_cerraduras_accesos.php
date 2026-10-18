<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Autorizaciones de cada cerradura Nuki: quién puede abrir, con qué código y desde cuándo.
//
// Es un espejo local de GET /smartlock/{id}/auth, refrescado por vm:sync-nuki-accesos después de
// vm:sync-nuki. Solo lectura: nada de aquí se envía a Nuki.
//
// El código de teclado se guarda cifrado (Crypt, con APP_KEY): da entrada física a una vivienda.
//
// Además registra la entrada de menú "Nuki" (tabla virtual nuki_default) para que menu.access
// aplique el permiso de lectura a la pantalla, igual que dev_fianzas_default.
return new class extends Migration
{
    private const PROJECT_ID = 4; // VM (slug vm)

    public function up(): void
    {
        if (!Schema::hasTable('vm_cerraduras_accesos')) {
            Schema::create('vm_cerraduras_accesos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('nuki_smartlock_id');
                $table->unsignedBigInteger('nuki_auth_id');
                $table->string('nombre', 255)->nullable();
                // codigo | app | teclado | otro (segun el tipo de autorizacion de Nuki)
                $table->string('tipo', 20);
                $table->text('codigo')->nullable();   // cifrado
                $table->boolean('habilitado')->default(true);
                $table->timestamp('fecha_desde')->nullable();
                $table->timestamp('fecha_hasta')->nullable();
                $table->timestamp('creado_nuki_at')->nullable();
                $table->timestamp('ultimo_uso_at')->nullable();
                $table->timestamp('leido_at')->nullable();
                $table->timestamps();

                $table->unique(['nuki_smartlock_id', 'nuki_auth_id']);
            });
        }

        $tablaExiste = DB::table('admin_project_tables')
            ->where('project_id', self::PROJECT_ID)->where('name', 'nuki_default')->exists();

        if (!$tablaExiste) {
            $tablaId = DB::table('admin_project_tables')->insertGetId([
                'project_id'  => self::PROJECT_ID,
                'name'        => 'nuki_default',
                'label'       => 'Nuki',
                'icon'        => 'fa-solid fa-key',
                'order'       => 48,
                'is_virtual'  => true,
                'admin_only'  => false,
                'active'      => true,
                'createuser'  => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            DB::table('admin_menu_items')->insert([
                'project_id'       => self::PROJECT_ID,
                'label'            => 'Nuki',
                'icon'             => 'fa-solid fa-key',
                'project_table_id' => $tablaId,
                'url'              => '/vm/nuki',
                'order'            => 48,
                'createuser'       => 1,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('admin_menu_items')->where('project_id', self::PROJECT_ID)->where('url', '/vm/nuki')->delete();
        DB::table('admin_project_tables')->where('project_id', self::PROJECT_ID)->where('name', 'nuki_default')->delete();
        Schema::dropIfExists('vm_cerraduras_accesos');
    }
};
