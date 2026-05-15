<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case User = 'user';
    case Municipal = 'municipal';

    public function label(): string
    {
        return match ($this) {
            self::Admin => __('Administrador'),
            self::User => __('Utilizador'),
            self::Municipal => __('Municipal'),
        };
    }

    /**
     * @return list<self>
     */
    public static function creatableBy(self $actor): array
    {
        return match ($actor) {
            self::Admin => [self::Admin, self::User, self::Municipal],
            self::User => [self::User],
            self::Municipal => [self::Municipal],
        };
    }

    /**
     * Valores de role permitidos em formulários (criação ou edição).
     *
     * @return list<string>
     */
    public static function assignableValuesFor(self $actorRole, ?self $targetRole = null): array
    {
        $values = array_map(static fn (self $role) => $role->value, self::creatableBy($actorRole));

        if (
            $actorRole === self::Admin
            && $targetRole === self::Admin
            && ! in_array(self::Admin->value, $values, true)
        ) {
            $values[] = self::Admin->value;
        }

        return array_values(array_unique($values));
    }

    /**
     * @return list<self>
     */
    public static function assignableFor(self $actorRole, ?self $targetRole = null): array
    {
        $values = self::assignableValuesFor($actorRole, $targetRole);

        return array_values(array_filter(
            self::cases(),
            static fn (self $role) => in_array($role->value, $values, true)
        ));
    }
}
