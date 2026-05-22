<?php

namespace App\Services\Ieducar;

use App\Services\CityDataConnection;

/**
 * Alias de compatibilidade para código e workers com referência antiga ao serviço municipal.
 *
 * @deprecated Use {@see CityDataConnection}.
 */
class IeducarCityDataService extends CityDataConnection
{
}
