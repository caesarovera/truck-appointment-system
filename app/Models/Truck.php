<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TruckStatus;
use Database\Factories\TruckFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $company_id
 * @property string $plate_no
 * @property TruckStatus $status
 */
class Truck extends Model
{
    /** @use HasFactory<TruckFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['company_id', 'plate_no', 'status'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => TruckStatus::class];
    }

    /** @return BelongsTo<TransportCompany, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(TransportCompany::class, 'company_id');
    }
}
