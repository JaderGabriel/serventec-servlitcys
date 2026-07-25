<?php

namespace App\Services\Cadunico;

use App\Models\MunicipalBenefitSnapshot;
use App\Repositories\MunicipalBenefitSnapshotRepository;

/**
 * Callouts CUN-04 — contexto PBF/NBF/BPC agregado no card Escolarização.
 * Não identifica alunos fora da escola (sem NIS/CPF).
 */
final class CadunicoBeneficiosEscolarizacaoCalloutBuilder
{
    public function __construct(
        private MunicipalBenefitSnapshotRepository $snapshots,
    ) {}

    /**
     * @param  array<string, mixed>|null  $escolarizacaoTotais  totais do card (possivel_fora_escola, etc.)
     * @return array<string, mixed>
     */
    public function build(?string $ibge, ?array $escolarizacaoTotais = null): array
    {
        $ibge = preg_replace('/\D/', '', (string) $ibge) ?: '';
        if (strlen($ibge) !== 7) {
            return $this->empty(__('IBGE municipal em falta para cruzar benefícios Portal.'));
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('municipal_benefit_snapshots')) {
            return $this->empty(__('Tabela de benefícios Portal ainda não migrada.'));
        }

        $byProg = $this->snapshots->latestByPrograma($ibge);
        if ($byProg === []) {
            return $this->empty(
                __('Sem série PBF/NBF/BPC para este município. Rode: php artisan cadunico:sync-beneficios-portal'),
            );
        }

        $nbf = $byProg[MunicipalBenefitSnapshot::PROGRAMA_NBF] ?? null;
        $pbf = $byProg[MunicipalBenefitSnapshot::PROGRAMA_PBF] ?? null;
        $bpc = $byProg[MunicipalBenefitSnapshot::PROGRAMA_BPC] ?? null;

        $bolsa = ($nbf !== null && (int) $nbf->quantidade_beneficiados > 0) ? $nbf : $pbf;
        $bolsaLabel = $bolsa?->programa === MunicipalBenefitSnapshot::PROGRAMA_NBF
            ? __('Novo Bolsa Família')
            : __('Bolsa Família');

        $callouts = [];
        $metrics = [];

        if ($bolsa !== null && (int) $bolsa->quantidade_beneficiados > 0) {
            $metrics['bolsa'] = $this->metricFrom($bolsa, $bolsaLabel);
            $callouts[] = [
                'tone' => 'info',
                'text' => __(':prog — :n famílias/benefícios no mês :mes (valor :v). Fonte: Portal da Transparência (agregado).', [
                    'prog' => $bolsaLabel,
                    'n' => number_format((int) $bolsa->quantidade_beneficiados, 0, ',', '.'),
                    'mes' => $bolsa->mesAnoLabel(),
                    'v' => $bolsa->valor !== null
                        ? 'R$ '.number_format((float) $bolsa->valor, 2, ',', '.')
                        : '—',
                ]),
            ];
        }

        if ($bpc !== null && (int) $bpc->quantidade_beneficiados > 0) {
            $metrics['bpc'] = $this->metricFrom($bpc, __('BPC'));
            $callouts[] = [
                'tone' => 'info',
                'text' => __('BPC — :n benefícios no mês :mes (valor :v). Contexto de inclusão/vulnerabilidade; não identifica alunos.', [
                    'n' => number_format((int) $bpc->quantidade_beneficiados, 0, ',', '.'),
                    'mes' => $bpc->mesAnoLabel(),
                    'v' => $bpc->valor !== null
                        ? 'R$ '.number_format((float) $bpc->valor, 2, ',', '.')
                        : '—',
                ]),
            ];
        }

        $foraEscola = isset($escolarizacaoTotais['possivel_fora_escola'])
            ? (int) $escolarizacaoTotais['possivel_fora_escola']
            : 0;
        $bolsaQtd = $bolsa !== null ? (int) $bolsa->quantidade_beneficiados : 0;

        if ($foraEscola >= 20 && $bolsaQtd > 0) {
            $callouts[] = [
                'tone' => 'warning',
                'text' => __(
                    'Pressão social: estimativa de :fora possivelmente fora da escola (CadÚnico × Censo) com :bolsa no :prog. Use para priorizar busca activa / articulação CRAS — o Portal não prova quem está fora da escola.',
                    [
                        'fora' => number_format($foraEscola, 0, ',', '.'),
                        'bolsa' => number_format($bolsaQtd, 0, ',', '.'),
                        'prog' => $bolsaLabel,
                    ],
                ),
            ];
        }

        $callouts[] = [
            'tone' => 'muted',
            'text' => __(
                'Limite LGPD: só agregados municipais (quantidade/valor). Endpoints por NIS/CPF não são usados. Benefício ≠ matrícula.',
            ),
        ];

        $mesRef = $bolsa?->mesAnoLabel() ?? $bpc?->mesAnoLabel() ?? '—';

        return [
            'available' => $callouts !== [] && ($bolsaQtd > 0 || ($bpc !== null && (int) $bpc->quantidade_beneficiados > 0)),
            'mes_referencia' => $mesRef,
            'metrics' => $metrics,
            'callouts' => $callouts,
            'enrich_hint' => 'php artisan cadunico:sync-beneficios-portal',
            'disclaimer' => __('Portal da Transparência — agregados PBF/NBF/BPC por IBGE (CUN-04).'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function empty(string $message): array
    {
        return [
            'available' => false,
            'message' => $message,
            'mes_referencia' => null,
            'metrics' => [],
            'callouts' => [],
            'enrich_hint' => 'php artisan cadunico:sync-beneficios-portal',
            'disclaimer' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function metricFrom(MunicipalBenefitSnapshot $row, string $label): array
    {
        return [
            'programa' => $row->programa,
            'label' => $label,
            'quantidade' => (int) $row->quantidade_beneficiados,
            'quantidade_fmt' => number_format((int) $row->quantidade_beneficiados, 0, ',', '.'),
            'valor' => $row->valor,
            'valor_fmt' => $row->valor !== null
                ? 'R$ '.number_format((float) $row->valor, 2, ',', '.')
                : '—',
            'mes_ano' => (int) $row->mes_ano,
            'mes_label' => $row->mesAnoLabel(),
        ];
    }
}
