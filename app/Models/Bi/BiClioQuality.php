<?php

namespace App\Models\Bi;

use Illuminate\Database\Eloquent\Model;

class BiClioQuality extends Model
{
    protected $table = 'bi_clio_quality';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'missing_triad' => 'boolean',
            'distortion_pct' => 'float',
            'density_avg' => 'float',
        ];
    }
}
