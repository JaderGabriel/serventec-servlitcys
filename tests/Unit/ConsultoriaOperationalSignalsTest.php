<?php

namespace Tests\Unit;

use App\Models\City;
use App\Support\Dashboard\IeducarFilterState;
use App\Support\Ieducar\ConsultoriaOperationalSignals;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ConsultoriaOperationalSignalsTest extends TestCase
{
    #[Test]
    public function normalize_dimension_completes_fundeb_signal_for_hub(): void
    {
        $city = new City(['id' => 3, 'name' => 'Cidade Alfa', 'ibge_municipio' => '2910800']);
        $filters = new IeducarFilterState(ano_letivo: '2024', escola_id: null, curso_id: null, turno_id: null);

        $partial = [
            'id' => 'fundeb_vaaf_fonte_censo',
            'title' => 'FUNDEB — VAAF estimado com Censo INEP',
            'availability' => 'available',
            'has_issue' => true,
            'total' => 4200,
            'operational_note' => 'Nota operacional de teste.',
        ];

        $dim = ConsultoriaOperationalSignals::normalizeDimension($partial, 5000, $city, $filters);

        $this->assertTrue($dim['analyzed']);
        $this->assertTrue($dim['has_issue']);
        $this->assertSame('warning', $dim['status']);
        $this->assertSame(__('Pendência'), $dim['status_label']);
        $this->assertSame(4200, $dim['occurrences_total']);
        $this->assertSame(1, $dim['schools_count']);
        $this->assertSame('Nota operacional de teste.', $dim['operational_note']);
        $this->assertGreaterThan(0.0, (float) ($dim['perda_estimada_anual'] ?? 0));
    }

    #[Test]
    public function operational_meta_includes_fundeb_routines_for_admin_hub(): void
    {
        $meta = ConsultoriaOperationalSignals::operationalMeta();

        $this->assertArrayHasKey('fundeb_vaaf_fonte_censo', $meta);
        $this->assertArrayHasKey('fundeb_ibge_nome_divergente', $meta);
        $this->assertArrayHasKey('rede_vagas_ociosas', $meta);
    }

    #[Test]
    public function normalize_dimension_preserves_already_complete_rows(): void
    {
        $row = [
            'id' => 'escola_sem_inep',
            'analyzed' => true,
            'status_label' => __('Sem pendência'),
            'schools_count' => 0,
            'occurrences_total' => 0,
            'has_issue' => false,
        ];

        $this->assertSame($row, ConsultoriaOperationalSignals::normalizeDimension($row, 100));
    }
}
