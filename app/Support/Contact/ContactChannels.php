<?php

namespace App\Support\Contact;

use App\Models\User;

/**
 * Canais de contacto (telefone, WhatsApp, e-mail) com links prontos para UI.
 *
 * @phpstan-type ContactPayload array{
 *   available: bool,
 *   name: ?string,
 *   phone: ?string,
 *   whatsapp: ?string,
 *   email: ?string,
 *   phone_href: ?string,
 *   whatsapp_href: ?string,
 *   email_href: ?string
 * }
 */
final class ContactChannels
{
    /**
     * @return ContactPayload
     */
    public static function from(
        ?string $name = null,
        ?string $phone = null,
        ?string $whatsapp = null,
        ?string $email = null,
    ): array {
        $name = self::cleanText($name);
        $phone = self::cleanText($phone);
        $whatsapp = self::cleanText($whatsapp);
        $email = self::cleanEmail($email);

        $phoneHref = self::telHref($phone);
        $whatsappHref = self::whatsappHref($whatsapp);
        $emailHref = $email !== null ? 'mailto:'.$email : null;

        $available = $name !== null
            || $phoneHref !== null
            || $whatsappHref !== null
            || $emailHref !== null;

        return [
            'available' => $available,
            'name' => $name,
            'phone' => $phone,
            'whatsapp' => $whatsapp,
            'email' => $email,
            'phone_href' => $phoneHref,
            'whatsapp_href' => $whatsappHref,
            'email_href' => $emailHref,
        ];
    }

    /**
     * @return ContactPayload
     */
    public static function fromUser(?User $user): array
    {
        if ($user === null) {
            return self::empty();
        }

        return self::from(null, $user->phone, $user->whatsapp, $user->email);
    }

    /**
     * @return ContactPayload
     */
    private static function empty(): array
    {
        return [
            'available' => false,
            'name' => null,
            'phone' => null,
            'whatsapp' => null,
            'email' => null,
            'phone_href' => null,
            'whatsapp_href' => null,
            'email_href' => null,
        ];
    }

    private static function cleanText(?string $value): ?string
    {
        $v = trim((string) $value);

        return $v !== '' ? $v : null;
    }

    private static function cleanEmail(?string $value): ?string
    {
        $v = strtolower(trim((string) $value));
        if ($v === '' || ! filter_var($v, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $v;
    }

    public static function telHref(?string $phone): ?string
    {
        $digits = self::digitsOnly($phone);
        if ($digits === null) {
            return null;
        }

        return 'tel:+'.$digits;
    }

    public static function whatsappHref(?string $whatsapp): ?string
    {
        $digits = self::digitsOnly($whatsapp);
        if ($digits === null) {
            return null;
        }

        return 'https://wa.me/'.$digits;
    }

    private static function digitsOnly(?string $value): ?string
    {
        $raw = preg_replace('/\D+/', '', (string) $value);
        if ($raw === null || $raw === '') {
            return null;
        }

        if (strlen($raw) >= 12 && str_starts_with($raw, '55')) {
            return $raw;
        }

        if (strlen($raw) === 10 || strlen($raw) === 11) {
            return '55'.$raw;
        }

        if (strlen($raw) >= 8) {
            return $raw;
        }

        return null;
    }
}
