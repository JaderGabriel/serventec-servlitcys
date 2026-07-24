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
        $this->assertSame(EtapaLabelOrder::SEGMENT_NSA, $order->segment('Não se aplica'));
        $this->assertStringContainsString('AEE', $order->displayLabel('Não se aplica'));
        $this->assertLessThan(
            $order->segmentOrder(EtapaLabelOrder::SEGMENT_EJA),
            $order->segmentOrder(EtapaLabelOrder::SEGMENT_SERIADA),
        );
        $this->assertLessThan(
            $order->segmentOrder(EtapaLabelOrder::SEGMENT_NSA),
            $order->segmentOrder(EtapaLabelOrder::SEGMENT_COMPLEMENTAR),
        );
    }

    #[Test]
    public function carga_horaria_usa_faixas_pedagogicas(): void
    {
        $agg = new RelationCsvAggregator;

        $integral = $agg->cargaHorariaBandMeta(40.0);
        $this->assertSame('integral', $integral['key']);
        $this->assertSame('≥ 35 h', $integral['short']);

        $rebucketed = $agg->rebucketCargaCounts([
            '20 h/semana' => 10,
            '22 h/semana' => 4,
            '40 h/semana' => 3,
            'Não informado' => 2,
            '8 h/semana' => 1,
        ]);

        $this->assertGreaterThan(0, $rebucketed['by_ch_band']['20–24 h — parcial típica'] ?? 0);
        $this->assertSame(3, $rebucketed['by_ch_band']['≥ 35 h — tempo integral'] ?? 0);
        $this->assertSame(2, $rebucketed['by_ch_band']['Não informado'] ?? 0);
        $this->assertArrayHasKey('20 h/semana', $rebucketed['by_ch_exact']);
        $this->assertArrayHasKey('40 h/semana', $rebucketed['by_ch_exact']);

        $bars = $agg->enrichCargaBars($agg->toBars($rebucketed['by_ch_band'], 8));
        $byBand = collect($bars)->keyBy('band');
        $this->assertTrue($byBand->has('parcial'));
        $this->assertTrue($byBand->has('integral'));
        $this->assertTrue($byBand->has('reduzida'));
        $this->assertSame(14, (int) ($byBand['parcial']['count'] ?? 0));
        $shorts = array_column($bars, 'short');
        $this->assertContains('20–24 h', $shorts);
        $this->assertContains('≥ 35 h', $shorts);
    }

    #[Test]
    public function carga_horaria_detecta_coluna_correcta_e_parseia_formatos(): void
    {
        $agg = new RelationCsvAggregator;
        $score = new ReflectionMethod(RelationCsvAggregator::class, 'scoreCargaHorariaHeader');
        $score->setAccessible(true);
        $parse = new ReflectionMethod(RelationCsvAggregator::class, 'parseCargaHoraria');
        $parse->setAccessible(true);
        $find = new ReflectionMethod(RelationCsvAggregator::class, 'findCargaHorariaHeader');
        $find->setAccessible(true);

        $this->assertGreaterThan(
            $score->invoke($agg, 'Duração do curso'),
            $score->invoke($agg, 'Carga horária semanal'),
        );
        $this->assertSame(0, $score->invoke($agg, 'Duração do curso'));
        $this->assertSame(0, $score->invoke($agg, 'Duração'));
        $this->assertSame(
            'Carga horária semanal',
            $find->invoke($agg, ['Turno', 'Duração do curso', 'Carga horária semanal', 'Código da turma']),
        );

        $this->assertSame(20.0, $parse->invoke($agg, '20'));
        $this->assertSame(20.0, $parse->invoke($agg, '20h'));
        $this->assertSame(20.0, $parse->invoke($agg, '20 horas'));
        $this->assertSame(20.0, $parse->invoke($agg, '20:00'));
        $this->assertSame(2.0, $parse->invoke($agg, '120 min'));
        $this->assertSame(20.0, $parse->invoke($agg, '1200')); // minutos implícitos
        $this->assertNull($parse->invoke($agg, ''));
        $this->assertNull($parse->invoke($agg, 'Integral'));
    }

    #[Test]
    public function estima_carga_horaria_a_partir_da_grade_do_turno(): void
    {
        $agg = new RelationCsvAggregator;
        $turno = 'Segunda-feira - 08:00 às 12:00 | Terça-feira - 08:00 às 12:00 | Quarta-feira - 08:00 às 12:00 | Quinta-feira - 08:00 às 12:00 | Sexta-feira - 08:00 às 12:00';
        $this->assertSame(20.0, $agg->estimateWeeklyHoursFromTurno($turno));
        $this->assertNull($agg->estimateWeeklyHoursFromTurno('Manhã'));
        $this->assertNull($agg->estimateWeeklyHoursFromTurno(''));
    }

    #[Test]
    public function cor_raca_nao_declarado_conta_como_without_cor(): void
    {
        $agg = new RelationCsvAggregator;
        $this->assertTrue($agg->isUndeclaredCorRaca(''));
        $this->assertTrue($agg->isUndeclaredCorRaca('Não declarado'));
        $this->assertTrue($agg->isUndeclaredCorRaca('nao informado'));
        $this->assertFalse($agg->isUndeclaredCorRaca('Parda'));

        $this->assertSame(21, $agg->undeclaredCorCountFromAggregates([
            'without_cor' => 0,
            'by_cor_raca' => ['Parda' => 100, 'Não declarado' => 21],
        ]));
        $this->assertSame(5, $agg->undeclaredCorCountFromAggregates([
            'without_cor' => 5,
            'by_cor_raca' => ['Não declarado' => 5],
        ]));
    }

    #[Test]
    public function turno_abreviado_com_dias_e_tom(): void
    {
        $agg = new RelationCsvAggregator;
        $meta = $agg->turnoDisplayMeta('Manhã — segunda a sexta');
        $this->assertSame('Manhã', $meta['label']);
        $this->assertSame('amber', $meta['tone']);
        $this->assertSame(['seg', 'ter', 'qua', 'qui', 'sex'], $meta['days']);
        $this->assertFalse($meta['is_other']);
    }

    #[Test]
    public function turno_infere_periodo_por_horario_e_detalha_outros(): void
    {
        $agg = new RelationCsvAggregator;

        $manha = $agg->turnoDisplayMeta('07:00 às 11:30');
        $this->assertSame('Manhã', $manha['label']);
        $this->assertSame('manha', $manha['bucket']);

        $tarde = $agg->turnoDisplayMeta('13h às 17h');
        $this->assertSame('Tarde', $tarde['label']);

        $outros = $agg->turnoDisplayMeta('Plantão sob demanda');
        $this->assertSame('Outros', $outros['label']);
        $this->assertTrue($outros['is_other']);

        $rebucketed = $agg->rebucketTurnoCounts([
            'Manhã' => 10,
            'Plantão sob demanda' => 3,
            '07:00 às 11:30' => 2,
        ]);
        $this->assertSame(12, $rebucketed['by_turno']['Manhã'] ?? 0);
        $this->assertSame(3, $rebucketed['by_turno']['Outros'] ?? 0);
        $this->assertArrayHasKey('Plantão sob demanda', $rebucketed['by_turno_outros']);

        $bars = $agg->enrichTurnoBars(
            $agg->toBars($rebucketed['by_turno'], 8),
            $rebucketed['by_turno_outros'],
        );
        $outrosBar = collect($bars)->firstWhere('is_other', true);
        $this->assertNotNull($outrosBar);
        $this->assertNotEmpty($outrosBar['details']);
    }
}
