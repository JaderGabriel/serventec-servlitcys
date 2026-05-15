<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'birth_date', 'cpf', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            $user->attributes['is_admin'] = $user->role() === UserRole::Admin;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'birth_date' => 'date',
            'role' => UserRole::class,
        ];
    }

    public function role(): UserRole
    {
        $role = $this->getAttribute('role');
        if ($role instanceof UserRole) {
            return $role;
        }

        return UserRole::User;
    }

    public function getIsAdminAttribute(): bool
    {
        return $this->role() === UserRole::Admin;
    }

    public function isAdmin(): bool
    {
        return $this->role() === UserRole::Admin;
    }

    public function isUtilizador(): bool
    {
        return $this->role() === UserRole::User;
    }

    public function isMunicipal(): bool
    {
        return $this->role() === UserRole::Municipal;
    }

    public function canAccessAdminArea(): bool
    {
        return $this->isAdmin();
    }

    public function canManageUsers(): bool
    {
        return $this->is_active && in_array($this->role(), [UserRole::Admin, UserRole::User, UserRole::Municipal], true);
    }

    public function canImportOrConfigure(): bool
    {
        return $this->is_active && $this->isAdmin();
    }

    /**
     * @return BelongsToMany<City, $this>
     */
    public function cities(): BelongsToMany
    {
        return $this->belongsToMany(City::class)->withTimestamps();
    }

    /**
     * @return list<int>
     */
    public function cityIds(): array
    {
        if (! $this->relationLoaded('cities')) {
            return $this->cities()->pluck('cities.id')->map(static fn ($id) => (int) $id)->all();
        }

        return $this->cities->pluck('id')->map(static fn ($id) => (int) $id)->all();
    }

    public function hasCityAccess(City $city): bool
    {
        if ($this->isAdmin() || $this->isUtilizador()) {
            return $city->is_active && $city->hasDataSetup();
        }

        if ($this->isMunicipal()) {
            return $city->is_active
                && $city->hasDataSetup()
                && $this->cities()->whereKey($city->id)->exists();
        }

        return false;
    }

    /**
     * Utilizadores visíveis na gestão conforme o perfil de quem lista.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        if ($viewer->isAdmin()) {
            return $query;
        }

        if ($viewer->isUtilizador()) {
            return $query->where('role', UserRole::User->value);
        }

        if ($viewer->isMunicipal()) {
            $cityIds = $viewer->cityIds();

            return $query
                ->where('role', UserRole::Municipal->value)
                ->where(function (Builder $q) use ($cityIds, $viewer) {
                    $q->where('users.id', $viewer->id);
                    if ($cityIds !== []) {
                        $q->orWhereHas('cities', static fn (Builder $c) => $c->whereIn('cities.id', $cityIds));
                    }
                });
        }

        return $query->whereRaw('1 = 0');
    }

    public function databaseSessions(): HasMany
    {
        return $this->hasMany(DatabaseSession::class, 'user_id');
    }

    public static function activeAdminCount(): int
    {
        return (int) static::query()
            ->where('role', UserRole::Admin->value)
            ->where('is_active', true)
            ->count();
    }

    public function soleActiveAdminWouldBeRemoved(UserRole $newRole, bool $newIsActive): bool
    {
        if ($this->role() !== UserRole::Admin || ! $this->is_active) {
            return false;
        }

        if ($newRole === UserRole::Admin && $newIsActive) {
            return false;
        }

        return static::activeAdminCount() === 1;
    }

    public function needsProfileCompletion(): bool
    {
        if ($this->birth_date === null) {
            return true;
        }

        $cpf = $this->cpf;

        return $cpf === null || $cpf === '';
    }
}
