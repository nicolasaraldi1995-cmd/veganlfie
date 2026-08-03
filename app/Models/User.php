<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'negocio', 'tipo_cliente', 'email', 'celular', 'direccion', 'ciudad', 'provincia', 'password', 'role', 'recibe_frio_congelado', 'aprobado'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'recibe_frio_congelado' => 'boolean',
            'aprobado' => 'boolean',
        ];
    }

    /**
     * Único lugar que decide quién ve los precios mayoristas. Un cliente recién
     * registrado no los ve hasta que la distribuidora lo aprueba: sin esto,
     * cualquiera (incluida la competencia) se creaba una cuenta y se llevaba
     * toda la lista de precios.
     */
    public function puedeVerPrecios(): bool
    {
        return $this->isAdmin() || $this->isOperador() || $this->aprobado;
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isOperador(): bool
    {
        return $this->role === 'operador';
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return in_array($this->role, ['admin', 'operador']);
    }
}
