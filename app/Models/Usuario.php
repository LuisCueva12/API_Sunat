<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UsuarioFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['empresa_id', 'name', 'email', 'password', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class Usuario extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UsuarioFactory> */
    use HasFactory, HasRoles, HasUuids, Notifiable;

    protected $table = 'usuarios';

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->empresa_id === null && $this->hasRole('super_admin'),
            'empresa' => $this->empresa_id !== null,
            default => false,
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
