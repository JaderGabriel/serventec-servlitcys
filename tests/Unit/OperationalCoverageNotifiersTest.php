<?php

namespace Tests\Unit;

use App\Contracts\Notifications\OperationalNotificationChannel;
use App\Enums\NotificationPriority;
use App\Services\Horizonte\HorizonteFortnightlyFeedNotifier;
use App\Services\Notifications\CadunicoOperationalNotifier;
use Illuminate\Support\Collection;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OperationalCoverageNotifiersTest extends TestCase
{
    #[Test]
    public function horizonte_skips_successful_phase_by_default(): void
    {
        config([
            'horizonte.fortnightly_feed.notify_phases' => true,
            'horizonte.fortnightly_feed.notify_phase_success' => false,
        ]);

        $channel = Mockery::mock(OperationalNotificationChannel::class);
        $channel->shouldReceive('isEnabled')->andReturn(true);
        $channel->shouldReceive('operationalRecipients')->never();
        $channel->shouldReceive('notifyOperational')->never();

        $notifier = new HorizonteFortnightlyFeedNotifier($channel);
        $notifier->phaseFinished('run-1', [
            'key' => 'procurement_sync',
            'success' => true,
            'message' => 'OK',
        ], 1, 10);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function horizonte_notifies_critical_failure_on_procurement(): void
    {
        config([
            'horizonte.fortnightly_feed.notify_phases' => true,
            'horizonte.fortnightly_feed.notify_phase_success' => false,
        ]);

        $channel = Mockery::mock(OperationalNotificationChannel::class);
        $channel->shouldReceive('isEnabled')->andReturn(true);
        $channel->shouldReceive('operationalRecipients')->once()->andReturn(new Collection([(object) ['id' => 1]]));
        $channel->shouldReceive('notifyOperational')
            ->once()
            ->withArgs(function ($recipients, array $payload): bool {
                return ($payload['dedupe_key'] ?? '') === 'horizonte:phase:run-2:procurement_sync'
                    && ($payload['priority'] ?? '') === NotificationPriority::Critical->value;
            });

        $notifier = new HorizonteFortnightlyFeedNotifier($channel);
        $notifier->phaseFinished('run-2', [
            'key' => 'procurement_sync',
            'success' => false,
            'message' => 'API key missing',
        ], 3, 10);
    }

    #[Test]
    public function cadunico_beneficios_notifies_on_failure(): void
    {
        config([
            'ieducar.cadunico.notify_operational' => true,
            'ieducar.cadunico.notify_success' => false,
        ]);

        $channel = Mockery::mock(OperationalNotificationChannel::class);
        $channel->shouldReceive('isEnabled')->andReturn(true);
        $channel->shouldReceive('operationalRecipients')->once()->andReturn(new Collection([(object) ['id' => 1]]));
        $channel->shouldReceive('notifyOperational')
            ->once()
            ->withArgs(function ($recipients, array $payload): bool {
                return str_starts_with((string) ($payload['dedupe_key'] ?? ''), 'cadunico:beneficios:')
                    && ($payload['priority'] ?? '') === NotificationPriority::Critical->value;
            });

        $notifier = new CadunicoOperationalNotifier($channel);
        $notifier->beneficiosPortalFinished([
            'success' => false,
            'message' => 'Portal offline',
            'cities' => 0,
            'dry_run' => false,
        ]);
    }
}
