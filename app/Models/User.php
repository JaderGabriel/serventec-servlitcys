<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'birth_date', 'cpf', 'password', 'is_admin', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
            'birth_date' => 'date',
        ];
    }

    /**
     * Sessões persistidas na tabela `sessions` (driver database).
     */
    public function databaseSessions(): HasMany
    {
        return $this->hasMany(DatabaseSession::class, 'user_id');
    }

    /**
     * Administradores com conta ativa (podem aceder à aplicação como admin).
     */
    public static function activeAdminCount(): int
    {
        return (int) static::query()
            ->where('is_admin', true)
            ->where('is_active', true)
            ->count();
    }

    /**
     * Desativar ou retirar admin a este utilizador deixaria o sistema sem admin ativo.
     */
    public function soleActiveAdminWouldBeRemoved(bool $newIsAdmin, bool $newIsActive): bool
    {
        if (! $this->is_admin || ! $this->is_active) {
            return false;
        }

        if ($newIsAdmin && $newIsActive) {
            return false;
        }

        return static::activeAdminCount() === 1;
    }

    /**
     * Data de nascimento e CPF são obrigatórios após o primeiro login (não no cadastro pelo admin).
     */
    public function needsProfileCompletion(): bool
    {
        if ($this->birth_date === null) {
            return true;
        }

        $cpf = $this->cpf;

        return $cpf === null || $cpf === '';
    }
}
