<?php

namespace App\Services\Analytics;

use App\Models\City;
use App\Models\InepCensoEscolaGeoAgg;
use App\Models\SchoolUnitGeo;

final class AnalyticsReportCoverBuilder
{
    /**
     * @return array{
     *   municipality: string,
     *   uf: string,
     *   ibge: ?string,
     *   year_label: string,
     *   region_label: ?string,
     *   map_image_url: ?string,
     *   regional_image_path: ?string,
     *   regional_image_data_uri: ?string
     * }
     */
    public function build(City $city, string $yearLabel): array
    {
        $ibge = filled($city->ibge_municipio) ? str_pad(preg_replace('/\D/', '', (string) $city->ibge_municipio), 7, '0', STR_PAD_LEFT) : null;
        $uf = strtoupper(trim((string) ($city->uf ?? '')));

        $region = null;
        $agg = InepCensoEscolaGeoAgg::query()
            ->when($uf !== '', fn ($q) => $q->where('sg_uf', $uf))
            ->when(filled($city->name), function ($q) use ($city): void {
                $q->whereRaw('LOWER(no_municipio) LIKE ?', ['%'.mb_strtolower((string) $city->name).'%']);
            })
            ->orderByDesc('nu_ano_censo')
            ->first();
        if ($agg !== null) {
            $region = trim(implode(' · ', array_filter([
                $agg->no_municipio,
                $agg->sg_uf,
                $agg->no_regiao,
            ])));
        }

        $coords = $this->municipalityCenter($city);
        $mapUrl = null;
        if ($coords !== null) {
            $zoom = (int) config('analytics.pdf_report.cover.map_zoom', 9);
            $mapUrl = sprintf(
                'https://staticmap.openstreetmap.de/staticmap.php?center=%s,%s&zoom=%d&size=640x280&markers=%s,%s,red-pushpin',
                $coords['lat'],
                $coords['lng'],
                $zoom,
                $coords['lat'],
                $coords['lng']
            );
        }

        $regionalPath = $this->regionalImagePath($uf);
        $dataUri = $this->fileToDataUri($regionalPath);

        return [
            'municipality' => (string) $city->name,
            'uf' => $uf,
            'ibge' => $ibge,
            'year_label' => $yearLabel,
            'region_label' => $region,
            'map_image_url' => $mapUrl,
            'regional_image_path' => $regionalPath,
            'regional_image_data_uri' => $dataUri,
        ];
    }

    /**
     * @return ?array{lat: string, lng: string}
     */
    private function municipalityCenter(City $city): ?array
    {
        $row = SchoolUnitGeo::query()
            ->where('city_id', $city->id)
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->selectRaw('avg(lat) as lat, avg(lng) as lng')
            ->first();

        if ($row === null || $row->lat === null || $row->lng === null) {
            return null;
        }

        return [
            'lat' => number_format((float) $row->lat, 5, '.', ''),
            'lng' => number_format((float) $row->lng, 5, '.', ''),
        ];
    }

    private function regionalImagePath(string $uf): ?string
    {
        $base = trim((string) config('analytics.pdf_report.cover.regional_image_base', 'images/pdf/regional'), '/');
        $candidates = [
            public_path($base.'/'.strtolower($uf).'.jpg'),
            public_path($base.'/'.strtolower($uf).'.png'),
            public_path($base.'/'.strtolower($uf).'.svg'),
            public_path($base.'/default.jpg'),
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
}
