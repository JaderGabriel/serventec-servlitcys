<?php

namespace App\Models\Bi;

use Illuminate\Database\Eloquent\Model;

class BiClioCampaign extends Model
{
    protected $table = 'bi_clio_campaign';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reference_date' => 'date',
            'refreshed_at' => 'datetime',
            'triade_pct' => 'float',
            'distortion_pct' => 'float',
            'density_avg' => 'float',
        ];
    }
}
