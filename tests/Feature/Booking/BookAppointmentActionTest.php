<?php

declare(strict_types=1);

use App\Actions\BookAppointmentAction;
use App\DataTransferObjects\BookAppointmentData;
use App\Enums\AppointmentStatus;
use App\Enums\MoveType;
use App\Enums\TruckStatus;
use App\Events\AppointmentBooked;
use App\Exceptions\DuplicateBookingException;
use App\Exceptions\FleetOwnershipException;
use App\Exceptions\InactiveTruckException;
use App\Exceptions\InvalidDriverException;
use App\Exceptions\SlotUnavailableException;
use App\Models\Container;
use App\Models\SlotWindow;
use App\Models\TransportCompany;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Support\Facades\Event;

/**
 * @return array{actor: User, data: BookAppointmentData, window: SlotWindow}
 */
function bookingScenario(int $capacity = 5, int $booked = 0, string $status = 'OPEN', string $containerNo = 'MAUU1234567'): array
{
    $company = TransportCompany::factory()->create();
    $actor = User::factory()->create(['company_id' => $company->id]);
    $truck = Truck::factory()->create(['company_id' => $company->id]);
    // ->driver(): sopir wajib ber-role `driver`, bukan sekadar sekantor.
    $driver = User::factory()->driver()->create(['company_id' => $company->id]);
    $window = SlotWindow::factory()->create([
        'capacity' => $capacity,
        'booked_count' => $booked,
        'status' => $status,
    ]);

    return [
        'actor' => $actor,
        'window' => $window,
        'data' => new BookAppointmentData(
            slotWindowId: $window->id,
            truckId: $truck->id,
            driverId: $driver->id,
            moveType: MoveType::DELIVERY,
            containerNo: $containerNo,
        ),
    ];
}

it('books an available slot, confirms it, and increments quota', function (): void {
    Event::fake();
    ['actor' => $actor, 'data' => $data, 'window' => $window] = bookingScenario(capacity: 5, booked: 0);

    $appointment = app(BookAppointmentAction::class)->execute($actor, $data);

    expect($appointment->status)->toBe(AppointmentStatus::CONFIRMED)
        ->and($appointment->company_id)->toBe($actor->company_id)
        ->and($appointment->booking_code)->toStartWith('TAS-');

    expect($window->fresh()->booked_count)->toBe(1);

    // Container menyimpan slot_window_id (penegak unik anti double-booking).
    $container = Container::query()->where('appointment_id', $appointment->id)->firstOrFail();
    expect($container->slot_window_id)->toBe($window->id)
        ->and($container->container_no)->toBe('MAUU1234567');

    Event::assertDispatched(AppointmentBooked::class);
});

it('rejects booking when the window is full (409)', function (): void {
    ['actor' => $actor, 'data' => $data, 'window' => $window] = bookingScenario(capacity: 3, booked: 3);

    expect(fn () => app(BookAppointmentAction::class)->execute($actor, $data))
        ->toThrow(SlotUnavailableException::class);

    expect($window->fresh()->booked_count)->toBe(3); // tidak berubah
});

it('rejects booking when the window is closed (409)', function (): void {
    ['actor' => $actor, 'data' => $data] = bookingScenario(status: 'CLOSED');

    expect(fn () => app(BookAppointmentAction::class)->execute($actor, $data))
        ->toThrow(SlotUnavailableException::class);
});

it('rejects booking into a window that has already ended (409)', function (): void {
    $this->travelTo(now()->setTime(12, 0)); // jauh dari tengah malam → window ±1 jam deterministik
    ['actor' => $actor, 'window' => $window] = bookingScenario();
    // Window kemarin yang lupa ditutup: tanpa guard ini booking lolos lalu
    // langsung disapu NO_SHOW ≤5 menit kemudian — absurd bagi transporter.
    $past = SlotWindow::factory()->create([
        'date' => now()->subDay()->toDateString(),
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
    ]);

    $truck = Truck::query()->where('company_id', $actor->company_id)->firstOrFail();
    $data = new BookAppointmentData(
        slotWindowId: $past->id,
        truckId: $truck->id,
        driverId: User::query()->where('company_id', $actor->company_id)->whereKeyNot($actor->id)->firstOrFail()->id,
        moveType: MoveType::DELIVERY,
        containerNo: 'PAST0000001',
    );

    expect(fn () => app(BookAppointmentAction::class)->execute($actor, $data))
        ->toThrow(SlotUnavailableException::class, 'berakhir');

    expect($past->fresh()->booked_count)->toBe(0);
});

it('still allows booking a window that is currently running', function (): void {
    $this->travelTo(now()->setTime(12, 0)); // jauh dari tengah malam → window ±1 jam deterministik
    Event::fake();
    ['actor' => $actor, 'window' => $window] = bookingScenario();
    // Sudah mulai tapi belum berakhir → truk masih bisa datang sebelum tutup.
    $running = SlotWindow::factory()->create([
        'date' => now()->toDateString(),
        'start_time' => now()->subHour()->format('H:i:s'),
        'end_time' => now()->addHour()->format('H:i:s'),
    ]);

    $truck = Truck::query()->where('company_id', $actor->company_id)->firstOrFail();
    $data = new BookAppointmentData(
        slotWindowId: $running->id,
        truckId: $truck->id,
        driverId: User::query()->where('company_id', $actor->company_id)->whereKeyNot($actor->id)->firstOrFail()->id,
        moveType: MoveType::DELIVERY,
        containerNo: 'RUNN0000001',
    );

    $appointment = app(BookAppointmentAction::class)->execute($actor, $data);

    expect($appointment->slot_window_id)->toBe($running->id)
        ->and($running->fresh()->booked_count)->toBe(1);
});

it('never over-books the last slot', function (): void {
    // Window sisa 1. Booking pertama lolos, kedua harus 409 — kuota tetap = capacity.
    ['actor' => $actor, 'data' => $data, 'window' => $window] = bookingScenario(capacity: 1, booked: 0);

    app(BookAppointmentAction::class)->execute($actor, $data);

    $second = new BookAppointmentData(
        slotWindowId: $data->slotWindowId,
        truckId: $data->truckId,
        driverId: $data->driverId,
        moveType: MoveType::RECEIVAL,
        containerNo: 'SINU9999999',
    );

    expect(fn () => app(BookAppointmentAction::class)->execute($actor, $second))
        ->toThrow(SlotUnavailableException::class);

    expect($window->fresh()->booked_count)->toBe(1);
});

it('blocks the same container being booked twice in one window', function (): void {
    ['actor' => $actor, 'data' => $data, 'window' => $window] = bookingScenario(capacity: 5, containerNo: 'TCLU1111111');

    app(BookAppointmentAction::class)->execute($actor, $data);

    expect(fn () => app(BookAppointmentAction::class)->execute($actor, $data))
        ->toThrow(DuplicateBookingException::class);

    expect($window->fresh()->booked_count)->toBe(1); // booking kedua tidak menambah kuota
});

it('forbids booking with a truck or driver from another company', function (): void {
    ['actor' => $actor, 'window' => $window] = bookingScenario();
    $otherCompany = TransportCompany::factory()->create();
    $foreignTruck = Truck::factory()->create(['company_id' => $otherCompany->id]);
    $foreignDriver = User::factory()->create(['company_id' => $otherCompany->id]);

    $data = new BookAppointmentData(
        slotWindowId: $window->id,
        truckId: $foreignTruck->id,
        driverId: $foreignDriver->id,
        moveType: MoveType::DELIVERY,
        containerNo: 'XXXU0000000',
    );

    expect(fn () => app(BookAppointmentAction::class)->execute($actor, $data))
        ->toThrow(FleetOwnershipException::class);

    expect($window->fresh()->booked_count)->toBe(0);
});

it('rejects booking with an INACTIVE truck', function (): void {
    ['actor' => $actor, 'data' => $data, 'window' => $window] = bookingScenario();

    // Truk dipensiunkan (rusak/dijual/izin mati). Endpoint hapus truk ber-riwayat
    // menyuruh transporter memakai INACTIVE — jadi status itu wajib benar-benar
    // menghentikan penjadwalan, bukan sekadar label.
    Truck::query()->whereKey($data->truckId)->update(['status' => TruckStatus::INACTIVE]);

    expect(fn () => app(BookAppointmentAction::class)->execute($actor, $data))
        ->toThrow(InactiveTruckException::class);

    expect($window->fresh()->booked_count)->toBe(0);
});

it('rejects booking when driver_id is a co-worker who is not a driver', function (): void {
    // `/me/fleet` sudah menyaring dropdown ke user ber-role `driver` saja, tapi
    // penyaringan UI bukan penegakan: klien bisa mengirim id user mana pun se-company
    // (mis. akun dispatcher/transporter itu sendiri). Kalau lolos, "sopir" appointment
    // bukan sopir → reminder nyasar & jadwalnya tak pernah muncul di `/me/appointments/today`
    // (butuh `appointment.read.self`), jadi sopir sungguhan tak pernah tahu.
    ['actor' => $actor, 'window' => $window] = bookingScenario();
    $bukanSopir = User::factory()->create(['company_id' => $actor->company_id]);

    $data = new BookAppointmentData(
        slotWindowId: $window->id,
        truckId: Truck::factory()->create(['company_id' => $actor->company_id])->id,
        driverId: $bukanSopir->id,
        moveType: MoveType::DELIVERY,
        containerNo: 'XXXU0000000',
    );

    expect(fn () => app(BookAppointmentAction::class)->execute($actor, $data))
        ->toThrow(InvalidDriverException::class);

    expect($window->fresh()->booked_count)->toBe(0);
});

it('reports a foreign non-driver as ownership failure, not invalid driver', function (): void {
    // Urutan guard sama seperti truk INACTIVE: kepemilikan DULU, baru kelayakan.
    // Kalau dibalik, beda pesan error membocorkan keberadaan user company lain.
    ['actor' => $actor, 'window' => $window] = bookingScenario();
    $otherCompany = TransportCompany::factory()->create();
    $foreignNonDriver = User::factory()->create(['company_id' => $otherCompany->id]);

    $data = new BookAppointmentData(
        slotWindowId: $window->id,
        truckId: Truck::factory()->create(['company_id' => $actor->company_id])->id,
        driverId: $foreignNonDriver->id,
        moveType: MoveType::DELIVERY,
        containerNo: 'XXXU0000000',
    );

    expect(fn () => app(BookAppointmentAction::class)->execute($actor, $data))
        ->toThrow(FleetOwnershipException::class);

    expect($window->fresh()->booked_count)->toBe(0);
});

it('reports a foreign INACTIVE truck as ownership failure, not inactive', function (): void {
    // Urutan guard penting: kepemilikan dicek DULU. Kalau status dicek lebih dulu,
    // pesan error yang berbeda membocorkan keberadaan & kondisi truk company lain.
    ['actor' => $actor, 'window' => $window] = bookingScenario();
    $foreignTruck = Truck::factory()->inactive()->create();
    $foreignDriver = User::factory()->create(['company_id' => $foreignTruck->company_id]);

    $data = new BookAppointmentData(
        slotWindowId: $window->id,
        truckId: $foreignTruck->id,
        driverId: $foreignDriver->id,
        moveType: MoveType::DELIVERY,
        containerNo: 'XXXU0000000',
    );

    expect(fn () => app(BookAppointmentAction::class)->execute($actor, $data))
        ->toThrow(FleetOwnershipException::class);
});
