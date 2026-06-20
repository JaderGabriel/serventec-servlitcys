<?php

namespace App\Support\Horizonte;

/** Fases do abastecimento bimestral Horizonte (ordem fixa). */
final class HorizonteFortnightlyFeedPhaseCatalog
{
    /**
     * @return list<array{key: string, label: string, skip_option: string}>
     */
    public static function definitions(): array
    {
        return [
            ['key' => 'fundeb_receita', 'label' => 'FUNDEB', 'skip_option' => 'skip_fundeb'],
            ['key' => 'censo_matriculas', 'label' => 'Censo', 'skip_option' => 'skip_censo'],
            ['key' => 'cadunico_sync', 'label' => 'CadÚnico', 'skip_option' => 'skip_cadunico'],
            ['key' => 'sidra_demography', 'label' => 'SIDRA', 'skip_option' => 'skip_sidra'],
            ['key' => 'repasses_tesouro', 'label' => 'Repasses', 'skip_option' => 'skip_repasses'],
            ['key' => 'saeb_planilhas', 'label' => 'SAEB', 'skip_option' => 'skip_saeb'],
            ['key' => 'ibge_catalog', 'label' => 'IBGE', 'skip_option' => 'skip_ibge'],
            ['key' => 'sge_registry', 'label' => 'SGE', 'skip_option' => 'skip_sge'],
            ['key' => 'official_check', 'label' => 'Verificação', 'skip_option' => 'skip_verify'],
        ];
    }

    /**
     * @param  array<string, bool>  $options
     * @return list<string>
     */
    public static function queueFromOptions(array $options): array
    {
        $queue = [];
        foreach (self::definitions() as $def) {
            $skipKey = $def['skip_option'];
            if ($options[$skipKey] ?? false) {
                continue;
            }
            $queue[] = $def['key'];
        }

        return $queue;
    }

    public static function label(string $key): string
    {
        foreach (self::definitions() as $def) {
            if ($def['key'] === $key) {
                return __($def['label']);
            }
        }

        return $key;
    }
}
