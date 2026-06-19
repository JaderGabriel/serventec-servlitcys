<?php

namespace App\Support\Notifications;

final class NotificationKinds
{
    public const ACCOUNT = 'account';

    public const PDF_EXPORT = 'pdf_export';

    public const ADMIN_SYNC = 'admin_sync';

    public const ANALYTICS = 'analytics';

    public const OPERATIONS = 'operations';

    public const PUBLIC_DATA = 'public_data';

    public const CONNECTION = 'connection';

    public const SECURITY = 'security';

    public static function label(?string $kind): ?string
    {
        return match ($kind) {
            self::ACCOUNT => __('Conta'),
            self::PDF_EXPORT => __('PDF'),
            self::ADMIN_SYNC => __('Sincronização'),
            self::ANALYTICS => __('Painel analítico'),
            self::OPERATIONS => __('Operações'),
            self::PUBLIC_DATA => __('Dados públicos'),
            self::CONNECTION => __('Conexão i-Educar'),
            self::SECURITY => __('Segurança'),
            default => $kind !== null && $kind !== '' ? $kind : null,
        };
    }
}
