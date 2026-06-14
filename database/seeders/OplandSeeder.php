<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectTable;
use App\Models\TableField;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OplandSeeder extends Seeder
{
    public function run(): void
    {
        // ── Usuario admin ──────────────────────────────────────────────
        $user = DB::table('users')->insertGetId([
            'name'              => 'Admin',
            'email'             => 'admin@opland.test',
            'password'          => Hash::make('password'),
            'email_verified_at' => now(),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // ── Proyecto: Gym ──────────────────────────────────────────────
        $gym = Project::create([
            'name'        => 'Gym Pro',
            'slug'        => 'gym',
            'description' => 'Gestión de gimnasio',
        ]);

        // Tabla: socios — createDynamicTable() crea la tabla Y los campos de sistema
        $socios = ProjectTable::create([
            'project_id' => $gym->id,
            'name'       => 'socios',
            'label'      => 'Socios',
            'icon'       => 'fas fa-users',
            'order'      => 1,
        ]);
        $socios->createDynamicTable();

        // Campos adicionales de socios (nombre ya creado por createDynamicTable)
        $camposSocios = [
            ['name' => 'email',    'label' => 'Email',       'type' => 'email',    'order' => 2, 'in_list' => true],
            ['name' => 'telefono', 'label' => 'Teléfono',    'type' => 'telefono', 'order' => 3, 'in_list' => true],
            ['name' => 'alta',     'label' => 'Fecha alta',  'type' => 'fecha',    'order' => 4, 'in_list' => true],
            ['name' => 'tipo',     'label' => 'Tipo cuota',  'type' => 'select',   'order' => 5, 'in_list' => true, 'extras' => 'opt:Mensual,Trimestral,Anual'],
            ['name' => 'activo',   'label' => 'Activo',      'type' => 'tinyint',  'order' => 6, 'in_list' => true],
            ['name' => 'notas',    'label' => 'Notas',       'type' => 'text',     'order' => 7, 'in_list' => false],
        ];

        foreach ($camposSocios as $c) {
            $field = $socios->fields()->create($c);
            $field->addColumnToTable();
        }

        // Tabla: clases
        $clases = ProjectTable::create([
            'project_id'   => $gym->id,
            'name'         => 'clases',
            'label'        => 'Clases',
            'icon'         => 'fas fa-calendar-alt',
            'order'        => 2,
            'has_calendar' => true,
        ]);
        $clases->createDynamicTable();

        $camposClases = [
            ['name' => 'instructor', 'label' => 'Instructor', 'type' => 'string', 'order' => 2, 'in_list' => true],
            ['name' => 'fecha',      'label' => 'Fecha',      'type' => 'fecha',  'order' => 3, 'in_list' => true],
            ['name' => 'hora',       'label' => 'Hora',       'type' => 'time',   'order' => 4, 'in_list' => true],
            ['name' => 'plazas',     'label' => 'Plazas',     'type' => 'int',    'order' => 5, 'in_list' => true],
            ['name' => 'sala',       'label' => 'Sala',       'type' => 'select', 'order' => 6, 'in_list' => true, 'extras' => 'opt:Sala A,Sala B,Piscina,Spinning'],
        ];

        foreach ($camposClases as $c) {
            $field = $clases->fields()->create($c);
            $field->addColumnToTable();
        }

        // Tabla: inscripciones
        $inscripciones = ProjectTable::create([
            'project_id' => $gym->id,
            'name'       => 'inscripciones',
            'label'      => 'Inscripciones',
            'icon'       => 'fas fa-clipboard-list',
            'order'      => 3,
        ]);
        $inscripciones->createDynamicTable();

        $camposInscripciones = [
            ['name' => 'id_socios', 'label' => 'Socio',  'type' => 'id',     'order' => 2, 'in_list' => true, 'in_form' => true, 'required' => true,  'extras' => 'ref:socios'],
            ['name' => 'id_clases', 'label' => 'Clase',  'type' => 'id',     'order' => 3, 'in_list' => true, 'in_form' => true, 'required' => true,  'extras' => 'ref:clases'],
            ['name' => 'fecha',     'label' => 'Fecha',  'type' => 'fecha',  'order' => 4, 'in_list' => true, 'in_form' => true],
            ['name' => 'estado',    'label' => 'Estado', 'type' => 'select', 'order' => 5, 'in_list' => true, 'in_form' => true, 'extras' => 'opt:Confirmada,Pendiente,Cancelada'],
        ];

        foreach ($camposInscripciones as $c) {
            $field = $inscripciones->fields()->create($c);
            $field->addColumnToTable();
        }

        // ── Menú ──────────────────────────────────────────────────────
        MenuItem::create(['project_id' => $gym->id, 'label' => 'Socios',         'icon' => 'fas fa-users',          'project_table_id' => $socios->id,        'order' => 1]);
        MenuItem::create(['project_id' => $gym->id, 'label' => 'Clases',         'icon' => 'fas fa-calendar-alt',   'project_table_id' => $clases->id,        'order' => 2]);
        MenuItem::create(['project_id' => $gym->id, 'label' => 'Inscripciones',  'icon' => 'fas fa-clipboard-list', 'project_table_id' => $inscripciones->id,  'order' => 3]);

        // ── Datos de prueba ────────────────────────────────────────────
        $faker = \Faker\Factory::create('es_ES');
        $meta  = ['hidden' => 0, 'deleted' => 0, 'createuser' => $user, 'updateuser' => $user, 'createdat' => now(), 'updatedat' => now()];

        $tipos       = ['Mensual', 'Trimestral', 'Anual'];
        $instructores = ['Elena Ruiz', 'Carlos Vega', 'Sofía Mora', 'Marcos Gil', 'Lucía Pons'];
        $tiposClase  = ['Yoga', 'Spinning', 'Aquagym', 'Pilates', 'Zumba', 'CrossFit', 'GAP', 'Boxeo', 'Stretching', 'Body Pump'];
        $salas       = ['Sala A', 'Sala B', 'Piscina', 'Spinning'];
        $horas       = ['08:00', '09:00', '09:30', '10:00', '10:30', '11:00', '12:00', '17:00', '18:00', '19:00', '20:00'];

        // 80 socios
        $socios = [];
        for ($i = 0; $i < 80; $i++) {
            $nombre = $faker->firstName() . ' ' . $faker->lastName();
            $socios[] = array_merge([
                'nombre'   => $nombre,
                'email'    => $faker->unique()->safeEmail(),
                'telefono' => $faker->numerify('6########'),
                'alta'     => $faker->dateTimeBetween('-3 years', 'now')->format('Y-m-d'),
                'tipo'     => $tipos[array_rand($tipos)],
                'activo'   => $faker->boolean(80) ? 1 : 0,
                'notas'    => $faker->optional(0.3)->sentence(),
            ], $meta);
        }
        DB::table('gym_socios')->insert($socios);

        // 400 clases distribuidas en los últimos 6 meses y próximos 3
        $clases = [];
        for ($i = 0; $i < 400; $i++) {
            $clases[] = array_merge([
                'nombre'     => $tiposClase[array_rand($tiposClase)],
                'instructor' => $instructores[array_rand($instructores)],
                'fecha'      => $faker->dateTimeBetween('-6 months', '+3 months')->format('Y-m-d'),
                'hora'       => $horas[array_rand($horas)],
                'plazas'     => $faker->randomElement([8, 10, 12, 15, 20, 25]),
                'sala'       => $salas[array_rand($salas)],
            ], $meta);
        }
        DB::table('gym_clases')->insert($clases);

        // 300 inscripciones
        $socioIds = DB::table('gym_socios')->pluck('id')->toArray();
        $claseIds = DB::table('gym_clases')->pluck('id')->toArray();
        $estados  = ['Confirmada', 'Pendiente', 'Cancelada'];
        $pesos    = [70, 20, 10]; // % de probabilidad por estado

        $inscs = [];
        $vistos = [];
        for ($i = 0; $i < 300; $i++) {
            // Evitar duplicados socio+clase
            do {
                $idSocio = $socioIds[array_rand($socioIds)];
                $idClase = $claseIds[array_rand($claseIds)];
                $clave   = $idSocio . '-' . $idClase;
            } while (isset($vistos[$clave]));
            $vistos[$clave] = true;

            $rand = rand(1, 100);
            $acum = 0;
            $estado = 'Confirmada';
            foreach ($estados as $k => $e) {
                $acum += $pesos[$k];
                if ($rand <= $acum) { $estado = $e; break; }
            }

            $inscs[] = array_merge([
                'nombre'   => null,
                'id_socios' => $idSocio,
                'id_clases' => $idClase,
                'fecha'    => $faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
                'estado'   => $estado,
            ], $meta);
        }
        DB::table('gym_inscripciones')->insert($inscs);
    }
}
