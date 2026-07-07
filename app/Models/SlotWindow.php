<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SlotWindowStatus;
use Database\Factories\SlotWindowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $gate_id
 * @property Carbon $date
 * @property string $start_time
 * @property string $end_time
 * @property int $capacity
 * @property int $booked_count
 * @property SlotWindowStatus $status
 */
class SlotWindow extends Model
{
    /** @use HasFactory<SlotWindowFactory> */
    use HasFactory;

    /**
     * `booked_count` & `status` SENGAJA tidak fillable (ADR-0004): kuota hanya
     * boleh naik/turun lewat Action ber-lock (CLAUDE.md §JANGAN), status hanya
     * lewat Open/CloseSlotWindowAction. Ditulis via property assignment eksplisit.
     *
     * @var list<string>
     */
    protected $fillable = [
        'gate_id',
        'date',
        'start_time',
        'end_time',
        'capacity',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'capacity' => 'integer',
            'booked_count' => 'integer',
            'status' => SlotWindowStatus::class,
        ];
    }

    public function isOpen(): bool
    {
        return $this->status === SlotWindowStatus::OPEN;
    }

    public function hasCapacity(): bool
    {
        return $this->booked_count < $this->capacity;
    }

    public function remaining(): int
    {
        return max(0, $this->capacity - $this->booked_count);
    }

    /** @return BelongsTo<Gate, $this> */
    public function gate(): BelongsTo
    {
        return $this->belongsTo(Gate::class);
    }

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
