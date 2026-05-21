<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AnalyticsReportExport;
use App\Models\City;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AnalyticsReportExportControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_pode_enfileirar_exportacao_pdf(): void
    {
        Queue::fake();

        $city = City::factory()->create([
            'is_active' => true,
            'db_host' => '127.0.0.1',
            'db_database' => 'ieducar',
            'db_username' => 'user',
            'db_password' => 'secret',
        ]);

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('dashboard.analytics.pdf.store'), [
            'city_id' => $city->id,
            'ano_letivo' => '2024',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('pdf_export_id');

        $this->assertDatabaseCount('analytics_report_exports', 1);

        Queue::assertPushed(\App\Jobs\GenerateAnalyticsReportPdfJob::class);
    }

    #[Test]
    public function municipal_nao_pode_exportar_pdf(): void
    {
        $city = City::factory()->create([
            'is_active' => true,
            'db_host' => '127.0.0.1',
            'db_database' => 'ieducar',
            'db_username' => 'user',
            'db_password' => 'secret',
        ]);

        $municipal = User::factory()->create([
            'role' => UserRole::Municipal,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $municipal->cities()->attach($city->id);

        $this->actingAs($municipal)->post(route('dashboard.analytics.pdf.store'), [
            'city_id' => $city->id,
            'ano_letivo' => '2024',
        ])->assertForbidden();

        $this->assertDatabaseCount('analytics_report_exports', 0);
    }

    #[Test]
    public function prune_mantem_apenas_os_exports_mais_recentes(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::User,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        config(['analytics.pdf_report.max_exports_per_user' => 2]);

        foreach (range(1, 4) as $i) {
            AnalyticsReportExport::query()->create([
                'user_id' => $user->id,
                'city_id' => City::factory()->create(['is_active' => true])->id,
                'status' => 'pending',
                'filters' => ['ano_letivo' => '2024'],
                'file_disk' => 'local',
            ]);
        }

        $service = app(\App\Services\Analytics\AnalyticsReportExportService::class);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('pruneOldExports');
        $method->setAccessible(true);
        $method->invoke($service, $user);

        $remaining = AnalyticsReportExport::query()->where('user_id', $user->id)->orderBy('id')->pluck('id')->all();

        $this->assertCount(2, $remaining);
        $this->assertSame([3, 4], $remaining);
    }
}
