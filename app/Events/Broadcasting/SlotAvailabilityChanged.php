<?php

declare(strict_types=1);

namespace App\Events\Broadcasting;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Ketersediaan slot satu gate berubah (booking/cancel/reschedule/no-show) →
 * dorong sisa kuota live ke channel `slot.{gateId}` (CLAUDE.md realtime).
 * Payload sengaja datar (bukan model) supaya kontrak websocket stabil.
 */
final class SlotAvailabilityChanged implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /** @param array<int, array<string, mixed>> $windows */
    public function __construct(
        public readonly int $gateId,
        public readonly array $windows,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("slot.{$this->gateId}")];
    }

    public function broadcastAs(): string
    {
        return 'slot.availability.changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'gate_id' => $this->gateId,
            'windows' => $this->windows,
        ];
    }
}
