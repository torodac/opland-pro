<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// Da de alta a un usuario en el proyecto `estudio` y le crea las asignaturas de su curso.
// Idempotente: se puede ejecutar varias veces sin duplicar asignaturas.
//
//   php artisan estudio:seed alumno@ejemplo.com --curso="2º ESO"
class EstudioSeedCommand extends Command
{
    protected $signature = 'estudio:seed {email : Email de la cuenta de admin_users} {--curso=2º ESO : Curso al que pertenecen las asignaturas}';

    protected $description = 'Da acceso al proyecto estudio y crea las asignaturas del curso indicado';

    // Asignaturas por curso. Añadir aquí los demás cursos cuando haga falta.
    private const ASIGNATURAS = [
        '2º ESO' => [
            'Geografía',
            'Lengua',
            'Matemáticas',
            'Física y Química',
            'Música',
            'Valenciano',
            'Inglés',
        ],
    ];

    public function handle(): int
    {
        $curso = (string) $this->option('curso');
        $asignaturas = self::ASIGNATURAS[$curso] ?? null;
        if (!$asignaturas) {
            $this->error("No hay asignaturas definidas para el curso «{$curso}». Añádelas en EstudioSeedCommand::ASIGNATURAS.");
            return self::FAILURE;
        }

        $user = DB::table('admin_users')->whereRaw('LOWER(email) = LOWER(?)', [$this->argument('email')])->first();
        if (!$user) {
            $this->error('No existe ninguna cuenta de admin_users con ese email.');
            return self::FAILURE;
        }

        $ahora = now();

        // Acceso al proyecto (lo comprueba User::canAccessProject)
        $tieneRol = DB::table('admin_user_roles')
            ->where('user_id', $user->id)->where('role', 'estudio_usuarios')->exists();
        if (!$tieneRol) {
            DB::table('admin_user_roles')->insert([
                'user_id'    => $user->id,
                'role'       => 'estudio_usuarios',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
            $this->line('Acceso a estudio concedido.');
        }

        $creadas = 0;
        foreach ($asignaturas as $nombre) {
            $existe = DB::table('estudio_asignaturas')
                ->where('admin_user_id', $user->id)->where('nombre', $nombre)->where('curso', $curso)
                ->where('deleted', 0)->exists();
            if ($existe) {
                continue;
            }
            DB::table('estudio_asignaturas')->insert([
                'nombre'        => $nombre,
                'curso'         => $curso,
                'admin_user_id' => $user->id,
                'createuser'    => $user->id,
                'updateuser'    => $user->id,
                'created_at'    => $ahora,
                'updated_at'    => $ahora,
            ]);
            $creadas++;
        }

        $this->info("Listo: {$creadas} asignaturas nuevas de {$curso} para {$this->argument('email')}.");
        return self::SUCCESS;
    }
}
