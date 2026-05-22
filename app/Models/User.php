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
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'username', 'email', 'profile_photo_path', 'birth_date', 'cpf', 'password', 'role', 'is_active'])]
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

    public function isUsuário(): bool
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

    public function canViewAdminDashboard(): bool
    {
        return $this->is_active && $this->isAdmin();
    }

    /** Relatório PDF completo (aba Serventec): administrador e usuário da plataforma. */
    public function canExportAnalyticsPdf(): bool
    {
        return $this->is_active && ($this->isAdmin() || $this->isUsuário());
    }

    /**
     * Rota de destino após login ou clique no logótipo.
     */
    public function homeRouteName(): string
    {
        return $this->canViewAdminDashboard() ? 'dashboard' : 'dashboard.analytics';
    }

    /**
     * Parâmetros da rota inicial (ex.: municipal com um único município → city_id).
     *
     * @return array<string, int|string>
     */
    public function homeRouteParameters(): array
    {
        if (! $this->isMunicipal()) {
            return [];
        }

        $ids = $this->cityIds();

        return count($ids) === 1 ? ['city_id' => $ids[0]] : [];
    }

    public function homeUrl(bool $absolute = true): string
    {
        return route($this->homeRouteName(), $this->homeRouteParameters(), $absolute);
    }

    public function hasProfilePhoto(): bool
    {
        return filled($this->profile_photo_path);
    }

    public function profilePhotoUrl(): ?string
    {
        $path = $this->profile_photo_path;
        if (! is_string($path) || $path === '') {
            return null;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    public function profileInitials(): string
    {
        $parts = preg_split('/\s+/u', trim((string) $this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($parts === []) {
            return strtoupper(substr((string) $this->username, 0, 2));
        }

        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $initials !== '' ? $initials : '?';
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
        if ($this->isAdmin() || $this->isUsuário()) {
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
     * Usuárioes visíveis na gestão conforme o perfil de quem lista.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        if ($viewer->isAdmin()) {
            return $query;
        }

        if ($viewer->isUsuário()) {
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

    public function isLastAdminAccount(): bool
    {
        if ($this->role() !== UserRole::Admin) {
            return false;
        }

        return static::query()->where('role', UserRole::Admin->value)->count() === 1;
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
