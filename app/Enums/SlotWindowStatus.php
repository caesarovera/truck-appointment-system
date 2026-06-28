<?php

declare(strict_types=1);

namespace App\Enums;

enum SlotWindowStatus: string
{
    case OPEN = 'OPEN';
    case CLOSED = 'CLOSED';
}
