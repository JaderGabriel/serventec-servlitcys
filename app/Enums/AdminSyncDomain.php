<?php

namespace App\Enums;

enum AdminSyncDomain: string
{
    case Fundeb = 'fundeb';
    case Geo = 'geo';
    case Pedagogical = 'pedagogical';
    case Ieducar = 'ieducar';

    public function label(): string
    {
        return match ($this) {
            self::Fundeb => __('FUNDEB'),
            self::Geo => __('Geográfico'),
            self::Pedagogical => __('Pedagógico'),
            self::Ieducar => __('i-Educar'),
        };
    }
}
