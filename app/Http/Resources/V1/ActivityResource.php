<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Activitylog\Models\Activity;

/**
 * Satu entri audit trail (BUSINESS-FLOW §3.7).
 *
 * @mixin Activity
 */
final class ActivityResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        // `properties` nullable menurut Spatie — entri tanpa properti tetap sah
        // (mis. log kustom), jadi jangan asumsikan selalu ada.
        /** @var array<string, mixed> $props */
        $props = $this->properties?->toArray() ?? [];
        $causer = $this->causer;

        return [
            'id' => $this->id,
            'event' => $this->event,
            // `old` kosong pada entri `created` — belum ada keadaan sebelumnya.
            'changes' => [
                'old' => $props['old'] ?? [],
                'new' => $props['attributes'] ?? [],
            ],
            // NULL = tindakan sistem (mis. NoShowSweepJob), bukan orang. Perbedaan
            // itu bagian dari makna audit trail, jadi ia diekspos apa adanya.
            'causer' => $causer instanceof User ? [
                'id' => $causer->id,
                'name' => $causer->name,
            ] : null,
            'created_at' => $this->created_at,
        ];
    }
}
