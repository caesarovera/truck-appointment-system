<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\DataTransferObjects\Auth\IssuedToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sengaja TANPA pembungkus `data`: SPA (stores/auth.ts) membaca `token` & `user` di level atas.
 *
 * @property IssuedToken $resource
 */
final class LoginResource extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->resource->plainTextToken,
            'token_type' => 'Bearer',
            'user' => UserResource::make($this->resource->user),
        ];
    }
}
