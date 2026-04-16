<?php

namespace App\Models;

use Database\Factories\CityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'uf',
    'ibge_municipio',
    'country',
    'db_driver',
    'ieducar_schema',
    'db_host',
    'db_port',
    'db_database',
    'db_username',
    'db_password',
    'is_active',
])]
class City extends Model
{
    public const DRIVER_MYSQL = 'mysql';

    public const DRIVER_PGSQL = 'pgsql';

    /** @use HasFactory<CityFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'db_port' => 'integer',
            'db_password' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Cidades ativas (disponíveis para painéis e consultas).
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Motor SQL usado na ligação dinâmica (mysql ou pgsql).
     */
    public function dataDriver(): string
    {
        $d = (string) ($this->db_driver ?? self::DRIVER_MYSQL);

        return in_array($d, [self::DRIVER_MYSQL, self::DRIVER_PGSQL], true)
            ? $d
            : self::DRIVER_MYSQL;
    }

    /**
     * Motor efectivo para iEducar: corrige cadastro em que a porta é 5432 mas o campo ainda diz "mysql".
     */
    public function effectiveIeducarDriver(): string
    {
        $d = $this->dataDriver();
        if ($d === self::DRIVER_PGSQL) {
            return self::DRIVER_PGSQL;
        }

        if ((int) ($this->db_port ?? 0) === 5432) {
            return self::DRIVER_PGSQL;
        }

        return $d;
    }

    /**
     * Cidades com credenciais de base de dados preenchidas (mínimo para ligar).
     */
    public function scopeWithDataSetup(Builder $query): void
    {
        $query->whereNotNull('db_host')
            ->whereNotNull('db_database')
            ->whereNotNull('db_username');
    }

    /**
     * Cidades elegíveis para analytics: ativas + setup de dados.
     * Padrão único para dropdowns e consultas do dashboard iEducar.
     */
    public function scopeForAnalytics(Builder $query): void
    {
        $query->active()->withDataSetup();
    }

    /**
     * Dados mínimos para consultar a base desta cidade no painel.
     */
    public function hasDataSetup(): bool
    {
        return filled($this->db_host)
            && filled($this->db_database)
            && filled($this->db_username);
    }
}
