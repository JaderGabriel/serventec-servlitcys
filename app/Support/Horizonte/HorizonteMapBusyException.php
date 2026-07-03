<?php

namespace App\Support\Horizonte;

use RuntimeException;

/** Mapa Horizonte em construção — pedido deve aguardar ou repetir (503). */
final class HorizonteMapBusyException extends RuntimeException
{
}
