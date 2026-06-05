<?php

namespace App\Support\Ieducar;

use App\Models\City;
use App\Models\FundebMunicipioReference;
use App\Repositories\FundebMunicipioReferenceRepository;
use App\Support\Dashboard\IeducarFilterState;

/**
 * Sinais operacionais FUNDEB (referência importada × matrículas i-Educar × portaria).
 */
final class FundebOperationalSignals
{
    /**
     * @param  list<array<string, mixed>>  $dimensions
     * @return list<array<string, mixed>>
     */
    public static function append(
        array $dimensions,
        ?City $city = null,
        ?IeducarFilterState $filters = null,
        ?int $fundebAnchorAno = null,
    ): array {
        if ($city === null) {
            return $dimensions;
        }

        $existing = [];
        foreach ($dimensions as $d) {
            $existing[(string) ($d['id'] ?? '')] = true;
        }

        $out = $dimensions;
        foreach (self::buildSignals($city, $filters, $fundebAnchorAno) as $signal) {
            $id = (string) ($signal['id'] ?? '');
            if ($id === '' || isset($existing[$id])) {
                continue;
            }
            $out[] = $signal;
            $existing[$id] = true;
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildSignals(City $city, ?IeducarFilterState $filters, ?int $fundebAnchorAno = null): array
    {
        $signals = [];
        $ano = self::anchorAno($filters, $fundebAnchorAno);
        $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
        if ($ibge === null) {
            return [];
        }

        $ref = FundebMunicipioReference::query()
            ->where('ibge_municipio', $ibge)
            ->where('ano', $ano)
            ->first();

        if ($ref === null) {
            return [];
        }

        $meta = is_array($ref->meta) ? $ref->meta : [];
        $ieducarMeta = (int) ($meta['ieducar_matriculas'] ?? 0);
        $censoMeta = isset($meta['censo_matriculas']) && is_numeric($meta['censo_matriculas'])
            ? (int) $meta['censo_matriculas']
            : null;
        $fonteMat = (string) ($ref->matriculas_fonte ?? '');

        if ($fonteMat === 'censo_inep' && $ieducarMeta === 0 && $censoMeta !== null && $censoMeta > 0) {
            $signals[] = self::signalCensoSemIeducar($censoMeta, $ano, $city, $filters);
        }

        if (! empty($meta['ibge_nome_divergente'])) {
            $signals[] = self::signalIbgeNomeDivergente(
                $city,
                (string) ($meta['nome_oficial_fnde'] ?? ''),
                $filters,
            );
        }

        return array_values(array_filter($signals));
    }

    private static function anchorAno(?IeducarFilterState $filters, ?int $fundebAnchorAno = null): int
    {
        if ($filters !== null && $filters->hasYearSelected() && ! $filters->isAllSchoolYears()) {
            return (int) $filters->yearFilterValue();
        }

        if ($fundebAnchorAno !== null && $fundebAnchorAno >= 2000) {
            return $fundebAnchorAno;
        }

        return max(2000, (int) date('Y') - 1);
    }

    /**
     * @return array<string, mixed>
     */
    private static function signalCensoSemIeducar(int $censo, int $ano, ?City $city, ?IeducarFilterState $filters): array
    {
        $id = 'fundeb_vaaf_fonte_censo';

        return [
            'id' => $id,
            'title' => __('FUNDEB — VAAF estimado com Censo INEP'),
            'vaar_refs' => [__('Referência FUNDEB'), __('Matrículas i-Educar')],
            'availability' => 'available',
            'has_issue' => true,
            'detected' => true,
            'total' => $censo,
            'status' => 'warning',
            'severity' => 'warning',
            'operational_note' => __(
                'A referência FUNDEB :ano usa :censo matrículas do Censo INEP (i-Educar = 0 na importação). Projeções de Finanças podem divergir das matrículas activas no painel.',
                ['ano' => (string) $ano, 'censo' => number_format($censo, 0, ',', '.')],
            ),
            'source_tab' => 'fundeb',
            'correction_tab' => 'enrollment',
            'correction_label' => __('Ver Matrículas'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function signalIbgeNomeDivergente(City $city, string $nomeOficial, ?IeducarFilterState $filters): array
    {
        $id = 'fundeb_ibge_nome_divergente';

        return [
            'id' => $id,
            'title' => __('FUNDEB — IBGE × nome oficial divergente'),
            'vaar_refs' => [__('Território'), __('Portaria FNDE')],
            'availability' => 'available',
            'has_issue' => true,
            'detected' => true,
            'total' => 1,
            'status' => 'warning',
            'severity' => 'warning',
            'operational_note' => __(
                'Cadastro «:sistema» (IBGE :ibge) — nome oficial FNDE: «:oficial». Revise mapas, geo-sync e relatórios territoriais.',
                [
                    'sistema' => $city->name,
                    'ibge' => (string) FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio),
                    'oficial' => $nomeOficial !== '' ? $nomeOficial : __('desconhecido'),
                ],
            ),
            'source_tab' => 'fundeb',
            'correction_tab' => null,
            'correction_label' => null,
        ];
    }
}
