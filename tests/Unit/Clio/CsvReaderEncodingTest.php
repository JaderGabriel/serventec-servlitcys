<?php

namespace Tests\Unit\Clio;

use App\Services\Clio\Parse\CsvReader;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CsvReaderEncodingTest extends TestCase
{
    #[Test]
    public function converte_celulas_latin1_para_utf8_valido(): void
    {
        $csv = new CsvReader;
        $latin1 = mb_convert_encoding('Conceição do Jacuípe', 'ISO-8859-1', 'UTF-8');

        $utf8 = $csv->toUtf8($latin1);

        $this->assertSame('Conceição do Jacuípe', $utf8);
        $this->assertTrue(mb_check_encoding($utf8, 'UTF-8'));
        $this->assertNotFalse(json_encode(['name' => $utf8], JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function le_csv_windows1252_e_expõe_utf8_nas_linhas(): void
    {
        $header = 'Código da Escola;Nome da Escola';
        $name = mb_convert_encoding('ESCOLA Nª SENHORA DA CONCEIÇÃO', 'Windows-1252', 'UTF-8');
        $body = $header."\n".'29091705;'.$name."\n";
        $tmp = tempnam(sys_get_temp_dir(), 'clio_enc');
        file_put_contents($tmp, $body);

        try {
            $parsed = (new CsvReader)->read($tmp);
            $this->assertSame('legacy-to-utf8', $parsed['encoding']);
            $this->assertSame('ESCOLA Nª SENHORA DA CONCEIÇÃO', $parsed['rows'][0]['Nome da Escola']);
            $this->assertNotFalse(json_encode($parsed['rows'], JSON_THROW_ON_ERROR));
        } finally {
            @unlink($tmp);
        }
    }

    #[Test]
    public function deep_utf8_sanitiza_arvores_para_json(): void
    {
        $csv = new CsvReader;
        $bad = mb_convert_encoding('Educação', 'ISO-8859-1', 'UTF-8');
        $tree = [
            'aggregates' => [
                'by_etapa_ensino' => [$bad => 3],
            ],
        ];

        $clean = $csv->deepUtf8($tree);

        $this->assertSame(3, $clean['aggregates']['by_etapa_ensino']['Educação']);
        $this->assertNotFalse(json_encode($clean, JSON_THROW_ON_ERROR));
    }
}
