<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\AppointmentRepositoryInterface;
use App\Http\Requests\V1\GateQueueRequest;
use App\Http\Resources\V1\AppointmentResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

final class GateQueueController
{
    public function __construct(private readonly AppointmentRepositoryInterface $appointments) {}

    public function __invoke(GateQueueRequest $request): AnonymousResourceCollection
    {
        // Otorisasi: gate.process (FormRequest::authorize).
        $user = $request->user();
        abort_if($user === null, Response::HTTP_UNAUTHORIZED);

        // Antrian ber-scope terminal → officer tanpa terminal ditolak.
        $terminalId = $user->terminal_id;
        abort_if($terminalId === null, Response::HTTP_FORBIDDEN);

        return AppointmentResource::collection(
            $this->appointments->queueForTerminal($terminalId, $request->requestedDate()),
        );
    }
}
