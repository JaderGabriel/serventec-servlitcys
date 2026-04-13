<?php

namespace App\Services;

use App\Models\MailSetting;
use Illuminate\Support\Facades\Config;

class MailConfigService
{
    /**
     * Aplica configurações SMTP da base de dados (se existirem) sobre a configuração em memória.
     */
    public function applyFromDatabase(): void
    {
        $row = MailSetting::query()->first();
        if (! $row || ! filled($row->smtp_host)) {
            return;
        }

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp', array_merge(
            config('mail.mailers.smtp', []),
            [
                'transport' => 'smtp',
                'host' => $row->smtp_host,
                'port' => $row->smtp_port ?: 587,
                'encryption' => $row->smtp_encryption ?: null,
                'username' => $row->smtp_username,
                'password' => $row->smtp_password,
            ]
        ));

        if (filled($row->mail_from_address)) {
            Config::set('mail.from.address', $row->mail_from_address);
        }
        if (filled($row->mail_from_name)) {
            Config::set('mail.from.name', $row->mail_from_name);
        }
    }
}
