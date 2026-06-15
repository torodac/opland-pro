<?php

namespace App\Imports;

use App\Models\Project;
use App\Models\ProjectTable;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class UsuariosImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public int   $inserted = 0;
    public int   $skipped  = 0;
    public array $errors   = [];

    public function __construct(
        private Project $project,
        private ProjectTable $projectTable,
        private int $importerId = 0
    ) {}

    public function collection(Collection $rows): void
    {
        $fullTable    = $this->projectTable->getFullTableName();
        $allowedNames = $this->projectTable->fields->pluck('name')->toArray();
        $now          = now();

        foreach ($rows as $i => $row) {
            $email = trim($row['mail'] ?? $row['email'] ?? '');
            $name  = trim($row['nombre'] ?? '');

            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->errors[] = "Fila " . ($i + 2) . ": email inválido o vacío ($email)";
                $this->skipped++;
                continue;
            }

            // Si ya existe en admin_users, saltar
            if (User::where('email', $email)->exists()) {
                $this->errors[] = "Fila " . ($i + 2) . ": $email ya existe, omitido";
                $this->skipped++;
                continue;
            }

            DB::transaction(function () use ($row, $email, $name, $fullTable, $allowedNames, $now) {
                // 1. Crear usuario de acceso
                $user = User::create([
                    'name'     => $name ?: $email,
                    'email'    => $email,
                    'password' => Hash::make('bienvenido'),
                ]);

                // 2. Asignar rol en admin_user_roles
                $role = trim($row['role'] ?? $row['rol'] ?? '');
                if ($role) {
                    UserRole::create(['user_id' => $user->id, 'role' => $role]);
                }

                // 3. Datos para la tabla dinámica
                $rowData = ['admin_user_id' => $user->id];
                foreach ($allowedNames as $col) {
                    if (isset($row[$col]) && $row[$col] !== '') {
                        $rowData[$col] = $row[$col];
                    }
                }

                DB::table($fullTable)->insert(array_merge($rowData, [
                    'createdat'  => $now,
                    'updatedat'  => $now,
                    'createuser' => $this->importerId,
                    'updateuser' => $this->importerId,
                    'deleted'    => 0,
                    'hidden'     => 0,
                ]));
            });

            $this->inserted++;
        }
    }
}
