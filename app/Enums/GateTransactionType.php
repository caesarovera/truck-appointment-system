<?php

declare(strict_types=1);

namespace App\Enums;

enum GateTransactionType: string
{
    case IN = 'IN';
    case OUT = 'OUT';
}
