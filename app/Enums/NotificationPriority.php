<?php

namespace App\Enums;

enum NotificationPriority: string
{
    case Normal = 'normal';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Critical => __('Crítico'),
            self::High => __('Importante'),
            self::Normal => __('Informativo'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Critical => 'error',
            self::High => 'warning',
            self::Normal => 'info',
        };
    }

    public function isCritical(): bool
    {
        return $this === self::Critical;
    }
}
