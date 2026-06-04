<?php

namespace App\Services\Cadunico;

use App\Models\City;
use App\Repositories\CadunicoMunicipioSnapshotRepository;
use App\Repositories\CadunicoTerritorioSnapshotRepository;
use App\Repositories\FundebMunicipioReferenceRepository;

/**
 * Importa territórios a partir de fontes oficiais IBGE (Censo 2022 + WFS).
 *
 * População escolar 4–17 por território: rateio do CadÚnico municipal proporcional
 * à população total do Censo em cada bairro/setor (v0001).
 */
final class CadunicoTerritorioOfficialImportService
{
    public function __construct(
        private CadunicoIbgeCensoAgregadosCache $agregados,
        private CadunicoIbgeMalhaWfsClient $wfs,
        private CadunicoTerritorioSnapshotRepository $repository,
        private CadunicoMunicipioSnapshotRepository $cadunicoSnapshots,
    ) {}

    /**
     * @return array{success: bool, imported: int, message: string, fonte: string}
     */
    public function importForCity(City $city, int $ano): array
    {
        $ibge = FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio);
        if ($ibge === null) {
            return [
                'success' => false,
                'imported' => 0,
                'message' => __('Município sem IBGE.'),
                'fonte' => '',
            ];
        }

        $cadRow = $this->cadunicoSnapshots->findForCityYear($city, $ano);
        if ($cadRow === null) {
            return [
                'success' => false,
                'imported' => 0,
                'message' => __('Importe o CadÚnico municipal antes (`cadunico:sync-city`).'),
                'fonte' => '',
            ];
        }

        $cadTotal = $cadRow->totalCriancasEscolaridade();
        if ($cadTotal <= 0) {
            return [
                'success' => false,
                'imported' => 0,
                'message' => __('CadÚnico municipal sem população escolar.'),
                'fonte' => '',
            ];
        }

        $territorios = $this->agregados->territoriosForMunicipio($ibge);
        if ($territorios === []) {
            return [
                'success' => false,
                'imported' => 0,
                'message' => __('IBGE sem bairros/setores para o município nos agregados Censo 2022.'),
                'fonte' => 'ibge_censo_2022',
            ];
        }

        $tipo = $territorios[0]['tipo'] ?? 'setor';
        $centroids = $this->wfs->centroidsByCodigo($ibge, $tipo);
        $popSum = max(1, array_sum(array_column($territorios, 'populacao')));
        $vulnPct = $this->municipalVulnerabilidadePct($cadRow);

        $imported = 0;
        $remaining = $cadTotal;
        $lastIndex = count($territorios) - 1;

        foreach ($territorios as $index => $row) {
            $codigo = (string) $row['codigo'];
            if ($index === $lastIndex) {
                $criancas = max(0, $remaining);
            } else {
                $criancas = max(0, (int) round($cadTotal * ((int) ($row['populacao'] ?? 0) / $popSum)));
                if ($criancas <= 0 && ($row['populacao'] ?? 0) > 0) {
                    $criancas = 1;
                }
                $remaining -= $criancas;
            }

            $centroid = $centroids[$codigo] ?? null;

            $this->repository->upsert($ibge, $ano, $codigo, [
                'territorio_nome' => (string) ($row['nome'] ?? $codigo),
                'territorio_tipo' => $tipo,
                'criancas_4_17' => $criancas,
                'criancas_4_5' => 0,
                'criancas_6_10' => 0,
                'criancas_11_14' => 0,
                'criancas_15_17' => 0,
                'familias_beneficio' => 0,
                'indice_vulnerabilidade' => $vulnPct,
                'latitude' => $centroid['lat'] ?? null,
                'longitude' => $centroid['lng'] ?? null,
                'fonte' => 'ibge_censo_2022_wfs',
                'metadados' => [
                    'censo_pop_total' => (int) ($row['populacao'] ?? 0),
                    'cadunico_municipal_4_17' => $cadTotal,
                    'rateio' => 'proporcional_v0001',
                ],
            ]);
            $imported++;
        }

        return [
            'success' => $imported > 0,
            'imported' => $imported,
            'message' => $imported > 0
                ? __(':n território(s) IBGE importado(s) (:tipo).', ['n' => $imported, 'tipo' => $tipo])
                : __('Nenhum território gravado.'),
            'fonte' => 'ibge_censo_2022_wfs',
        ];
    }

    /**
     * @param  \App\Models\CadunicoMunicipioSnapshot  $cadRow
     */
    private function municipalVulnerabilidadePct($cadRow): ?float
    {
        $v = is_array($cadRow->metadados['vulnerabilidade'] ?? null)
            ? $cadRow->metadados['vulnerabilidade']
            : [];
        $pct = $v['pct_criancas_pbf'] ?? null;

        return is_numeric($pct) ? (float) $pct : null;
    }
}
