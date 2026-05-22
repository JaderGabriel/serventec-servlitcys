<?php

namespace App\Services\Analytics;

use App\Models\City;
use App\Models\InepCensoEscolaGeoAgg;
use App\Support\Analytics\AnalyticsReportMunicipalityOutlineSvg;
use App\Support\Dashboard\IeducarFilterState;

final class AnalyticsReportCoverBuilder
{
    public function __construct(
        private readonly AnalyticsReportCoverMapResolver $mapResolver,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(City $city, string $yearLabel, ?IeducarFilterState $filters = null): array
    {
        $ibge = $this->normalizeIbge((string) ($city->ibge_municipio ?? ''));
        $uf = strtoupper(trim((string) ($city->uf ?? '')));
        $ufName = $this->ufDisplayName($uf);

        $regionMacro = $this->regionMacro($city, $uf);
        $municipalityLine = $this->municipalityLine($city->name, $uf, $ufName);
        $maps = $this->mapResolver->resolve($city);
        $center = $this->mapResolver->resolveMunicipalityCenter($city);

        $municipalMap = $maps['municipal'] ?? null;
        $regionalMap = $maps['regional'] ?? null;

        $regionalPath = $this->regionalImagePath($uf);
        $regionalBrandUri = $this->fileToDataUri($regionalPath);

        $colors = config('analytics.pdf_report.colors', []);
        $primary = (string) ($colors['primary'] ?? '#0f766e');
        $caricatureUri = null;
        $mapCaption = __('Recorte municipal ilustrativo (sem unidades escolares)');
        $mapSource = 'municipality_outline';

        if ($center !== null) {
            $outline = AnalyticsReportMunicipalityOutlineSvg::render(
                (string) $city->name,
                $uf,
                $center['lat'],
                $center['lng'],
                680,
                280,
                $primary,
            );
            $caricatureUri = $outline['data_uri'];
        }

        $osmBackdropUri = $municipalMap['data_uri'] ?? null;
        $mapDataUri = $caricatureUri ?? $osmBackdropUri;
        if ($mapDataUri === $caricatureUri) {
            $mapSource = 'municipality_outline';
        } elseif ($osmBackdropUri !== null) {
            $mapCaption = $municipalMap['caption'] ?? __('OpenStreetMap (sem marcadores de escolas)');
            $mapSource = $municipalMap['source'] ?? 'openstreetmap_static';
        }

        $regionalMapUri = $regionalMap['data_uri'] ?? null;
        $regionalMapCaption = $regionalMap['caption'] ?? __('Recorte regional (OpenStreetMap)');

        if ($mapDataUri === null && $regionalMapUri !== null) {
            $mapDataUri = $regionalMapUri;
            $mapCaption = $regionalMapCaption;
        }

        return [
            'report_title' => __('Relatório analítico municipal'),
            'report_subtitle' => __('Educação pública · i-Educar · Censo · FUNDEB'),
            'municipality' => trim((string) $city->name),
            'uf' => $uf,
            'uf_name' => $ufName,
            'municipality_line' => $municipalityLine,
            'municipality_subtitle' => $this->municipalitySubtitle($ufName, $regionMacro),
            'ibge' => $ibge,
            'year_label' => $yearLabel,
            'year_value' => $this->yearValue($filters, $yearLabel),
            'region_label' => $this->regionLabel($city, $uf),
            'region_macro' => $regionMacro,
            'filter_details' => $this->filterDetails($filters),
            'coords_source' => $center['source'] ?? $maps['coords']['source'] ?? null,
            'map_image_data_uri' => $mapDataUri,
            'map_osm_backdrop_uri' => $caricatureUri !== null ? $osmBackdropUri : null,
            'map_image_url' => null,
            'map_caption' => $mapCaption,
            'map_source' => $mapSource,
            'regional_map_data_uri' => $regionalMapUri !== $mapDataUri ? $regionalMapUri : null,
            'regional_map_caption' => $regionalMapCaption,
            'regional_image_path' => $regionalPath,
            'regional_image_data_uri' => $regionalBrandUri,
        ];
    }

    private function municipalityLine(string $name, string $uf, string $ufName): string
    {
        $n = trim($name);
        if ($n === '') {
            return $uf !== '' ? $uf : '';
        }
        if ($uf === '') {
            return $n;
        }

        return $n.' - '.$uf;
    }

    /**
     * Subtítulo da capa: qualificadores após «Município - UF» (ex.: Bahia · Nordeste).
     */
    private function municipalitySubtitle(string $ufName, ?string $regionMacro): ?string
    {
        $parts = array_values(array_filter([
            $ufName !== '' ? $ufName : null,
            filled($regionMacro) ? $regionMacro : null,
        ], static fn ($p) => is_string($p) && trim($p) !== ''));

        return $parts === [] ? null : implode(' · ', array_unique($parts));
    }

    private function regionMacro(City $city, string $uf): ?string
    {
        $agg = InepCensoEscolaGeoAgg::query()
            ->when($uf !== '', fn ($q) => $q->where('sg_uf', $uf))
            ->when(filled($city->name), function ($q) use ($city): void {
                $q->whereRaw('LOWER(no_municipio) LIKE ?', ['%'.mb_strtolower((string) $city->name).'%']);
            })
            ->orderByDesc('nu_ano_censo')
            ->first();

        $macro = trim((string) ($agg?->no_regiao ?? ''));

        return $macro !== '' ? $macro : null;
    }

    private function yearValue(?IeducarFilterState $filters, string $yearLabel): string
    {
        if ($filters !== null && $filters->hasYearSelected() && ! $filters->isAllSchoolYears()) {
            return (string) $filters->ano_letivo;
        }

        return $yearLabel !== '' ? $yearLabel : '—';
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function filterDetails(?IeducarFilterState $filters): array
    {
        if ($filters === null) {
            return [];
        }

        $out = [];

        if ($filters->hasYearSelected()) {
            $out[] = [
                'label' => __('Ano letivo'),
                'value' => $filters->isAllSchoolYears()
                    ? __('Todos os anos')
                    : (string) $filters->ano_letivo,
            ];
        }

        if (filled($filters->escola_id)) {
            $out[] = [
                'label' => __('Escola'),
                'value' => __('Unidade #:id', ['id' => $filters->escola_id]),
            ];
        }

        if (filled($filters->curso_id)) {
            $out[] = [
                'label' => __('Curso / segmento'),
                'value' => '#'.$filters->curso_id,
            ];
        }

        if (filled($filters->turno_id)) {
            $out[] = [
                'label' => __('Turno'),
                'value' => '#'.$filters->turno_id,
            ];
        }

        if ($filters->inclusionSomenteNee()) {
            $out[] = [
                'label' => __('Inclusão'),
                'value' => __('Somente NEE'),
            ];
        } elseif ($filters->inclusionSomenteInconsistencias()) {
            $out[] = [
                'label' => __('Inclusão'),
                'value' => __('Somente inconsistências'),
            ];
        }

        return $out;
    }

    private function regionLabel(City $city, string $uf): ?string
    {
        $agg = InepCensoEscolaGeoAgg::query()
            ->when($uf !== '', fn ($q) => $q->where('sg_uf', $uf))
            ->when(filled($city->name), function ($q) use ($city): void {
                $q->whereRaw('LOWER(no_municipio) LIKE ?', ['%'.mb_strtolower((string) $city->name).'%']);
            })
            ->orderByDesc('nu_ano_censo')
            ->first();

        if ($agg === null) {
            return $uf !== '' ? $this->ufDisplayName($uf) : null;
        }

        return trim(implode(' · ', array_filter([
            $agg->no_municipio,
            $agg->sg_uf,
            $agg->no_regiao,
        ])));
    }

    private function ufDisplayName(string $uf): string
    {
        return match ($uf) {
            'AC' => 'Acre',
            'AL' => 'Alagoas',
            'AP' => 'Amapá',
            'AM' => 'Amazonas',
            'BA' => 'Bahia',
            'CE' => 'Ceará',
            'DF' => 'Distrito Federal',
            'ES' => 'Espírito Santo',
            'GO' => 'Goiás',
            'MA' => 'Maranhão',
            'MT' => 'Mato Grosso',
            'MS' => 'Mato Grosso do Sul',
            'MG' => 'Minas Gerais',
            'PA' => 'Pará',
            'PB' => 'Paraíba',
            'PR' => 'Paraná',
            'PE' => 'Pernambuco',
            'PI' => 'Piauí',
            'RJ' => 'Rio de Janeiro',
            'RN' => 'Rio Grande do Norte',
            'RS' => 'Rio Grande do Sul',
            'RO' => 'Rondônia',
            'RR' => 'Roraima',
            'SC' => 'Santa Catarina',
            'SP' => 'São Paulo',
            'SE' => 'Sergipe',
            'TO' => 'Tocantins',
            default => $uf,
        };
    }

    private function regionalImagePath(string $uf): ?string
    {
        $base = trim((string) config('analytics.pdf_report.cover.regional_image_base', 'images/pdf/regional'), '/');
        $candidates = [
            public_path($base.'/'.strtolower($uf).'.jpg'),
            public_path($base.'/'.strtolower($uf).'.png'),
            public_path($base.'/'.strtolower($uf).'.svg'),
            public_path($base.'/default.jpg'),
            public_path($base.'/default.png'),
            public_path($base.'/default.svg'),
        ];
        foreach ($candidates as $path) {
            if (is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function fileToDataUri(?string $absolutePath): ?string
    {
        if ($absolutePath === null || ! is_readable($absolutePath)) {
            return null;
        }

        $mime = match (strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION))) {
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'application/octet-stream',
        };

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($absolutePath));
    }

    private function normalizeIbge(string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', $raw);
        if ($digits === null || strlen($digits) < 6) {
            return null;
        }

        return str_pad($digits, 7, '0', STR_PAD_LEFT);
    }
}
