<?php

namespace App\Support\City;

use App\Models\City;
use App\Support\Contact\ContactChannels;

/**
 * Contacto de referência do município (gestor / ponto focal) para painéis operacionais.
 */
final class CityReferenceContact
{
    /**
     * @return array<string, mixed>
     */
    public static function from(?City $city): array
    {
        if ($city === null) {
            return ContactChannels::from();
        }

        return ContactChannels::from(
            $city->contact_name,
            $city->contact_phone,
            $city->contact_whatsapp,
            $city->contact_email,
        );
    }
}
