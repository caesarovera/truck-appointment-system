<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\MoveType;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $company_id
 * @property int $truck_id
 * @property int $driver_id
 * @property int $slot_window_id
 * @property MoveType $move_type
 * @property AppointmentStatus $status
 * @property int $version
 * @property string $booking_code
 */
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * `status`, `version`, `company_id` SENGAJA tidak fillable (ADR-0004):
     * status & version hanya boleh berubah lewat Action ber-lock (state machine,
     * BUSINESS-FLOW §2), company_id ditentukan dari actor — bukan input klien.
     * Repository/seeder menulisnya via property assignment / forceFill eksplisit.
     *
     * @var list<string>
     */
    protected $fillable = [
        'truck_id',
        'driver_id',
        'slot_window_id',
        'move_type',
        'booking_code',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'move_type' => MoveType::class,
            'status' => AppointmentStatus::class,
            'version' => 'integer',
        ];
    }

    /** Audit trail (docs/BUSINESS-FLOW.md §3.7) — status changes are the source of truth. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'slot_window_id', 'version'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('appointment');
    }

    public function isGatedIn(): bool
    {
        return in_array($this->status, [AppointmentStatus::ARRIVED, AppointmentStatus::IN_PROGRESS], true);
    }

    /** @return BelongsTo<TransportCompany, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(TransportCompany::class, 'company_id');
    }

    /** @return BelongsTo<Truck, $this> */
    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    /** @return BelongsTo<User, $this> */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /** @return BelongsTo<SlotWindow, $this> */
    public function slotWindow(): BelongsTo
    {
        return $this->belongsTo(SlotWindow::class);
    }

    /** @return HasMany<Container, $this> */
    public function containers(): HasMany
    {
        return $this->hasMany(Container::class);
    }

    /** @return HasMany<GateTransaction, $this> */
    public function gateTransactions(): HasMany
    {
        return $this->hasMany(GateTransaction::class);
    }

    /** @return HasOne<GateTransaction, $this> */
    public function gateIn(): HasOne
    {
        return $this->hasOne(GateTransaction::class)->where('type', 'IN');
    }

    /** @return HasOne<GateTransaction, $this> */
    public function gateOut(): HasOne
    {
        return $this->hasOne(GateTransaction::class)->where('type', 'OUT');
    }
}
