<?php

namespace App\Support\Fundeb;

use App\Services\Fundeb\FundebOpenDataImportService;

/**
 * Linguagem comum para leigos: exercício publicado, em formação, projeção e piso de referência.
 */
final class FundebValueLexicon
{
    /** Exercício com portaria FNDE já publicada (receita/complementações consolidadas). */
    public const PHASE_PUBLISHED = 'published';

    /** Último exercício de referência FUNDEB (defasagem FNDE — tipicamente ano civil − 1). */
    public const PHASE_REFERENCE = 'reference';

    /** Exercício em curso: matrículas e cadastro ainda em formação. */
    public const PHASE_IN_PROGRESS = 'in_progress';

    /** Exercício futuro: projeção de planejamento (efeito das matrículas vigentes). */
    public const PHASE_PROJECTION = 'projection';

    /**
     * Fase do exercício na linha do tempo FUNDEB.
     */
    public static function exercisePhase(int $exerciseYear, ?int $calendarYear = null): string
    {
        $calendarYear ??= (int) date('Y');
        $reference = FundebOpenDataImportService::suggestedImportYear();

        if ($exerciseYear > $calendarYear + 1) {
            return self::PHASE_PROJECTION;
        }
        if ($exerciseYear === $calendarYear + 1) {
            return self::PHASE_PROJECTION;
        }
        if ($exerciseYear === $calendarYear) {
            return self::PHASE_IN_PROGRESS;
        }
        if ($exerciseYear === $reference) {
            return self::PHASE_REFERENCE;
        }

        return self::PHASE_PUBLISHED;
    }

    public static function exercisePhaseLabel(int $exerciseYear, ?int $calendarYear = null): string
    {
        return match (self::exercisePhase($exerciseYear, $calendarYear)) {
            self::PHASE_REFERENCE => __('fundeb.semantics.phase_reference'),
            self::PHASE_IN_PROGRESS => __('fundeb.semantics.phase_in_progress'),
            self::PHASE_PROJECTION => __('fundeb.semantics.phase_projection'),
            default => __('fundeb.semantics.phase_published'),
        };
    }

    public static function exercisePhaseHint(int $exerciseYear, ?int $calendarYear = null): string
    {
        return match (self::exercisePhase($exerciseYear, $calendarYear)) {
            self::PHASE_REFERENCE => __('fundeb.semantics.phase_reference_hint'),
            self::PHASE_IN_PROGRESS => __('fundeb.semantics.phase_in_progress_hint'),
            self::PHASE_PROJECTION => __('fundeb.semantics.phase_projection_hint'),
            default => __('fundeb.semantics.phase_published_hint'),
        };
    }

    /**
     * Rótulo da coluna na matriz admin (exercício + fase).
     */
    public static function matrixColumnCaption(int $exerciseYear, ?int $calendarYear = null): string
    {
        $phase = self::exercisePhaseLabel($exerciseYear, $calendarYear);

        return __('fundeb.semantics.matrix_column', [
            'year' => (string) $exerciseYear,
            'phase' => $phase,
        ]);
    }

    /**
     * Natureza do valor gravado (complementa FundebMatrixCellPresentation).
     *
     * @return array{label: string, hint: string}
     */
    public static function valueNature(?string $fonte, bool $hasReference = true): array
    {
        if (! $hasReference) {
            return [
                'label' => __('fundeb.semantics.no_value'),
                'hint' => __('fundeb.matrix.empty_title'),
            ];
        }

        $display = FundebMatrixCellPresentation::forFonte($fonte, true);

        return [
            'label' => $display['label'],
            'hint' => $display['title'],
        ];
    }

    /**
     * Texto para matrículas do filtro vs exercício da portaria.
     */
    public static function matriculasExercicioNota(int $filterYear, int $portariaExerciseYear): ?string
    {
        if ($filterYear === $portariaExerciseYear) {
            return null;
        }

        if ($filterYear === (int) date('Y') && $portariaExerciseYear < $filterYear) {
            return __('fundeb.semantics.matriculas_vigentes_proximo_exercicio', [
                'mat_ano' => (string) $filterYear,
                'fundeb_ano' => (string) ($filterYear + 1),
            ]);
        }

        return __('fundeb.semantics.matriculas_ano_diferente', [
            'mat_ano' => (string) $filterYear,
            'exercicio' => (string) $portariaExerciseYear,
        ]);
    }

    /**
     * @return list<array{key: string, title: string, body: string}>
     */
    public static function layGuideItems(): array
    {
        return [
            [
                'key' => self::PHASE_PUBLISHED,
                'title' => __('fundeb.semantics.guide_published_title'),
                'body' => __('fundeb.semantics.guide_published_body'),
            ],
            [
                'key' => self::PHASE_REFERENCE,
                'title' => __('fundeb.semantics.guide_reference_title'),
                'body' => __('fundeb.semantics.guide_reference_body'),
            ],
            [
                'key' => self::PHASE_IN_PROGRESS,
                'title' => __('fundeb.semantics.guide_in_progress_title'),
                'body' => __('fundeb.semantics.guide_in_progress_body'),
            ],
            [
                'key' => self::PHASE_PROJECTION,
                'title' => __('fundeb.semantics.guide_projection_title'),
                'body' => __('fundeb.semantics.guide_projection_body'),
            ],
        ];
    }
}
