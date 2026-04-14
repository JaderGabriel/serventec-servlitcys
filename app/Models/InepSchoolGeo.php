<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InepSchoolGeo extends Model
{
    protected $fillable = [
        'inep_code',
        'lat',
        'lng',
        'source',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'inep_code' => 'integer',
            'lat' => 'float',
            'lng' => 'float',
            'payload' => 'array',
        ];
    }
}
