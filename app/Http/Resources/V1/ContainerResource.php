<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Container;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Container */
final class ContainerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'container_no' => $this->container_no,
            'iso_type' => $this->iso_type,
            'size' => $this->size,
        ];
    }
}
