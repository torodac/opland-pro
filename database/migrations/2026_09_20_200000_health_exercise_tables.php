<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::create('health_muscle_groups', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('icono', 10)->default('💪');
            $table->unsignedTinyInteger('orden')->default(0);
        });

        Schema::create('health_exercises', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('muscle_group_id');
            $table->string('nombre');
            $table->unsignedTinyInteger('orden')->default(0);
        });

        Schema::create('health_exercise_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_user_id');
            $table->date('log_date');
            $table->unsignedBigInteger('exercise_id');
            $table->timestamps();
            $table->unique(['admin_user_id', 'log_date', 'exercise_id']);
        });

        $groups = [
            [1, 'Pecho',   '🫁', 1],
            [2, 'Espalda', '🔙', 2],
            [3, 'Hombros', '🦴', 3],
            [4, 'Bíceps',  '💪', 4],
            [5, 'Tríceps', '💪', 5],
            [6, 'Piernas', '🦵', 6],
            [7, 'Glúteos', '🍑', 7],
            [8, 'Abdomen', '⬜', 8],
            [9, 'Cardio',  '🏃', 9],
        ];
        foreach ($groups as [$id, $nombre, $icono, $orden]) {
            DB::table('health_muscle_groups')->insert(compact('id','nombre','icono','orden'));
        }

        $exercises = [
            [1,'Press banca plano',1],[1,'Press banca inclinado',2],[1,'Aperturas con mancuernas',3],
            [1,'Press en máquina',4],[1,'Fondos en paralelas',5],[1,'Pullover',6],
            [2,'Dominadas',1],[2,'Remo con barra',2],[2,'Remo en polea baja',3],
            [2,'Jalón al pecho',4],[2,'Remo con mancuerna',5],[2,'Peso muerto',6],[2,'Hiperextensiones',7],
            [3,'Press militar',1],[3,'Elevaciones laterales',2],[3,'Elevaciones frontales',3],
            [3,'Face pull',4],[3,'Press Arnold',5],[3,'Pájaros',6],
            [4,'Curl con barra',1],[4,'Curl alterno con mancuernas',2],[4,'Curl martillo',3],
            [4,'Curl en polea',4],[4,'Curl concentrado',5],
            [5,'Press francés',1],[5,'Extensiones en polea alta',2],[5,'Fondos en banco',3],
            [5,'Patada de tríceps',4],[5,'Press cerrado en banca',5],
            [6,'Sentadilla',1],[6,'Prensa de piernas',2],[6,'Extensiones de cuádriceps',3],
            [6,'Curl femoral',4],[6,'Zancadas',5],[6,'Peso muerto rumano',6],
            [6,'Elevación de gemelos',7],[6,'Sentadilla hack',8],
            [7,'Hip thrust',1],[7,'Patada de glúteo en máquina',2],[7,'Puente de glúteo',3],
            [7,'Abducción de cadera',4],[7,'Sentadilla sumo',5],
            [8,'Crunch',1],[8,'Plancha',2],[8,'Elevación de piernas',3],
            [8,'Oblicuos en polea',4],[8,'Rueda abdominal',5],[8,'Mountain climbers',6],
            [9,'Cinta (correr)',1],[9,'Bicicleta estática',2],[9,'Elíptica',3],
            [9,'Remo ergómetro',4],[9,'HIIT',5],[9,'Salto a la comba',6],
        ];
        foreach ($exercises as [$gid, $nombre, $orden]) {
            DB::table('health_exercises')->insert(['muscle_group_id'=>$gid,'nombre'=>$nombre,'orden'=>$orden]);
        }
    }

    public function down(): void {
        Schema::dropIfExists('health_exercise_log');
        Schema::dropIfExists('health_exercises');
        Schema::dropIfExists('health_muscle_groups');
    }
};
