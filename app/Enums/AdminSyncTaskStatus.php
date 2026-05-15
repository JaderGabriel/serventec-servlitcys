<?php

namespace App\Enums;

enum AdminSyncTaskStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pendente'),
            self::Processing => __('Em execução'),
            self::Completed => __('Concluída'),
            self::Failed => __('Falhou'),
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
            self::Processing => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
            self::Completed => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
            self::Failed => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
        };
    }
}
