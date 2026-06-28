<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;

it('marks terminal states as final', function (): void {
    expect(AppointmentStatus::COMPLETED->isFinal())->toBeTrue()
        ->and(AppointmentStatus::CANCELLED->isFinal())->toBeTrue()
        ->and(AppointmentStatus::NO_SHOW->isFinal())->toBeTrue()
        ->and(AppointmentStatus::BOOKED->isFinal())->toBeFalse();
});

it('counts only live states as holding quota', function (): void {
    expect(AppointmentStatus::BOOKED->holdsQuota())->toBeTrue()
        ->and(AppointmentStatus::IN_PROGRESS->holdsQuota())->toBeTrue()
        ->and(AppointmentStatus::CANCELLED->holdsQuota())->toBeFalse()
        ->and(AppointmentStatus::NO_SHOW->holdsQuota())->toBeFalse();
});

it('follows the documented transition graph (BUSINESS-FLOW §2)', function (): void {
    expect(AppointmentStatus::BOOKED->canTransitionTo(AppointmentStatus::CONFIRMED))->toBeTrue()
        ->and(AppointmentStatus::CONFIRMED->canTransitionTo(AppointmentStatus::ARRIVED))->toBeTrue()
        ->and(AppointmentStatus::ARRIVED->canTransitionTo(AppointmentStatus::IN_PROGRESS))->toBeTrue()
        ->and(AppointmentStatus::IN_PROGRESS->canTransitionTo(AppointmentStatus::COMPLETED))->toBeTrue();
});

it('rejects illegal transitions', function (): void {
    expect(AppointmentStatus::COMPLETED->canTransitionTo(AppointmentStatus::ARRIVED))->toBeFalse()
        ->and(AppointmentStatus::BOOKED->canTransitionTo(AppointmentStatus::COMPLETED))->toBeFalse()
        ->and(AppointmentStatus::ARRIVED->canTransitionTo(AppointmentStatus::CANCELLED))->toBeFalse();
});

it('allows cancellation only before arrival', function (): void {
    expect(AppointmentStatus::BOOKED->isCancellable())->toBeTrue()
        ->and(AppointmentStatus::CONFIRMED->isCancellable())->toBeTrue()
        ->and(AppointmentStatus::ARRIVED->isCancellable())->toBeFalse();
});
