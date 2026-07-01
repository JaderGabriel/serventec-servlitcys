<?php

namespace App\Support\Horizonte;

use App\Models\InepCensoMunicipioMatricula;
use Illuminate\Support\Facades\Schema;

/** Janela temporal do Educacenso para o gráfico de matrículas e fase Horizonte. */
final class HorizonteEducacensoYearWindow
{
    /**
     * Últimos N anos consecutivos, terminando no ano mais recente indexado (ou exercício de referência).
     *
     * @return list<int>
     */
    public static function years(?int $limit = null): array
    {
        $count = max(2, min(10, $limit ?? (int) config('horizonte.enrollment_series.years', 5)));
        $anchor = self::anchorYear();
        $years = [];
        for ($offset = $count - 1; $offset >= 0; $offset--) {
            $years[] = $anchor - $offset;
        }

        return $years;
    }

    public static function anchorYear(): int
    {
        $anchor = 0;
        if (Schema::hasTable('inep_censo_municipio_matriculas')) {
            $anchor = (int) (InepCensoMunicipioMatricula::query()->max('ano') ?? 0);
        }
        if ($anchor < 2000) {
            $anchor = (int) config('horizonte.reference_year', (int) date('Y') - 1);
        }

        return $anchor;
    }
}
