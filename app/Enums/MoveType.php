<?php

declare(strict_types=1);

namespace App\Enums;

enum MoveType: string
{
    case DELIVERY = 'DELIVERY';   // ambil kontainer impor
    case RECEIVAL = 'RECEIVAL';   // drop kontainer ekspor
}
