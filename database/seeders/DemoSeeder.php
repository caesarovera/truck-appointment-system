<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Container;
use App\Models\Gate;
use App\Models\GateTransaction;
use App\Models\SlotWindow;
use App\Models\Terminal;
use App\Models\TransportCompany;
use App\Models\Truck;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Data demo TAS. Menyentuh semua status appointment + satu window
 * sengaja hampir penuh untuk demo race condition.
 *
 * Asumsi: model & migrasi sudah dibuat sesuai docs/BUSINESS-FLOW.md §4.
 *
 * Catatan forceFill: kolom status/version/booked_count/company_id guarded dari
 * mass-assignment (ADR-0004). Seeder menata KONDISI AWAL lintas status — bukan
 * menjalankan alur bisnis — jadi sah melewati Action; forceFill membuat niat
 * "bypass yang disengaja" itu terlihat eksplisit di code review.
 */
final class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $terminal = Terminal::create(['name' => 'JICT', 'code' => 'JICT']);
        $gateA = Gate::create(['terminal_id' => $terminal->id, 'code' => 'GATE-A', 'name' => 'Gate A']);
        $gateB = Gate::create(['terminal_id' => $terminal->id, 'code' => 'GATE-B', 'name' => 'Gate B']);

        // --- Staff terminal ---
        $this->user('admin@tas.test', 'Administrator', 'admin');
        $this->user('planner@tas.test', 'Slot Planner', 'planner');
        $this->user('gate@tas.test', 'Gate Officer', 'gate-officer', ['terminal_id' => $terminal->id]);

        // --- Perusahaan angkutan + armada + sopir ---
        $maju = $this->company('PT Maju Logistik', 'MAJU');
        $majuDispatcher = $this->user('dispatcher@majulog.test', 'Maju Dispatcher', 'transporter', ['company_id' => $maju->id]);
        $budi = $this->user('budi@majulog.test', 'Budi Santoso', 'driver', ['company_id' => $maju->id]);
        $majuTrucks = $this->trucks($maju, ['B 9011 XX', 'B 9012 XX', 'B 9013 XX']);

        $sinar = $this->company('PT Sinar Kargo', 'SINAR');
        $sinarDispatcher = $this->user('dispatcher@sinarkargo.test', 'Sinar Dispatcher', 'transporter', ['company_id' => $sinar->id]);
        $andi = $this->user('andi@sinarkargo.test', 'Andi Wijaya', 'driver', ['company_id' => $sinar->id]);
        $sinarTrucks = $this->trucks($sinar, ['B 7021 YY', 'B 7022 YY', 'B 7023 YY']);

        // --- Slot windows: kemarin, hari ini, besok (06:00-18:00, kapasitas 5/jam) ---
        $yesterday = $this->windows($gateA, Carbon::yesterday());
        $today = $this->windows($gateA, Carbon::today());
        $tomorrow = $this->windows($gateB, Carbon::tomorrow());

        // Window hari ini jam 08:00 sengaja hampir penuh (sisa 1) untuk demo race.
        // forceFill: booked_count guarded (ADR-0004) — seeder = jalur tepercaya
        // yang sengaja melewati Action (menata kondisi awal, bukan alur bisnis).
        $nearFull = $today[8];
        $nearFull->forceFill(['booked_count' => $nearFull->capacity - 1])->save();

        // --- Appointment lintas status ---
        // 2x COMPLETED (kemarin) + gate-in/out
        $this->completed($maju, $majuTrucks[0], $budi, $yesterday[7], 'DELIVERY', 'MAUU1234567');
        $this->completed($sinar, $sinarTrucks[0], $andi, $yesterday[9], 'RECEIVAL', 'SINU7654321');

        // 1x NO_SHOW (kemarin) — kuota sudah dikembalikan
        $this->appointment($maju, $majuTrucks[1], $budi, $yesterday[10], 'DELIVERY', 'MAUU2222222', 'NO_SHOW', returnsQuota: true);

        // 2x CONFIRMED hari ini → siap gate-in
        $this->appointment($maju, $majuTrucks[0], $budi, $today[9], 'DELIVERY', 'MAUU3333333', 'CONFIRMED');
        $this->appointment($sinar, $sinarTrucks[1], $andi, $today[10], 'RECEIVAL', 'SINU3333333', 'CONFIRMED');

        // 1x IN_PROGRESS hari ini → siap gate-out (sudah ada gate-in)
        $inProgress = $this->appointment($sinar, $sinarTrucks[2], $andi, $today[11], 'DELIVERY', 'SINU4444444', 'IN_PROGRESS');
        GateTransaction::create([
            'appointment_id' => $inProgress->id,
            'type' => 'IN',
            'processed_by' => User::where('email', 'gate@tas.test')->value('id'),
            'processed_at' => now()->subMinutes(30),
        ]);

        // 2x BOOKED besok → siap reschedule/cancel
        $this->appointment($maju, $majuTrucks[2], $budi, $tomorrow[7], 'DELIVERY', 'MAUU5555555', 'BOOKED');
        $this->appointment($sinar, $sinarTrucks[0], $andi, $tomorrow[8], 'RECEIVAL', 'SINU5555555', 'BOOKED');

        // 1x CANCELLED besok
        $this->appointment($sinar, $sinarTrucks[1], $andi, $tomorrow[9], 'DELIVERY', 'SINU6666666', 'CANCELLED', returnsQuota: true);
    }

    /** @param array<string,mixed> $extra */
    private function user(string $email, string $name, string $role, array $extra = []): User
    {
        $user = new User;
        $user->forceFill(array_merge([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ], $extra));
        $user->save();

        $user->assignRole($role);

        return $user;
    }

    private function company(string $name, string $code): TransportCompany
    {
        return TransportCompany::create(['name' => $name, 'code' => $code]);
    }

    /**
     * @param  array<int,string>  $plates
     * @return array<int,Truck>
     */
    private function trucks(TransportCompany $company, array $plates): array
    {
        return array_map(
            fn (string $plate): Truck => Truck::create([
                'company_id' => $company->id,
                'plate_no' => $plate,
                'status' => 'ACTIVE',
            ]),
            $plates,
        );
    }

    /**
     * Buat window per jam 06..17 untuk satu gate+tanggal.
     *
     * @return array<int,SlotWindow> key = jam (6..17)
     */
    private function windows(Gate $gate, Carbon $date): array
    {
        $windows = [];
        foreach (range(6, 17) as $hour) {
            // forceFill: booked_count/status guarded (ADR-0004) — lihat catatan run().
            $window = new SlotWindow;
            $window->forceFill([
                'gate_id' => $gate->id,
                'date' => $date->toDateString(),
                'start_time' => sprintf('%02d:00:00', $hour),
                'end_time' => sprintf('%02d:00:00', $hour + 1),
                'capacity' => 5,
                'booked_count' => 0,
                'status' => 'OPEN',
            ])->save();

            $windows[$hour] = $window;
        }

        return $windows;
    }

    private function appointment(
        TransportCompany $company,
        Truck $truck,
        User $driver,
        SlotWindow $window,
        string $moveType,
        string $containerNo,
        string $status,
        bool $returnsQuota = false,
    ): Appointment {
        // forceFill: company_id/status/version guarded (ADR-0004) — lihat catatan run().
        $appointment = new Appointment;
        $appointment->forceFill([
            'company_id' => $company->id,
            'truck_id' => $truck->id,
            'driver_id' => $driver->id,
            'slot_window_id' => $window->id,
            'move_type' => $moveType,
            'status' => $status,
            'booking_code' => 'TAS-'.Str::upper(Str::random(8)),
            'version' => 1,
        ])->save();

        Container::create([
            'appointment_id' => $appointment->id,
            'container_no' => $containerNo,
            'iso_type' => '22G1',
            'size' => 20,
        ]);

        // Kuota hanya naik untuk status yang masih "menahan" slot.
        if (! $returnsQuota && ! in_array($status, ['CANCELLED', 'NO_SHOW'], true)) {
            $window->increment('booked_count');
        }

        return $appointment;
    }

    private function completed(
        TransportCompany $company,
        Truck $truck,
        User $driver,
        SlotWindow $window,
        string $moveType,
        string $containerNo,
    ): void {
        $appointment = $this->appointment($company, $truck, $driver, $window, $moveType, $containerNo, 'COMPLETED');
        $gateOfficerId = User::where('email', 'gate@tas.test')->value('id');

        GateTransaction::create([
            'appointment_id' => $appointment->id,
            'type' => 'IN',
            'processed_by' => $gateOfficerId,
            'processed_at' => Carbon::yesterday()->setTime((int) $window->start_time, 5),
        ]);
        GateTransaction::create([
            'appointment_id' => $appointment->id,
            'type' => 'OUT',
            'processed_by' => $gateOfficerId,
            'processed_at' => Carbon::yesterday()->setTime((int) $window->start_time, 45),
        ]);
    }
}
