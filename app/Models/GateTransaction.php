<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GateTransactionType;
use Database\Factories\GateTransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $appointment_id
 * @property GateTransactionType $type
 * @property int $processed_by
 * @property Carbon $processed_at
 */
class GateTransaction extends Model
{
    /** @use HasFactory<GateTransactionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'appointment_id',
        'type',
        'processed_by',
        'processed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => GateTransactionType::class,
            'processed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Appointment, $this> */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
