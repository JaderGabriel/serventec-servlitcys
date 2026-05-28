<?php

namespace App\Enums;

enum AdminSyncDomain: string
{
    case Fundeb = 'fundeb';
    case Funding = 'funding';
    case Geo = 'geo';
    case Pedagogical = 'pedagogical';
    case Cadastro = 'cadastro';
    case Ieducar = 'ieducar';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Fundeb => __('FUNDEB'),
            self::Funding => __('Financiamentos / repasses'),
            self::Geo => __('Geográfico'),
            self::Pedagogical => __('Pedagógico'),
            self::Cadastro => __('Cadastro público (CadÚnico)'),
            self::Ieducar => __('i-Educar'),
            self::System => __('Sistema'),
        };
    }
}
