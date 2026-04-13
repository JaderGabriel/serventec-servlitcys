<?php

namespace App\Support\Dashboard;

use Illuminate\Http\Request;

/**
 * Estado dos filtros do painel (ano letivo obrigatório para carregar indicadores; escola, tipo/segmento, turno opcionais).
 */
final class IeducarFilterState
{
    public function __construct(
        /** null = ainda não escolhido; "all" = todos os anos; caso contrário ano (string numérica). */
        public ?string $ano_letivo,
        public ?string $escola_id,
        public ?string $curso_id,
        public ?string $turno_id,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $raw = $request->input('ano_letivo');
        $ano = null;
        if ($raw !== null && $raw !== '') {
            if ($raw === 'all') {
                $ano = 'all';
            } else {
                $i = (int) $raw;

                $ano = $i > 0 ? (string) $i : null;
            }
        }

        return new self(
            ano_letivo: $ano,
            escola_id: self::nullableString($request->input('escola_id')),
            curso_id: self::nullableString($request->input('curso_id')),
            turno_id: self::nullableString($request->input('turno_id')),
        );
    }

    /**
     * O utilizador escolheu «Todos os anos» ou um ano específico (não o placeholder vazio).
     */
    public function hasYearSelected(): bool
    {
        return $this->ano_letivo !== null && $this->ano_letivo !== '';
    }

    public function isAllSchoolYears(): bool
    {
        return $this->ano_letivo === 'all';
    }

    /**
     * Valor numérico para filtrar coluna ano da turma, ou null quando «todos os anos».
     */
    public function yearFilterValue(): ?int
    {
        if (! $this->hasYearSelected() || $this->isAllSchoolYears()) {
            return null;
        }

        return (int) $this->ano_letivo;
    }

    /**
     * @return array<string, string|int|null>
     */
    public function toQueryParams(): array
    {
        return array_filter([
            'ano_letivo' => $this->ano_letivo,
            'escola_id' => $this->escola_id,
            'curso_id' => $this->curso_id,
            'turno_id' => $this->turno_id,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @return array<string, string|int|null>
     */
    public function toQueryParamsWithCity(int $cityId): array
    {
        return array_merge(['city_id' => $cityId], $this->toQueryParams());
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
