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
}
