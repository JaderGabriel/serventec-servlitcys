<?php

namespace Tests\Unit\Clio;

use App\Services\Clio\Analysis\EtapaLabelOrder;
use App\Services\Clio\Analysis\RelationCsvAggregator;
use App\Services\Clio\Export\CampaignActiveCensusMatrixBuilder;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

final class EtapaLabelOrderAndCensusStageTest extends TestCase
{
    #[Test]
    public function ordena_anos_do_fundamental_em_sequencia(): void
    {
        $order = new EtapaLabelOrder;
        $labels = [
            'Ensino Fundamental de 9 anos - 9º Ano',
            'Ensino Fundamental de 9 anos - 1º Ano',
            'Ensino Fundamental de 9 anos - 6º Ano',
            'Ensino Fundamental de 9 anos - 2º Ano',
        ];
        usort($labels, fn (string $a, string $b): int => $order->compare($a, $b));

        $this->assertSame('Ensino Fundamental de 9 anos - 1º Ano', $labels[0]);
        $this->assertSame('Ensino Fundamental de 9 anos - 2º Ano', $labels[1]);
        $this->assertSame('Ensino Fundamental de 9 anos - 6º Ano', $labels[2]);
        $this->assertSame('Ensino Fundamental de 9 anos - 9º Ano', $labels[3]);
    }

    #[Test]
    public function ordena_etapas_na_sequencia_pedagogica_completa(): void
    {
        $order = new EtapaLabelOrder;
        $labels = [
            'EJA - Ensino fundamental - anos iniciais (1º segmento)',
            'Ensino fundamental de 9 anos - 6º Ano',
            'Educação infantil - pré-escola (4 e 5 anos)',
            'Não se aplica',
            'Ensino fundamental de 9 anos - 1º Ano',
            'Educação infantil - creche (0 a 3 anos)',
            'Ensino fundamental de 9 anos - 9º Ano',
        ];
        usort($labels, fn (string $a, string $b): int => $order->compare($a, $b));

        $this->assertSame('Educação infantil - creche (0 a 3 anos)', $labels[0]);
        $this->assertSame('Educação infantil - pré-escola (4 e 5 anos)', $labels[1]);
        $this->assertSame('Ensino fundamental de 9 anos - 1º Ano', $labels[2]);
        $this->assertSame('Ensino fundamental de 9 anos - 6º Ano', $labels[3]);
        $this->assertSame('Ensino fundamental de 9 anos - 9º Ano', $labels[4]);
        $this->assertSame('EJA - Ensino fundamental - anos iniciais (1º segmento)', $labels[5]);
        $this->assertSame('Não se aplica', $labels[6]);
    }

    #[Test]
    public function classifica_fundamental_de_9_anos_sem_confundir_com_digito_9(): void
    {
        $method = new ReflectionMethod(CampaignActiveCensusMatrixBuilder::class, 'classifyStage');
        $builder = new CampaignActiveCensusMatrixBuilder(app(\App\Services\Clio\Parse\CsvReader::class), new RelationCsvAggregator);

        $this->assertSame(
            'anos_iniciais',
            $method->invoke($builder, 'Ensino Fundamental de 9 anos - 1º Ano', '', ''),
        );
        $this->assertSame(
            'anos_iniciais',
            $method->invoke($builder, 'Ensino Fundamental de 9 anos - 5º Ano', '', ''),
        );
        $this->assertSame(
            'anos_finais',
            $method->invoke($builder, 'Ensino Fundamental de 9 anos - 6º Ano', '', ''),
        );
        $this->assertSame(
            'anos_finais',
            $method->invoke($builder, 'Ensino Fundamental de 9 anos - 9º Ano', '', ''),
        );
        $this->assertSame(
            'anos_iniciais',
            $method->invoke($builder, '', '', 'Anos iniciais'),
        );
        $this->assertSame(
            'anos_finais',
            $method->invoke($builder, '', '', 'Anos finais'),
        );
    }

    #[Test]
    public function segmenta_eja_profissional_especial_e_seriada(): void
    {
        $order = new EtapaLabelOrder;
        $this->assertSame(EtapaLabelOrder::SEGMENT_SERIADA, $order->segment('Ensino Fundamental de 9 anos - 5º Ano'));
        $this->assertSame(EtapaLabelOrder::SEGMENT_EJA, $order->segment('EJA - Ensino fundamental - anos iniciais'));
        $this->assertSame(EtapaLabelOrder::SEGMENT_PROFISSIONAL, $order->segment('Educação profissional técnica de nível médio'));
        $this->assertSame(EtapaLabelOrder::SEGMENT_ESPECIAL, $order->segment('Atendimento Educacional Especializado'));
        $this->assertSame(EtapaLabelOrder::SEGMENT_COMPLEMENTAR, $order->segment('Atividade complementar'));
        $this->assertLessThan(
            $order->segmentOrder(EtapaLabelOrder::SEGMENT_EJA),
            $order->segmentOrder(EtapaLabelOrder::SEGMENT_SERIADA),
        );
    }

    #[Test]
    public function carga_horaria_usa_valores_exactos_ordenados(): void
    {
        $agg = new RelationCsvAggregator;
        $bars = $agg->enrichCargaBars([
            ['label' => '40 h/semana', 'count' => 2, 'pct' => 20],
            ['label' => '20 h/semana', 'count' => 6, 'pct' => 60],
            ['label' => 'Não informado', 'count' => 2, 'pct' => 20],
        ]);
        $this->assertSame('20 h', $bars[0]['short']);
        $this->assertSame('40 h', $bars[1]['short']);
        $this->assertNull($bars[2]['hours']);
    }

    #[Test]
    public function turno_abreviado_com_dias_e_tom(): void
    {
        $agg = new RelationCsvAggregator;
        $meta = $agg->turnoDisplayMeta('Manhã — segunda a sexta');
        $this->assertSame('Manhã', $meta['label']);
        $this->assertSame('amber', $meta['tone']);
        $this->assertSame(['seg', 'ter', 'qua', 'qui', 'sex'], $meta['days']);
    }
}
