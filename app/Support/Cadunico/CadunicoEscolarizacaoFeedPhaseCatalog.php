<?php

namespace App\Support\Cadunico;

/** Fases do abastecimento bimestral do card Escolarização (CadÚnico × Censo). */
final class CadunicoEscolarizacaoFeedPhaseCatalog
{
    /**
     * @return list<array{key: string, label: string}>
     */
    public static function definitions(): array
    {
        return [
            ['key' => 'cadunico_sync', 'label' => 'CadÚnico'],
            ['key' => 'censo_matriculas', 'label' => 'Censo matrículas'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function phaseKeys(): array
    {
        return array_column(self::definitions(), 'key');
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
