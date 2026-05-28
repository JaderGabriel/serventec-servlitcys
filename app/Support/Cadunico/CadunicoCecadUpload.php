<?php

namespace App\Support\Cadunico;

use App\Models\City;
use App\Repositories\FundebMunicipioReferenceRepository;
use Illuminate\Http\UploadedFile;

/**
 * Grava upload Cecad na pasta de storage com nome previsível para importação automática.
 */
final class CadunicoCecadUpload
{
    /**
     * @return array{path: string, filename: string}
     */
    public static function store(UploadedFile $file, int $ano, ?City $city = null): array
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($ext, ['csv', 'txt'], true)) {
            $ext = 'csv';
        }

        $root = CadunicoStoragePaths::storageRoot();
        if (! is_dir($root)) {
            mkdir($root, 0755, true);
        }

        $ibge = $city !== null
            ? FundebMunicipioReferenceRepository::normalizeIbge($city->ibge_municipio)
            : null;

        $filename = $ibge !== null
            ? $ibge.'_'.$ano.'.'.$ext
            : 'nacional_'.$ano.'.'.$ext;

        $absolute = $root.'/'.$filename;
        $file->move($root, $filename);

        return ['path' => $absolute, 'filename' => $filename];
    }
}
