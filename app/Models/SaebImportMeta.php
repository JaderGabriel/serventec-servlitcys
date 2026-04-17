<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Meta global da última importação SAEB (fonte, explicação modal, etc.).
 */
class SaebImportMeta extends Model
{
    protected $table = 'saeb_import_meta';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }
}
