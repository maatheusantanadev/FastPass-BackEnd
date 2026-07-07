<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    public const ROLE_PASSAGEIRO    = 'passageiro';
    public const ROLE_MOTORISTA     = 'motorista';
    public const ROLE_ADMINISTRADOR = 'administrador';

    protected $fillable = [
        'name',
        'email',
        'password',
        'cpf',
        'telefone',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class);
    }

    // Excursões em que este usuário é o motorista designado.
    public function excursoesComoMotorista(): HasMany
    {
        return $this->hasMany(Excursao::class, 'motorista_id');
    }

    public function isMotorista(): bool
    {
        return $this->role === self::ROLE_MOTORISTA;
    }

    public function isAdministrador(): bool
    {
        return $this->role === self::ROLE_ADMINISTRADOR;
    }

    public function isPassageiro(): bool
    {
        return $this->role === self::ROLE_PASSAGEIRO;
    }
}
