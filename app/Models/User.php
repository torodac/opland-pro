<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'admin_users';

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function roles()
    {
        return $this->hasMany(UserRole::class);
    }

    public function isAdmin(): bool
    {
        return $this->roles()->where('role', 'admin')->exists();
    }

    public function canAccessProject(Project $project): bool
    {
        if ($this->isAdmin()) return true;

        return $this->roles()
            ->whereIn('role', ["admin_{$project->slug}", "{$project->slug}_usuarios"])
            ->exists();
    }

    public function isProjectAdmin(Project $project): bool
    {
        return $this->isAdmin()
            || $this->roles()->where('role', "admin_{$project->slug}")->exists();
    }

    // Returns the project user ID in {slug}_usuarios, or null if not found
    public function projectUserId(Project $project): ?int
    {
        $row = \Illuminate\Support\Facades\DB::table($project->slug . '_usuarios')
            ->where('admin_user_id', $this->id)
            ->value('id');

        return $row ? (int) $row : null;
    }

    // Admins always have full access. For regular users, empty array = no restrictions.
    public function canViewTable(Project $project, string $tableName): bool
    {
        if ($this->isProjectAdmin($project)) return true;

        $role = $this->getProjectRolePublic($project);
        if (!$role) return false;

        $ver = json_decode($role->ver ?? '[]', true) ?? [];
        return empty($ver) || in_array($tableName, $ver);
    }

    public function canEditTable(Project $project, string $tableName): bool
    {
        if ($this->isProjectAdmin($project)) return true;

        $role = $this->getProjectRolePublic($project);
        if (!$role) return false;

        $editar = json_decode($role->editar ?? '[]', true) ?? [];
        return empty($editar) || in_array($tableName, $editar);
    }

    public function getProjectRolePublic(Project $project): ?object
    {
        $userId = $this->projectUserId($project);
        if (!$userId) return null;

        return \Illuminate\Support\Facades\DB::table($project->slug . '_roles')
            ->join($project->slug . '_usuarios', $project->slug . '_roles.id', '=', $project->slug . '_usuarios.id_rol')
            ->where($project->slug . '_usuarios.id', $userId)
            ->select($project->slug . '_roles.*')
            ->first();
    }
}
