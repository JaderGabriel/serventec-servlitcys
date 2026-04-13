<?php

namespace App\Support\Dashboard;

use Illuminate\Http\Request;

/**
 * Estado dos filtros estilo iEducar (ano, escola, curso, série, segmento, etapa, turno).
 * Mantém os parâmetros GET para o painel e futuras consultas à base da cidade.
 */
final class IeducarFilterState
{
    public function __construct(
        public ?int $ano_letivo,
        public ?string $escola_id,
        public ?string $curso_id,
        public ?string $serie_id,
        public ?string $segmento_id,
        public ?string $etapa_id,
        public ?string $turno_id,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $ano = $request->input('ano_letivo');

        return new self(
            ano_letivo: $ano !== null && $ano !== '' ? (int) $ano : null,
            escola_id: self::nullableString($request->input('escola_id')),
            curso_id: self::nullableString($request->input('curso_id')),
            serie_id: self::nullableString($request->input('serie_id')),
            segmento_id: self::nullableString($request->input('segmento_id')),
            etapa_id: self::nullableString($request->input('etapa_id')),
            turno_id: self::nullableString($request->input('turno_id')),
        );
    }

    /**
     * Parâmetros para query string (mantém filtros ao mudar de página ou aba).
     *
     * @return array<string, string|int|null>
     */
    public function toQueryParams(): array
    {
        return array_filter([
            'ano_letivo' => $this->ano_letivo,
            'escola_id' => $this->escola_id,
            'curso_id' => $this->curso_id,
            'serie_id' => $this->serie_id,
            'segmento_id' => $this->segmento_id,
            'etapa_id' => $this->etapa_id,
            'turno_id' => $this->turno_id,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Junta com city_id para links.
     *
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
