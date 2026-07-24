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

    #[Test]
    public function detecta_delimitador_ponto_e_virgula(): void
    {
        $body = "Código da escola;Nome da escola\n29174651;Alpha\n";
        $tmp = tempnam(sys_get_temp_dir(), 'clio_semi');
        file_put_contents($tmp, $body);

        try {
            $parsed = (new CsvReader)->read($tmp);
            $this->assertSame(';', $parsed['delimiter']);
            $this->assertSame(['Código da escola', 'Nome da escola'], $parsed['headers']);
            $this->assertSame('Alpha', $parsed['rows'][0]['Nome da escola']);
        } finally {
            @unlink($tmp);
        }
    }

    #[Test]
    public function detecta_delimitador_virgula_e_le_colunas_obrigatorias(): void
    {
        $body = "Data de Referência,UF,Código da escola,Nome da escola\n21/07/2026,BA,29174651,Escola Municipal Alpha\n";
        $tmp = tempnam(sys_get_temp_dir(), 'clio_comma');
        file_put_contents($tmp, $body);

        try {
            $reader = new CsvReader;
            $this->assertSame(',', $reader->detectDelimiter($tmp));
            $parsed = $reader->read($tmp);
            $this->assertSame(',', $parsed['delimiter']);
            $this->assertContains('Código da escola', $parsed['headers']);
            $this->assertContains('Nome da escola', $parsed['headers']);
            $this->assertSame('29174651', $parsed['rows'][0]['Código da escola']);
            $this->assertSame([], $reader->missingHeaders($parsed['headers'], ['Código da escola', 'Nome da escola']));
        } finally {
            @unlink($tmp);
        }
    }

    #[Test]
    public function acomp_com_virgula_passa_no_parser(): void
    {
        $header = 'Data de Referência,UF,Município,Dependência Administrativa,Código da escola,Nome da escola,Situação de Funcionamento,Localização,Forma de Coleta,Escola Bloqueada';
        $row = '21/07/2026,BA,Itamari,Municipal,29123456,Escola Teste,Em Atividade,Urbana,Educacenso Web,Não';
        $tmp = tempnam(sys_get_temp_dir(), 'clio_acomp_comma');
        file_put_contents($tmp, $header."\n".$row."\n");

        try {
            $artifact = new \App\Models\Clio\ClioCampaignArtifact([
                'original_name' => 'Relatorio_Acomp_Coleta_1Etapa_21072026 (2).csv',
                'kind' => 'acomp_coleta_1etapa',
            ]);
            $result = (new \App\Services\Clio\Parse\AcompColeta1EtapaParser(new CsvReader))
                ->parse($tmp, $artifact);

            $this->assertSame(\App\Services\Clio\Parse\ParseResult::STATUS_OK, $result->status);
            $this->assertCount(1, $result->schools);
            $this->assertSame('29123456', $result->schools[0]['inep_code']);
        } finally {
            @unlink($tmp);
        }
    }
}
