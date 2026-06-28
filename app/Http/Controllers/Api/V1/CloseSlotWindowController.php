<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\CloseSlotWindowAction;
use App\Http\Requests\V1\CloseSlotWindowRequest;
use App\Http\Resources\V1\SlotWindowResource;
use App\Models\SlotWindow;

final class CloseSlotWindowController
{
    public function __invoke(
        CloseSlotWindowRequest $request,
        SlotWindow $slotWindow,
        CloseSlotWindowAction $action,
    ): SlotWindowResource {
        // Otorisasi: slot.manage (FormRequest::authorize).
        $closed = $action->execute($slotWindow);

        return SlotWindowResource::make($closed);
    }
}
