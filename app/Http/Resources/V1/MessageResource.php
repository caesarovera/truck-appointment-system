<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property string $resource */
final class MessageResource extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /** @return array<string, string> */
    public function toArray(Request $request): array
    {
        return ['message' => $this->resource];
    }
}
