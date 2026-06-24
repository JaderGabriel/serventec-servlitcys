<?php

namespace Tests\Unit;

use App\Models\AdminSyncTask;
use App\Models\AnalyticsReportExport;
use App\Models\CadunicoMunicipioSnapshot;
use App\Models\MunicipalTransferSnapshot;
use App\Models\User;
use App\Support\Dashboard\Presenters\FundebTabPresenter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EloquentModelScopesTest extends TestCase
{
    #[Test]
    public function municipal_transfer_snapshot_scopes_build_expected_sql(): void
    {
        $query = MunicipalTransferSnapshot::query()
            ->forIbge('2927408')
            ->forYear(2024)
            ->forFonte('tesouro')
            ->forPrograma('fundeb');

        $sql = $query->toSql();

        $this->assertStringContainsString('ibge_municipio', $sql);
        $this->assertStringContainsString('ano', $sql);
        $this->assertStringContainsString('fonte', $sql);
        $this->assertStringContainsString('programa_id', $sql);
    }

    #[Test]
    public function cadunico_municipio_snapshot_scopes_build_expected_sql(): void
    {
        $query = CadunicoMunicipioSnapshot::query()
            ->forIbge('2927408')
            ->forReferenceYear(2024)
            ->betweenReferenceYears(2022, 2024);

        $sql = $query->toSql();

        $this->assertStringContainsString('ibge_municipio', $sql);
        $this->assertStringContainsString('ano_referencia', $sql);
    }

    #[Test]
    public function admin_sync_task_visible_to_user_scope_for_non_admin(): void
    {
        $user = new User(['id' => 7]);
        $sql = AdminSyncTask::query()->visibleToUser($user)->toSql();

        $this->assertStringContainsString('queued_by', $sql);
    }

    #[Test]
    public function analytics_report_export_visible_to_user_scope_for_non_admin(): void
    {
        $user = new User(['id' => 3]);
        $sql = AnalyticsReportExport::query()->visibleToUser($user)->toSql();

        $this->assertStringContainsString('user_id', $sql);
    }

    #[Test]
    public function fundeb_tab_presenter_extracts_projection_flags(): void
    {
        $presented = FundebTabPresenter::present([
            'city_name' => 'Salvador',
            'year_label' => '2024',
            'resource_projection' => [
                'available' => true,
                'distribuicao_legal' => ['itens' => [['label' => 'A']]],
            ],
            'complementacao_informe' => ['blocos' => []],
        ]);

        $this->assertTrue($presented['projAvailable']);
        $this->assertCount(1, $presented['distItens']);
        $this->assertStringContainsString('Salvador', (string) $presented['fundebMeta']);
    }
}
