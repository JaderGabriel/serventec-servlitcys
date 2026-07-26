<?php

namespace Tests\Unit;

use App\Contracts\Notifications\OperationalNotificationChannel;
use App\Enums\NotificationPriority;
use App\Services\Notifications\ModuleMonitorOperationalNotifier;
use App\Services\Notifications\ScheduleFailureNotifier;
use Illuminate\Support\Collection;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ModuleMonitorAndScheduleNotifiersTest extends TestCase
{
    #[Test]
    public function after_collect_notifies_failed_modules(): void
    {
        config([
            'module_monitor.notify.enabled' => true,
            'module_monitor.notify.on_critical' => true,
            'module_monitor.notify.on_degraded' => false,
        ]);

        $channel = Mockery::mock(OperationalNotificationChannel::class);
        $channel->shouldReceive('isEnabled')->andReturn(true);
        $channel->shouldReceive('operationalRecipients')->once()->andReturn(new Collection([(object) ['id' => 1]]));
        $channel->shouldReceive('notifyOperational')
            ->once()
            ->withArgs(function ($recipients, array $payload): bool {
                return ($payload['dedupe_key'] ?? '') === 'ops:module_monitor:failed'
                    && ($payload['priority'] ?? '') === NotificationPriority::Critical->value;
            });

        $notifier = new ModuleMonitorOperationalNotifier($channel);
        $notifier->afterCollect([
            'modules' => [
                'geo' => ['signal' => 'failed', 'detail' => 'sync antigo'],
                'pdf' => ['signal' => 'operational', 'detail' => 'ok'],
            ],
        ]);
    }

    #[Test]
    public function after_collect_skips_when_all_healthy(): void
    {
        config([
            'module_monitor.notify.enabled' => true,
            'module_monitor.notify.on_critical' => true,
        ]);

        $channel = Mockery::mock(OperationalNotificationChannel::class);
        $channel->shouldReceive('isEnabled')->andReturn(true);
        $channel->shouldReceive('operationalRecipients')->once()->andReturn(new Collection([(object) ['id' => 1]]));
        $channel->shouldReceive('notifyOperational')->never();

        $notifier = new ModuleMonitorOperationalNotifier($channel);
        $notifier->afterCollect([
            'modules' => [
                'geo' => ['signal' => 'operational', 'detail' => 'ok'],
            ],
        ]);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function schedule_failure_notifier_uses_critical_priority(): void
    {
        config(['notifications.schedule_failures.enabled' => true]);

        $channel = Mockery::mock(OperationalNotificationChannel::class);
        $channel->shouldReceive('isEnabled')->andReturn(true);
        $channel->shouldReceive('operationalRecipients')->once()->andReturn(new Collection([(object) ['id' => 1]]));
        $channel->shouldReceive('notifyOperational')
            ->once()
            ->withArgs(function ($recipients, array $payload): bool {
                return ($payload['dedupe_key'] ?? '') === 'ops:schedule_failed:module-monitor-collect'
                    && ($payload['priority'] ?? '') === NotificationPriority::Critical->value;
            });

        (new ScheduleFailureNotifier($channel))->notify('module-monitor-collect');
    }
}
