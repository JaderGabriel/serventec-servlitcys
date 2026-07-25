<?php

namespace App\Services\Funding;

use App\Models\City;
use App\Models\MunicipalEmendaSnapshot;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Repositories\MunicipalEmendaSnapshotRepository;
use App\Support\Ieducar\DiscrepanciesFundingImpact;

/**
 * Catálogo de emendas educação para a aba Finanças (consultoria).
 */
final class MunicipalEmendaCatalogService
{
    public const PORTAL_CONSULTA_URL = 'https://portaldatransparencia.gov.br/emendas/consulta';

    public function __construct(
        private MunicipalEmendaSnapshotRepository $snapshots,
    ) {}

    /**
     * @return array{
     *   available: bool,
     *   year: int|null,
     *   intro: string,
     *   footnote: string,
     *   empty_message: string|null,
     *   enrich_hint: string|null,
     *   portal_url: string,
     *   count: int,
     *   total_empenhado: float|null,
     *   total_pago: float|null,
     *   total_empenhado_fmt: string|null,
     *   total_pago_fmt: string|null,
     *   rows: list<array<string, mixed>>
     * }
     */
    public function build(?City $city, int $year): array
    {
        $portalUrl = self::PORTAL_CONSULTA_URL;
        $base = [
            'available' => false,
            'year' => $year >= 2000 ? $year : null,
            'intro' => __(
                'Emendas parlamentares com função Educação no Portal da Transparência, ligadas ao município pela localidade do gasto (indicativo — sem filtro IBGE na API).'
            ),
            'footnote' => __(
                'Valores e vínculos são indicativos do Portal. Não some com FUNDEB, Tempo Real ou cobertura i-Educar. Emendas só de UF (ex.: «BAHIA (UF)») não entram neste catálogo municipal.'
            ),
            'empty_message' => null,
            'enrich_hint' => null,
            'portal_url' => $portalUrl,
            'count' => 0,
            'total_empenhado' => null,
            'total_pago' => null,
            'total_empenhado_fmt' => null,
            'total_pago_fmt' => null,
            'rows' => [],
        ];

        if ($city === null || $year < 2000) {
            $base['empty_message'] = __('Selecione cidade e ano letivo para ver emendas.');

            return $base;
        }

        $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
        if ($ibge === null) {
            $base['empty_message'] = __('Município sem código IBGE — não é possível filtrar emendas.');

            return $base;
        }

        try {
            $models = $this->snapshots->forCityYear($city, $year);
        } catch (\Throwable) {
            $base['empty_message'] = __('Catálogo de emendas indisponível neste ambiente (tabela ou ligação).');

            return $base;
        }

        if ($models === []) {
            $base['available'] = true;
            $base['empty_message'] = __(
                'Nenhuma emenda de educação com localidade «:cidade - :uf» no ano :ano.',
                ['cidade' => (string) $city->name, 'uf' => (string) $city->uf, 'ano' => (string) $year]
            );
            $base['enrich_hint'] = __(
                'Se ainda não importou: php artisan funding:enrich-consultoria-emendas --ano=:ano --city=:id',
                ['ano' => (string) $year, 'id' => (string) $city->id]
            );

            return $base;
        }

        $rows = [];
        $sumEmp = 0.0;
        $sumPago = 0.0;
        $hasEmp = false;
        $hasPago = false;

        foreach ($models as $m) {
            if (! $m instanceof MunicipalEmendaSnapshot) {
                continue;
            }
            $emp = $m->valor_empenhado;
            $pago = $m->valor_pago;
            if ($emp !== null) {
                $sumEmp += (float) $emp;
                $hasEmp = true;
            }
            if ($pago !== null) {
                $sumPago += (float) $pago;
                $hasPago = true;
            }

            $docs = is_array($m->documentos) ? $m->documentos : [];
            $docRows = [];
            foreach ($docs as $doc) {
                if (! is_array($doc)) {
                    continue;
                }
                $docRows[] = [
                    'data' => (string) ($doc['data'] ?? '—'),
                    'fase' => (string) ($doc['fase'] ?? '—'),
                    'codigo' => (string) ($doc['codigoDocumentoResumido'] ?? $doc['codigoDocumento'] ?? '—'),
                    'especie' => (string) ($doc['especieTipo'] ?? ''),
                ];
            }

            $rows[] = [
                'codigo' => (string) $m->codigo_emenda,
                'numero' => $m->numero_emenda,
                'tipo' => $m->tipo_emenda,
                'autor' => $m->autor ?: '—',
                'localidade' => $m->localidade_do_gasto,
                'funcao' => $m->funcao,
                'subfuncao' => $m->subfuncao,
                'valor_empenhado' => $emp !== null ? (float) $emp : null,
                'valor_liquidado' => $m->valor_liquidado !== null ? (float) $m->valor_liquidado : null,
                'valor_pago' => $pago !== null ? (float) $pago : null,
                'valor_empenhado_fmt' => $emp !== null ? DiscrepanciesFundingImpact::formatBrl((float) $emp) : '—',
                'valor_liquidado_fmt' => $m->valor_liquidado !== null
                    ? DiscrepanciesFundingImpact::formatBrl((float) $m->valor_liquidado)
                    : '—',
                'valor_pago_fmt' => $pago !== null ? DiscrepanciesFundingImpact::formatBrl((float) $pago) : '—',
                'documentos' => $docRows,
                'documentos_count' => count($docRows),
                'portal_url' => $portalUrl,
            ];
        }

        return [
            'available' => true,
            'year' => $year,
            'intro' => $base['intro'],
            'footnote' => $base['footnote'],
            'empty_message' => null,
            'enrich_hint' => null,
            'portal_url' => $portalUrl,
            'count' => count($rows),
            'total_empenhado' => $hasEmp ? round($sumEmp, 2) : null,
            'total_pago' => $hasPago ? round($sumPago, 2) : null,
            'total_empenhado_fmt' => $hasEmp ? DiscrepanciesFundingImpact::formatBrl($sumEmp) : null,
            'total_pago_fmt' => $hasPago ? DiscrepanciesFundingImpact::formatBrl($sumPago) : null,
            'rows' => $rows,
        ];
    }
}
