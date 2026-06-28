<?php

declare(strict_types=1);

namespace App\Events\Broadcasting;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Antrian gate satu terminal bergerak (gate-in / gate-out) → dorong ke channel
 * `gate.queue.{terminalId}` (CLAUDE.md realtime). Driver & gate officer memantau
 * status truk secara live (BUSINESS-FLOW §3.4).
 */
final class GateQueueUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $terminalId,
        public readonly int $appointmentId,
        public readonly string $bookingCode,
        public readonly string $status,
        public readonly string $gateEvent,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("gate.queue.{$this->terminalId}")];
    }

    public function broadcastAs(): string
    {
        return 'gate.queue.updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'appointment_id' => $this->appointmentId,
            'booking_code' => $this->bookingCode,
            'status' => $this->status,
            'gate_event' => $this->gateEvent,
        ];
    }
}
