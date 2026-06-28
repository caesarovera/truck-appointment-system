<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Appointment lifecycle — authoritative state machine (docs/BUSINESS-FLOW.md §2).
 * Transition rules live in the Actions; this enum only describes the graph.
 */
enum AppointmentStatus: string
{
    case BOOKED = 'BOOKED';
    case CONFIRMED = 'CONFIRMED';
    case ARRIVED = 'ARRIVED';
    case IN_PROGRESS = 'IN_PROGRESS';
    case COMPLETED = 'COMPLETED';
    case CANCELLED = 'CANCELLED';
    case NO_SHOW = 'NO_SHOW';

    /** Final states cannot transition further. */
    public function isFinal(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED, self::NO_SHOW], true);
    }

    /** Still holds quota in its slot window (counts against capacity). */
    public function holdsQuota(): bool
    {
        return in_array($this, [self::BOOKED, self::CONFIRMED, self::ARRIVED, self::IN_PROGRESS], true);
    }

    /** May still be cancelled by transporter (only before the truck arrives). */
    public function isCancellable(): bool
    {
        return in_array($this, [self::BOOKED, self::CONFIRMED], true);
    }

    /** May still be moved to another window (same precondition as cancel: before arrival). */
    public function isReschedulable(): bool
    {
        return $this->isCancellable();
    }

    /** Eligible to be swept to NO_SHOW (truck never arrived). Pre-arrival states only. */
    public function canMarkNoShow(): bool
    {
        return in_array($this, [self::BOOKED, self::CONFIRMED], true);
    }

    /** Eligible for gate-in (truck arriving at the gate). Only confirmed appointments. */
    public function canGateIn(): bool
    {
        return $this === self::CONFIRMED;
    }

    /** Eligible for gate-out (handling finished). Only appointments currently being processed. */
    public function canGateOut(): bool
    {
        return $this === self::IN_PROGRESS;
    }

    /** Whether $target is a legal next state from the current one. */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedNext(), true);
    }

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::BOOKED => [self::CONFIRMED, self::CANCELLED, self::NO_SHOW],
            self::CONFIRMED => [self::ARRIVED, self::CANCELLED, self::NO_SHOW],
            self::ARRIVED => [self::IN_PROGRESS],
            self::IN_PROGRESS => [self::COMPLETED],
            self::COMPLETED, self::CANCELLED, self::NO_SHOW => [],
        };
    }
}
