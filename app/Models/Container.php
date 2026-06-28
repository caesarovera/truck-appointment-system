<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ContainerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $appointment_id
 * @property int|null $slot_window_id
 * @property string $container_no
 * @property string|null $iso_type
 * @property int|null $size
 */
class Container extends Model
{
    /** @use HasFactory<ContainerFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'appointment_id',
        'slot_window_id',
        'container_no',
        'iso_type',
        'size',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
