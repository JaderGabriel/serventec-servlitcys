<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Policies\PlatformFeaturePolicy;
use App\Support\Contact\ContactChannels;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'name',
    'username',
    'email',
    'phone',
    'whatsapp',
    'profile_photo_path',
    'birth_date',
    'cpf',
    'password',
    'role',
    'is_active',
    'privacy_policy_version_accepted',
    'privacy_policy_accepted_at',
    'cookies_consent_version',
    'cookies_consent_accepted_at',
])]
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
            'privacy_policy_accepted_at' => 'datetime',
            'cookies_consent_accepted_at' => 'datetime',
        ];
    }

    public function legalConsentLogs(): HasMany
    {
        return $this->hasMany(LegalConsentLog::class);
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
        return app(PlatformFeaturePolicy::class)->importOrConfigure($this);
    }

    /** Documentação interna (leitor Markdown). */
    public function canViewDocumentation(): bool
    {
        return app(PlatformFeaturePolicy::class)->viewDocumentation($this);
    }

    /** Fila de exportações e tarefas enfileiradas pelo próprio utilizador. */
    public function canViewSyncQueue(): bool
    {
        return app(PlatformFeaturePolicy::class)->viewSyncQueue($this);
    }

    /** Exportação detalhada NEE (CSV/Excel) na aba Inclusão. */
    public function canExportInclusionNee(): bool
    {
        return app(PlatformFeaturePolicy::class)->exportInclusionNee($this);
    }

    public function canViewAdminDashboard(): bool
    {
        return app(PlatformFeaturePolicy::class)->viewAdminDashboard($this);
    }

    /** Mapa Horizonte (oportunidade territorial): administrador e utilizador da plataforma. */
    public function canViewHorizonte(): bool
    {
        return app(PlatformFeaturePolicy::class)->viewHorizonte($this);
    }

    /** Clio — coletas e relatórios Educacenso 1ª etapa. */
    public function canViewClio(): bool
    {
        return app(PlatformFeaturePolicy::class)->viewClio($this);
    }

    /** Relatório PDF completo (aba Serventec): administrador e usuário da plataforma. */
    public function canExportAnalyticsPdf(): bool
    {
        return app(PlatformFeaturePolicy::class)->exportAnalyticsPdf($this);
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
        if ($this->relationLoaded('cities')) {
            return $this->cities->pluck('id')->map(static fn ($id) => (int) $id)->all();
        }

        $ttl = (int) config('performance.user_city_ids_cache', 3600);
        if ($ttl <= 0) {
            return $this->cities()->pluck('cities.id')->map(static fn ($id) => (int) $id)->all();
        }

        return Cache::remember(
            self::cityIdsCacheKey((int) $this->getKey()),
            $ttl,
            fn () => $this->cities()->pluck('cities.id')->map(static fn ($id) => (int) $id)->all(),
        );
    }

    public static function cityIdsCacheKey(int $userId): string
    {
        return "user:{$userId}:city_ids";
    }

    public static function forgetCityIdsCache(?int $userId): void
    {
        if ($userId !== null && $userId > 0) {
            Cache::forget(self::cityIdsCacheKey($userId));
        }
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

    /**
     * Rótulo de município(s) para o rodapé (perfis com território restrito).
     */
    public function footerMunicipalityLabel(): ?string
    {
        if ($this->isAdmin() || $this->isUsuário()) {
            return null;
        }

        $names = $this->cities()
            ->where('cities.is_active', true)
            ->orderBy('cities.name')
            ->get(['cities.name', 'cities.uf'])
            ->map(static function (City $city): string {
                $uf = trim((string) $city->uf);

                return $uf !== '' ? "{$city->name} ({$uf})" : $city->name;
            })
            ->all();

        if ($names === []) {
            return $this->isMunicipal() ? __('Nenhum município vinculado') : null;
        }

        if (count($names) <= 3) {
            return implode(' · ', $names);
        }

        return implode(' · ', array_slice($names, 0, 2))
            .' · '
            .__('+:n municípios', ['n' => count($names) - 2]);
    }

    /**
     * @return array<string, mixed>
     */
    public function contactChannels(): array
    {
        return ContactChannels::fromUser($this);
    }
}
