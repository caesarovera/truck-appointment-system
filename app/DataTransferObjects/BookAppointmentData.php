<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Enums\MoveType;
use Spatie\LaravelData\Data;

/**
 * Input booking yang sudah tervalidasi & bertipe kuat. Sengaja TIDAK memuat
 * company_id — itu diambil dari user yang login (jangan percaya klien soal
 * kepemilikan company).
 */
final class BookAppointmentData extends Data
{
    public function __construct(
        public int $slotWindowId,
        public int $truckId,
        public int $driverId,
        public MoveType $moveType,
        public string $containerNo,
        public ?string $isoType = null,
        public ?int $size = null,
    ) {}
}
