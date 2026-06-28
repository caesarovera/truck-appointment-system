<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\GateEventGateway;
use App\Enums\GateTransactionType;
use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Dorong gate event ke TOS terminal (efek eksternal) — dipanggil lewat
 * listener event, di luar transaksi DB (CLAUDE.md hardening Queue).
 *
 * Idempoten: cek state appointment dulu sebelum push, jadi retry / event ganda
 * tidak menembak TOS dua kali. WithoutOverlapping per appointment.
 */
final class ProcessGateEventJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly int $appointmentId,
        public readonly GateTransactionType $type,
    ) {}

    public function uniqueId(): string
    {
        return "gate-event:{$this->appointmentId}:{$this->type->value}";
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [new WithoutOverlapping((string) $this->appointmentId)];
    }

    public function handle(GateEventGateway $tos): void
    {
        $appointment = Appointment::query()->find($this->appointmentId);

        if ($appointment === null) {
            return;
        }

        // Guard idempoten: hanya push bila gate_transaction yang sesuai memang
        // sudah tercatat (jaring anti push ganda saat retry / event kembar).
        $recorded = $appointment->gateTransactions()
            ->where('type', $this->type->value)
            ->exists();

        if (! $recorded) {
            return;
        }

        $tos->push($appointment, $this->type);
    }

    public function failed(Throwable $e): void
    {
        // Hook untuk alerting saat push TOS gagal permanen (di-wire saat observability slice).
    }
}
