<?php

namespace App\Models\Bi;

use Illuminate\Database\Eloquent\Model;

class BiClioSchool extends Model
{
    protected $table = 'bi_clio_school';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
