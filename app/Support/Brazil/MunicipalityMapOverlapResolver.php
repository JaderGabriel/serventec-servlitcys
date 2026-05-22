<?php

namespace App\Support\Brazil;

/**
 * Garante distância mínima entre marcadores no mapa do Início (evita pontos sobrepostos).
 */
final class MunicipalityMapOverlapResolver
{
    private const MIN_DISTANCE_DEG = 0.14;

    /**
     * @param  list<array{lat: float, lng: float, coord_source?: string}>  $markers
     * @return list<array{lat: float, lng: float, coord_source?: string}>
     */
    public function separate(array $markers): array
    {
        if (count($markers) < 2) {
            return $markers;
        }

        $out = $markers;
        $maxPasses = count($out) * 3;

        for ($pass = 0; $pass < $maxPasses; $pass++) {
            $moved = false;
            for ($i = 0; $i < count($out); $i++) {
                for ($j = $i + 1; $j < count($out); $j++) {
                    if (! $this->tooClose($out[$i], $out[$j])) {
                        continue;
                    }
                    $out[$j] = $this->pushApart($out[$j], $out[$i], $j);
                    $moved = true;
                }
            }
            if (! $moved) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param  array{lat: float, lng: float}  $a
     * @param  array{lat: float, lng: float}  $b
     */
    private function tooClose(array $a, array $b): bool
    {
        $dLat = abs((float) $a['lat'] - (float) $b['lat']);
        $dLng = abs((float) $a['lng'] - (float) $b['lng']);

        return $dLat < self::MIN_DISTANCE_DEG && $dLng < self::MIN_DISTANCE_DEG;
    }

    /**
     * @param  array{lat: float, lng: float, coord_source?: string}  $target
     * @param  array{lat: float, lng: float}  $anchor
     * @return array{lat: float, lng: float, coord_source?: string}
     */
    private function pushApart(array $target, array $anchor, int $seed): array
    {
        $angle = (2 * M_PI * ($seed % 12)) / 12;
        $lat = (float) $anchor['lat'] + self::MIN_DISTANCE_DEG * cos($angle);
        $lng = (float) $anchor['lng'] + self::MIN_DISTANCE_DEG * sin($angle) * 1.15;

        [$lat, $lng] = $this->clampBrazil($lat, $lng);

        $source = (string) ($target['coord_source'] ?? '');
        if (! str_contains($source, 'offset')) {
            $source = $source !== '' ? $source.'+offset' : 'offset';
        }

        $target['lat'] = round($lat, 5);
        $target['lng'] = round($lng, 5);
        $target['coord_source'] = $source;

        return $target;
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function clampBrazil(float $lat, float $lng): array
    {
        return [
            max(-33.75, min(5.27, $lat)),
            max(-73.99, min(-32.39, $lng)),
        ];
    }
}
