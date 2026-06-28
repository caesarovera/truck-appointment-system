# docs/CODE-WALKTHROUGH.md — Penjelasan Detail Kode

> Dokumen ini menjelaskan **setiap potongan kode** yang dibuat (foundation + seluruh
> slice backend MVP: booking, auth, policy, reschedule/cancel, gate-in/out,
> no-show/reminder, realtime, endpoint pendukung, slot-window management, **hardening
> rate-limit**, **read referensi & persona**, **admin CRUD master data**), lengkap dengan **alasan (kenapa)** dan
> **contoh**. Sasaran: kamu bisa membaca kode TAS dan paham *kenapa* ditulis begitu,
> bukan sekadar *apa*-nya. **Kode frontend (Vue SPA) ada di `docs/FRONTEND.md`.**
>
> Urutan baca yang disarankan: konsep dasar → enum → migrasi → model → factory →
> seeder → provider → tooling → test. Untuk cara menjalankan, lihat
> `docs/SETUP-GUIDE.md`. Untuk aturan arsitektur, lihat `CLAUDE.md`.

---

## Daftar Isi
- [A. Konsep PHP/Laravel yang dipakai berulang](#a-konsep-phplaravel-yang-dipakai-berulang)
- [B. Enum & State Machine](#b-enum--state-machine)
- [C. Migrasi (skema database)](#c-migrasi-skema-database)
- [D. Model Eloquent](#d-model-eloquent)
- [E. Factory](#e-factory)
- [F. Seeder](#f-seeder)
- [G. AppServiceProvider](#g-appserviceprovider)
- [H. File tooling](#h-file-tooling)
- [I. Test](#i-test)
- [J. Slice Booking (jantung anti-race)](#j-slice-booking-jantung-anti-race)
- [K. Slice Auth (Sanctum token + abilities)](#k-slice-auth-sanctum-token--abilities)
- [L. Slice Policy (isolasi data per role)](#l-slice-policy-isolasi-data-per-role)
- [M. Slice Reschedule & Cancel (optimistic lock)](#m-slice-reschedule--cancel-optimistic-lock)
- [N. Slice Gate-in / Gate-out (idempoten + transaksi gate)](#n-slice-gate-in--gate-out-idempoten--transaksi-gate)
- [O. Slice Job No-show & Reminder (scheduler + queue)](#o-slice-job-no-show--reminder-scheduler--queue)
- [P. Slice Realtime (broadcast Reverb + seam TOS)](#p-slice-realtime-broadcast-reverb--seam-tos)
- [Q. Slice Endpoint Pendukung (me/today + utilisasi)](#q-slice-endpoint-pendukung-metoday--utilisasi)
- [R. Slice Slot-window Management (open/close)](#r-slice-slot-window-management-openclose)
- [S. Slice Hardening (rate limiting)](#s-slice-hardening-rate-limiting)
- [T. Read Referensi (gates + fleet)](#t-slice-read-referensi-gates--fleet)
- [U. Read endpoints persona (booking list + gate queue)](#u-read-endpoints-persona-booking-list--gate-queue)
- [V. Admin CRUD master data (terminal/gate/company/user)](#v-admin-crud-master-data-terminalgatecompanyuser)
- [Frontend (Vue SPA) → `docs/FRONTEND.md`](#frontend-vue-spa)

---

## A. Konsep PHP/Laravel yang dipakai berulang

Sebelum masuk file per file, ini idiom yang muncul di mana-mana.

### `declare(strict_types=1);`
Baris pertama tiap file PHP kita.
```php
<?php

declare(strict_types=1);
```
**Apa:** memaksa PHP **tidak** meng-koersi tipe diam-diam. Tanpa ini, fungsi
`f(int $x)` yang dipanggil `f("5")` akan diam-diam mengubah `"5"` → `5`. Dengan
strict, itu jadi error.
**Kenapa:** bug tipe ketahuan lebih awal, bukan jadi data salah di DB. Diwajibkan
`CLAUDE.md`.

### `final class`
```php
final class BookAppointmentAction { ... }
```
**Apa:** class tidak boleh di-`extends`.
**Kenapa:** mendorong komposisi, bukan warisan dalam. Action/Service kita unit kecil
satu tugas — tidak dirancang untuk diwarisi. (Model tidak di-`final` karena Eloquent
& factory butuh fleksibilitas.)

### Docblock generics — mis. `BelongsTo<Terminal, $this>`
```php
/** @return BelongsTo<Terminal, $this> */
public function terminal(): BelongsTo { ... }
```
**Apa:** PHP native cuma tahu tipe `BelongsTo`. Docblock `<Terminal, $this>`
memberi tahu **PHPStan/IDE** bahwa relasi ini menghasilkan `Terminal`.
**Kenapa:** PHPStan level 8 butuh ini; tanpa generics ia komplain
`missingType.generics`. Bonus: autocomplete IDE jadi pintar.

### `casts()` — konversi otomatis tipe kolom
```php
protected function casts(): array
{
    return ['status' => AppointmentStatus::class];
}
```
**Apa:** kolom `status` di DB bertipe string `'BOOKED'`; cast mengubahnya jadi objek
enum `AppointmentStatus::BOOKED` saat dibaca, dan sebaliknya saat disimpan.
**Kenapa:** kode bekerja dengan objek enum yang aman tipe, bukan string mentah.

### Named arguments — `returnsQuota: true`
```php
$this->appointment(..., 'NO_SHOW', returnsQuota: true);
```
**Apa:** menyebut nama parameter saat memanggil. **Kenapa:** lebih jelas daripada
`true` telanjang yang tidak jelas artinya.

---

## B. Enum & State Machine

File: `app/Enums/*.php`. Lima enum **backed** (punya nilai string).

### Kenapa backed enum (`: string`)
```php
enum MoveType: string
{
    case DELIVERY = 'DELIVERY';
    case RECEIVAL = 'RECEIVAL';
}
```
- **Type-safe:** fungsi `f(MoveType $t)` hanya menerima nilai sah. Tidak mungkin
  mengirim `'DELVERY'` (typo) — error saat kompilasi/analisis, bukan runtime.
- **Nilai string** disimpan ke DB & dikirim ke API apa adanya (`'DELIVERY'`),
  jadi tetap terbaca manusia.

### `AppointmentStatus` = state machine hidup
Daripada menyebar `if ($status === 'BOOKED')` di banyak tempat, aturan transisi
(`BUSINESS-FLOW.md §2`) dijadikan **method di enum**:

```php
public function allowedNext(): array
{
    return match ($this) {
        self::BOOKED      => [self::CONFIRMED, self::CANCELLED, self::NO_SHOW],
        self::CONFIRMED   => [self::ARRIVED, self::CANCELLED, self::NO_SHOW],
        self::ARRIVED     => [self::IN_PROGRESS],
        self::IN_PROGRESS => [self::COMPLETED],
        self::COMPLETED, self::CANCELLED, self::NO_SHOW => [], // final
    };
}

public function canTransitionTo(self $target): bool
{
    return in_array($target, $this->allowedNext(), true);
}
```

**Kenapa `match`:** `match` itu *exhaustive* — kalau nanti ada case enum baru dan
lupa menanganinya, PHP error. `switch` tidak seketat itu. Jadi state machine kita
mustahil "bolong" tanpa ketahuan.

**Contoh pakai (nanti di Action):**
```php
if (! $appointment->status->canTransitionTo(AppointmentStatus::ARRIVED)) {
    throw new InvalidStateTransition(); // tolak gate-in dari status salah
}
```

Helper lain dan gunanya:
| Method | Untuk |
|--------|-------|
| `isFinal()` | cegah ubah status yang sudah COMPLETED/CANCELLED/NO_SHOW |
| `holdsQuota()` | tahu apakah status ini masih "memakai" kuota slot (untuk balikin kuota saat cancel/no-show) |
| `isCancellable()` | transporter hanya boleh cancel sebelum truk tiba |

**Contoh:**
```php
AppointmentStatus::CONFIRMED->holdsQuota();   // true  → kuota dipakai
AppointmentStatus::NO_SHOW->holdsQuota();      // false → kuota sudah dilepas
AppointmentStatus::COMPLETED->isFinal();       // true  → tak bisa diubah lagi
```

---

## C. Migrasi (skema database)

File: `database/migrations/2026_06_27_1530*_*.php`. Tiap migrasi = 1 tabel.

### Pola umum
```php
return new class extends Migration
{
    public function up(): void { Schema::create('terminals', function (Blueprint $table): void {
        $table->id();
        $table->string('code')->unique();
        $table->string('name');
        $table->timestamps();
    }); }

    public function down(): void { Schema::dropIfExists('terminals'); }
};
```
- **Anonymous class** (`new class extends Migration`): gaya Laravel modern, tak perlu
  nama class unik.
- `$table->id()` = primary key auto-increment `bigint`.
- `$table->timestamps()` = kolom `created_at` & `updated_at` otomatis.

### Foreign key & urutan
```php
$table->foreignId('terminal_id')->constrained()->cascadeOnDelete();
```
- `foreignId('terminal_id')->constrained()` membuat kolom + FK ke tabel `terminals`
  (ditebak dari nama `terminal_id`).
- `cascadeOnDelete()`: hapus terminal → gate-nya ikut terhapus.
- **Kenapa urutan migrasi penting:** tabel tujuan FK harus dibuat lebih dulu. Maka
  `terminals` sebelum `gates`, `transport_companies` sebelum `trucks`, dst.

### Hardening yang ditanam di skema (INTI proyek)
Ini bukan kolom biasa — ini pertahanan terhadap masalah konkurensi.

**1. Anti over-booking (slot_windows):**
```php
$table->unsignedInteger('capacity');
$table->unsignedInteger('booked_count')->default(0);
$table->unique(['gate_id', 'date', 'start_time']);   // 1 window unik
$table->index(['gate_id', 'date', 'status']);         // cepat untuk query ketersediaan
```
`booked_count` vs `capacity` adalah inti race condition: Action akan `lockForUpdate`
baris ini saat booking. Index `(gate_id, date, status)` mempercepat endpoint
"ketersediaan slot" yang sering di-poll.

**2. Optimistic lock (appointments):**
```php
$table->unsignedInteger('version')->default(1);
$table->string('booking_code')->unique();
```
`version` naik tiap reschedule. Kalau dua orang edit bareng, yang `version`-nya
ketinggalan ditolak (cegah saling timpa). `booking_code` unik = identitas booking
untuk QR/sopir.

**3. Anti double-booking kontainer (containers):**
```php
$table->unsignedBigInteger('slot_window_id')->nullable();
$table->string('container_no');
$table->unique(['slot_window_id', 'container_no']);
```
**Ini keputusan desain penting.** Aturannya: satu kontainer tidak boleh dibooking
dua kali di window yang sama (`CLAUDE.md`). Caranya:
- `slot_window_id` di-*denormalisasi* (disalin) ke tabel containers.
- Unik `(slot_window_id, container_no)` menegakkan aturan di level DB.
- `nullable` + saat **cancel/no-show** kita set `slot_window_id = NULL` → slot
  "dilepas", kontainer bisa dibooking lagi. (DB mengizinkan banyak baris NULL,
  jadi tidak bentrok.)

> **Kenapa di DB, bukan cuma di kode:** ini "pertahanan terakhir". Kalau ada bug di
> Action atau dua request lolos lock secara ajaib, DB tetap menolak duplikat.

**4. Anti double gate event (gate_transactions):**
```php
$table->unique(['appointment_id', 'type']);   // 1 IN + 1 OUT per appointment
```
Sopir/petugas sering double-tap; constraint ini menjamin maksimal satu IN & satu OUT.

### Kolom di tabel `users` (migrasi bawaan diubah)
```php
$table->unsignedBigInteger('terminal_id')->nullable()->index(); // gate officer
$table->unsignedBigInteger('company_id')->nullable()->index();   // driver/transporter
```
**Kenapa tanpa FK lintas-tabel di sini:** menambah FK ke tabel `users` yang dibuat
paling awal akan menyulitkan `migrate:fresh` di SQLite (urutan ALTER). Integritas
arah sebaliknya (appointments → users) tetap pakai FK. Kompromi yang aman.

### Kenapa enum disimpan `string`, bukan `$table->enum()`
`$table->enum()` membuat tipe ENUM khas MySQL yang ribet diubah & tak portabel ke
SQLite. Kita pakai `string` + PHP Enum cast di model → portabel (test pakai SQLite,
prod bisa MySQL) dan tetap type-safe di aplikasi.

---

## D. Model Eloquent

File: `app/Models/*.php`. Model = representasi 1 baris tabel + relasinya.

### `User` — jantung auth & RBAC
```php
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected string $guard_name = 'api';
    protected $fillable = ['name','email','password','terminal_id','company_id'];
    protected $hidden = ['password','remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed'];
    }
}
```
- `HasApiTokens` (Sanctum): memberi `->createToken()` untuk login API.
- `HasRoles` (Spatie): memberi `->assignRole()`, `->can()`.
- `$guard_name = 'api'`: **wajib** karena permission kita di-seed untuk guard `api`
  (lihat RolePermissionSeeder). Kalau tidak diset, cek `->can()` meleset.
- `$fillable`: kolom yang boleh diisi massal (mass assignment). **Kenapa penting:**
  cegah penyerang mengirim field tak terduga (mis. `is_admin`).
- `$hidden`: `password` tak pernah ikut saat model di-serialize ke JSON.
- `'password' => 'hashed'`: set password otomatis di-hash. Bersifat *idempoten* —
  kalau nilai sudah hash, dibiarkan (jadi `Hash::make()` manual di seeder aman).

Relasi:
```php
/** @return BelongsTo<TransportCompany, $this> */
public function company(): BelongsTo
{
    return $this->belongsTo(TransportCompany::class, 'company_id');
}
```
**Contoh pakai:**
```php
$driver->company->name;          // nama perusahaan sopir
$user->assignRole('planner');    // beri role
$user->can('slot.manage');       // cek izin
```

### `Appointment` — paling kaya
```php
/**
 * @property AppointmentStatus $status
 * @property MoveType $move_type
 * @property int $version
 * ...
 */
class Appointment extends Model
{
    use HasFactory;
    use LogsActivity;   // ← audit trail otomatis (Spatie)

    protected function casts(): array
    {
        return [
            'move_type' => MoveType::class,
            'status'    => AppointmentStatus::class,
            'version'   => 'integer',
        ];
    }
}
```

**Kenapa docblock `@property`:** Larastan/PHPStan menebak `status` sebagai `string`
dari kolom DB. Tanpa `@property AppointmentStatus $status`, kode
`$this->status === AppointmentStatus::ARRIVED` dianggap "selalu false" (membandingkan
string vs enum). Anotasi memberi tahu tipe aslinya (hasil cast).

**Audit otomatis (`LogsActivity`):**
```php
public function getActivitylogOptions(): LogOptions
{
    return LogOptions::defaults()
        ->logOnly(['status', 'slot_window_id', 'version'])
        ->logOnlyDirty()           // cuma catat yang berubah
        ->dontSubmitEmptyLogs()
        ->useLogName('appointment');
}
```
Setiap kali `status`/`slot_window_id`/`version` berubah, Spatie otomatis menulis
baris ke `activity_log` — inilah "sumber kebenaran audit" (`BUSINESS-FLOW.md §3.7`).

**Helper & relasi spesial:**
```php
public function isGatedIn(): bool
{
    return in_array($this->status, [AppointmentStatus::ARRIVED, AppointmentStatus::IN_PROGRESS], true);
}

/** @return HasOne<GateTransaction, $this> */
public function gateIn(): HasOne
{
    return $this->hasOne(GateTransaction::class)->where('type', 'IN');
}
```
`isGatedIn()` dipakai job idempoten (`if ($appointment->isGatedIn()) return;`).
`gateIn()`/`gateOut()` = relasi tersaring, jadi bisa `->gateIn->processed_at`.

### `SlotWindow` — logika kuota
```php
public function hasCapacity(): bool { return $this->booked_count < $this->capacity; }
public function remaining(): int    { return max(0, $this->capacity - $this->booked_count); }
public function isOpen(): bool      { return $this->status === SlotWindowStatus::OPEN; }
```
**Kenapa method, bukan hitung manual di banyak tempat:** satu definisi "sisa kuota",
dipakai Resource (tampilkan sisa), Action (tolak kalau penuh), test.
**Contoh:**
```php
if (! $window->isOpen() || ! $window->hasCapacity()) {
    abort(409, 'Slot penuh atau ditutup');
}
```

---

## E. Factory

File: `database/factories/*.php`. Factory = pabrik data palsu untuk test & seed.

```php
/** @extends Factory<Truck> */
class TruckFactory extends Factory
{
    protected $model = Truck::class;

    public function definition(): array
    {
        return [
            'company_id' => TransportCompany::factory(),   // bikin perusahaan sekalian
            'plate_no'   => 'B '.fake()->unique()->numberBetween(1000,9999).' '.fake()->bothify('??'),
            'status'     => TruckStatus::ACTIVE,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => TruckStatus::INACTIVE]);
    }
}
```
- `definition()`: nilai default tiap kali bikin Truck.
- `TransportCompany::factory()` di dalamnya: relasi otomatis dibuat. Jadi
  `Truck::factory()->create()` sekaligus bikin perusahaannya.
- **State** (`inactive()`, `nearlyFull()`, `confirmed()`): variasi siap pakai.

**Kenapa `definition()` TANPA docblock `@return`:** lihat penjelasan di
`SETUP-GUIDE §6c` / Troubleshooting — docblock `array<string,mixed>` bentrok dengan
tipe induk yang lebih sempit (PHPStan `method.childReturnType`). Dibiarkan polos =
mewarisi tipe induk yang benar.

**Contoh pakai di test:**
```php
$full = SlotWindow::factory()->full()->create();          // window penuh
$appt = Appointment::factory()->for($company,'company')->confirmed()->create();
Truck::factory()->count(3)->for($company,'company')->create();
```

---

## F. Seeder

File: `database/seeders/*.php`. Mengisi DB dengan data awal.

### `RolePermissionSeeder` — sumber kebenaran RBAC
```php
foreach ($permissions as $name) {
    Permission::findOrCreate($name, 'api');   // guard 'api' — samakan dgn User
}
$matrix = [
    'planner'      => ['slot.manage','slot.read','appointment.read','appointment.override','report.read','audit.read'],
    'gate-officer' => ['gate.process','appointment.read','slot.read'],
    // ...
];
foreach ($matrix as $role => $perms) {
    Role::findOrCreate($role, 'api')->syncPermissions($perms);
}
```
- `findOrCreate`: idempoten — jalan berkali-kali tidak menduplikat.
- **`'api'`**: harus sama dengan `$guard_name` di User, kalau beda `->can()` gagal.
- `$matrix` **wajib** cermin persis `BUSINESS-FLOW.md §1`.

### `DemoSeeder` — data demo menyentuh semua status
Beberapa keputusan kode penting:

**1. `forceFill` untuk membuat user:**
```php
$user = new User;
$user->forceFill(array_merge([
    'name' => $name, 'email' => $email,
    'password' => Hash::make('password'), 'email_verified_at' => now(),
], $extra));
$user->save();
```
**Kenapa bukan `User::create(array_merge(...))`:** PHPStan level 8 menolaknya —
`create()` minta `array<model property,...>` tapi `array_merge` menghasilkan
`array<string,mixed>`. `forceFill` menerima array umum dan tetap menjalankan cast
(password tetap di-hash). (Detail di Troubleshooting SETUP-GUIDE.)

**2. Window di-key per integer jam:**
```php
foreach (range(6, 17) as $hour) {
    $windows[$hour] = SlotWindow::create([...]);   // key = int 6..17
}
// dipakai: $today[8], $yesterday[10]
```
**Kenapa integer, bukan `'06'`,`'08'`:** PHP otomatis mengubah string angka seperti
`'10'` jadi int `10`, sehingga array jadi campuran key string+int → PHPStan
`offsetAccess.notFound`. Integer konsisten menghilangkan masalah.

**3. Logika kuota saat seed:**
```php
if (! $returnsQuota && ! in_array($status, ['CANCELLED','NO_SHOW'], true)) {
    $window->increment('booked_count');
}
```
Hanya status yang "memakai" slot yang menaikkan `booked_count`. CANCELLED/NO_SHOW
tidak (kuotanya sudah balik) — supaya data demo konsisten dengan aturan kuota.

---

## G. AppServiceProvider

File: `app/Providers/AppServiceProvider.php`.
```php
public function boot(): void
{
    Model::preventLazyLoading(! $this->app->isProduction());
    Model::preventAccessingMissingAttributes(! $this->app->isProduction());
}
```
- **`preventLazyLoading`:** kalau kode mengakses relasi yang belum di-`with()`
  (penyebab N+1 query), Laravel **melempar error di dev/test** — jadi N+1 ketahuan
  saat ngoding, bukan jadi lambat di prod. Di production dimatikan (jangan crash).
- **`preventAccessingMissingAttributes`:** error kalau baca kolom yang tidak ada
  (salah ketik nama kolom).
- **Binding repository** didaftarkan di `register()` (Contracts → impl Eloquent):
  ```php
  $this->app->bind(SlotRepositoryInterface::class, SlotRepository::class);
  $this->app->bind(AppointmentRepositoryInterface::class, AppointmentRepository::class);
  ```
  Karena ini, controller/action cukup minta `SlotRepositoryInterface` di constructor
  dan Laravel menyuntik implementasinya. (Lihat bagian J.)

**Contoh efeknya:** `Appointment::all()` lalu `$a->truck->plate_no` dalam loop →
di dev langsung error "lazy loading violation", memaksa kamu tulis
`Appointment::with('truck')->get()`.

---

## H. File tooling

### `phpstan.neon`
```neon
includes:
    - vendor/larastan/larastan/extension.neon
parameters:
    level: 8
    paths: [app, database, routes]
    checkModelProperties: true
```
- `includes`: aktifkan Larastan agar PHPStan paham Eloquent.
- `level: 8`: cek ketat termasuk kemungkinan `null`.
- `checkModelProperties: true`: validasi `@property` di model benar-benar cocok.

### `tests/Pest.php`
```php
pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');
```
- Semua test di folder `Feature` otomatis pakai `RefreshDatabase` (DB bersih tiap
  test) dan `TestCase` Laravel (akses penuh aplikasi).
- Test di `Unit` tidak (lebih ringan, tanpa DB).

### `phpunit.xml` (DB `:memory:`)
```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```
Test pakai SQLite di RAM: cepat & **tidak menyentuh** `database/database.sqlite`
(data dev aman). Tanpa ini, `RefreshDatabase` akan menghapus data dev tiap run.

### `composer.json` scripts
```json
"test": "pest --parallel",
"analyse": "phpstan analyse --memory-limit=1G",
"fix": "pint",
"lint": "pint --test"
```
Singkatan agar perintah seragam antar-orang & dipakai di CI.

---

## I. Test

File: `tests/Unit/AppointmentStatusTest.php`, `tests/Feature/FoundationSeedTest.php`.
Pakai sintaks **Pest** (`it(...)`, `expect(...)`).

### Unit (tanpa DB)
```php
it('rejects illegal transitions', function (): void {
    expect(AppointmentStatus::COMPLETED->canTransitionTo(AppointmentStatus::ARRIVED))
        ->toBeFalse();
});
```
- `it('...')` = nama test yang dibaca manusia.
- `expect($x)->toBeFalse()` = assertion. Bisa dirantai:
  `expect($a)->toBeTrue()->and($b)->toBeFalse()`.

### Feature (pakai DB)
```php
use function Pest\Laravel\seed;

it('wires the RBAC matrix', function (): void {
    seed([RolePermissionSeeder::class, DemoSeeder::class]);

    $planner = User::query()->where('email','planner@tas.test')->firstOrFail();

    expect($planner->can('slot.manage'))->toBeTrue()
        ->and($planner->can('appointment.write'))->toBeFalse();
});
```
- `seed([...])` menjalankan seeder di DB test (in-memory).
- `firstOrFail()` (bukan `first()`): kembalikan model atau lempar error — **aman
  untuk PHPStan level 8** karena hasilnya pasti bukan `null`.

**Menjalankan:**
```bash
./vendor/bin/pest                       # semua
./vendor/bin/pest --filter="RBAC"       # cocokkan nama
./vendor/bin/pest tests/Unit            # folder tertentu
```

---

## J. Slice Booking (jantung anti-race)

Inilah vertical slice pertama yang menyatukan semua lapisan. Alur request:

```
POST /api/v1/appointments
  → auth:sanctum            (harus login)
  → middleware idempotency  (anti double-tap)
  → BookAppointmentRequest  (validasi + authorize + build DTO)
  → BookAppointmentController (invokable, tipis)
  → BookAppointmentAction   (transaksi + lock + kuota)  ← LOGIKA
      → SlotRepository / AppointmentRepository (data)
  → AppointmentResource     (output JSON)
  (setelah commit) → event AppointmentBooked → listener invalidasi cache
```

### J.1 Data layer — Repository + Contracts

**Kenapa ada interface (`Contracts/`)?** Supaya Action bergantung pada *kontrak*,
bukan implementasi konkret. Bisa diganti/di-mock saat test, dan di-*bind* sekali di
`AppServiceProvider`.

```php
// app/Contracts/SlotRepositoryInterface.php
public function lockForUpdate(int $slotWindowId): ?SlotWindow;
public function incrementBooked(SlotWindow $window): void;
public function cachedAvailability(int $gateId, string $date): Collection;
public function forgetAvailability(int $gateId, string $date): void;
```

Implementasi inti (`SlotRepository`):
```php
public function lockForUpdate(int $slotWindowId): ?SlotWindow
{
    return SlotWindow::query()->whereKey($slotWindowId)->lockForUpdate()->first();
}
```
**`lockForUpdate()`** menghasilkan SQL `SELECT ... FOR UPDATE` → baris slot **dikunci**
sampai transaksi selesai. Transporter kedua yang mengincar slot sama **menunggu**
sampai yang pertama commit, lalu membaca `booked_count` terbaru. Inilah inti anti-race.

Cache ketersediaan (anti-stampede):
```php
return Cache::flexible($key, [10, 30], fn () => $this->queryAvailability($gateId, $date));
```
**`Cache::flexible($key, [segar, basi], $cb)`:** data dianggap *segar* 10 detik; antara
10–30 detik masih disajikan (stale) sambil di-refresh di belakang. Jadi saat banyak
transporter poll bersamaan, DB tidak diserbu (cuma satu yang refresh).
> Catatan implementasi: kita pakai key eksplisit + `Cache::forget`, bukan `Cache::tags`,
> karena cache dev (`database`) tak mendukung tag. Lihat changelog `HANDOVER.md`.

### J.2 DTO — `BookAppointmentData`

```php
final class BookAppointmentData extends Data
{
    public function __construct(
        public int $slotWindowId,
        public int $truckId,
        public int $driverId,
        public MoveType $moveType,
        public string $containerNo,
        public ?string $isoType = null,
        public ?int $size = null,
    ) {}
}
```
**Kenapa DTO, bukan oper `$request` ke Action?** Action jadi tak tahu-menahu soal
HTTP — bisa dipanggil dari command/job/test tanpa request. Tipe tiap field jelas
(`MoveType`, bukan string). **Sengaja tanpa `companyId`** — itu diambil dari user yang
login, bukan dari klien (jangan percaya klien soal kepemilikan company).

### J.3 Action — `BookAppointmentAction` (inti)

```php
public function execute(User $actor, BookAppointmentData $data): Appointment
{
    $companyId = $actor->company_id ?? throw new FleetOwnershipException;
    $this->assertFleetBelongsToCompany($companyId, $data);   // truk & sopir milik company?

    $appointment = DB::transaction(function () use ($data, $companyId): Appointment {
        $window = $this->slots->lockForUpdate($data->slotWindowId);     // (1) KUNCI
        if ($window === null)            throw (new ModelNotFoundException)->setModel(SlotWindow::class);
        if (! $window->isOpen())         throw SlotUnavailableException::closed();   // (2) 409
        if (! $window->hasCapacity())    throw SlotUnavailableException::full();      // (2) 409

        try {
            $appointment = $this->appointments->createConfirmed($data, $companyId, $this->generateBookingCode());
        } catch (UniqueConstraintViolationException) {
            throw new DuplicateBookingException;                          // (3) jaring DB
        }
        $this->slots->incrementBooked($window);                          // (4) kuota++
        return $appointment;
    }, attempts: 3);                                                     // (5) retry deadlock

    AppointmentBooked::dispatch($appointment);                          // (6) efek samping pasca-commit
    return $appointment;
}
```
Nomor-nomor di atas:
1. **Lock** baris slot — serialisasi perebut slot terakhir.
2. Tolak kalau **ditutup/penuh** → exception ber-`render()` jadi **409**.
3. Kalau kontainer sudah dibooking di window itu, DB melempar unik-violation →
   diterjemahkan jadi **`DuplicateBookingException` (409)**. Ini "jaring terakhir"
   bila idempotency HTTP terlewat.
4. `booked_count++` **di transaksi yang sama** dengan create → konsisten.
5. **`attempts: 3`** — kalau DB deadlock, transaksi diulang otomatis sampai 3×.
6. **Efek samping (event) di-dispatch SETELAH commit**, bukan di dalam transaksi —
   aturan keras `CLAUDE.md` (jangan kirim job/HTTP di tengah transaksi).

> **Kenapa exception untuk alur "penuh", bukan return null/false?** Alur sukses dan
> gagal jadi tegas; controller tak perlu cek-cek; HTTP status benar otomatis lewat
> `render()`. Membaca kodenya pun lurus (happy path saja).

### J.4 HTTP — Request, Controller, Resource, Middleware

**FormRequest** (`BookAppointmentRequest`) — gerbang masuk:
```php
public function authorize(): bool
{
    return (bool) $this->user()?->can('appointment.write');   // hanya transporter
}

public function rules(): array
{
    $companyId = $this->user()?->company_id;
    return [
        'truck_id'  => ['required','integer', Rule::exists('trucks','id')->where('company_id',$companyId)],
        'driver_id' => ['required','integer', Rule::exists('users','id')->where('company_id',$companyId)],
        // ...
    ];
}
```
- `authorize()` false → otomatis **403**. Gagal `rules()` → otomatis **422**.
- `Rule::exists(...)->where('company_id', $companyId)`: truk/sopir **harus** milik
  company si user — isolasi antar-company ditegakkan sejak validasi (plus dicek lagi
  di Action = defense in depth).
- `toData()` mengubah input tervalidasi → DTO. (Dinamai `toData`, bukan `data`,
  karena `Request::data()` sudah ada — bentrok signature.)

**Controller** invokable (tipis, tanpa logika):
```php
public function __invoke(BookAppointmentRequest $request, BookAppointmentAction $action): JsonResponse
{
    $user = $request->user();
    abort_if($user === null, 401);
    $appointment = $action->execute($user, $request->toData());
    $appointment->load(['truck','driver','company','slotWindow','containers']);   // eager → cegah N+1
    return AppointmentResource::make($appointment)->response()->setStatusCode(201);
}
```

**Resource** — output. Pakai `@mixin` + `whenLoaded`:
```php
/** @mixin Appointment */
final class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->status->value,
            'slot_window' => $this->whenLoaded('slotWindow', fn () => SlotWindowResource::make($this->slotWindow)),
            // ...
        ];
    }
}
```
- **`@mixin Appointment`:** memberitahu PHPStan bahwa `$this->status` dll. adalah
  properti Appointment (Resource mem-proxy ke model). Tanpa ini PHPStan komplain.
- **`whenLoaded('rel', fn () => ...)`:** field relasi hanya muncul kalau sudah
  di-`load()`. Mencegah N+1 *dan* output yang tak sengaja membocorkan relasi berat.

**Middleware** `IdempotencyKey`:
```php
$lock = Cache::lock("{$cacheKey}:lock", 10);
if (! $lock->get()) abort(409);            // kembar masih in-flight
try {
    $response = $next($request);
    if ($response instanceof JsonResponse && $response->getStatusCode() < 400) {
        Cache::put($cacheKey, ['status'=>..., 'body'=>$response->getData(true)], now()->addHours(24));
    }
    return $response;
} finally { $lock->release(); }
```
Klien kirim header `Idempotency-Key: <uuid>`. Request kedua dengan key sama →
**respons pertama diputar ulang** (header `Idempotent-Replayed: true`), **tanpa**
membuat appointment baru. Krusial untuk mobile yang sering double-tap.

### J.5 Test slice (TDD)
Dua level, sesuai `CLAUDE.md`:
- **Action-level** (`BookAppointmentActionTest`): panggil `app(BookAppointmentAction::class)`
  langsung — uji murni logika (happy, full→409, closed→409, **never over-book**,
  duplicate container, fleet ownership).
- **Endpoint-level** (`BookAppointmentEndpointTest`): `Sanctum::actingAs($user)` +
  `postJson` — uji rangkaian HTTP (201, 409, **idempotency double-submit = 1 booking**,
  403 driver, 401, 422 truk company lain).

Contoh assertion penting (anti over-booking):
```php
it('never over-books the last slot', function (): void {
    [...] = bookingScenario(capacity: 1, booked: 0);
    app(BookAppointmentAction::class)->execute($actor, $data);          // slot terakhir
    expect(fn () => app(BookAppointmentAction::class)->execute($actor, $second))
        ->toThrow(SlotUnavailableException::class);                      // yang kedua 409
    expect($window->fresh()->booked_count)->toBe(1);                    // tidak lebih dari kapasitas
});
```

---

## K. Slice Auth (Sanctum token + abilities)

Tiga endpoint: `POST /login`, `POST /logout`, `GET /me`.

**Login** — verifikasi manual lalu terbitkan token:
```php
$user = User::query()->where('email', $request->string('email')->toString())->first();
if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
    throw ValidationException::withMessages(['email' => [__('auth.failed')]]);  // 422 generik
}
$abilities = $user->getAllPermissions()->pluck('name')->all();
$token = $user->createToken($request->deviceName(), $abilities)->plainTextToken;
```
- **Pesan error generik** (`auth.failed` di field `email`) — jangan beri tahu apakah
  email terdaftar atau password salah (cegah enumerasi akun).
- **`Hash::check(plain, $user->password)`** membandingkan password polos dengan hash
  di DB. Tidak pernah mendekripsi (hash satu arah).
- **`createToken($name, $abilities)`** menerbitkan token Sanctum. `$abilities` =
  daftar permission dari role user → "scope per role" (`BUSINESS-FLOW.md §1`) ikut
  melekat di token. `plainTextToken` hanya muncul **sekali** (hanya hash-nya disimpan).

**Logout** — cabut token yang sedang dipakai:
```php
$token = $request->user()?->currentAccessToken();
if ($token instanceof PersonalAccessToken) {
    $token->delete();
}
```
Cek `instanceof PersonalAccessToken` karena di test `Sanctum::actingAs` memakai
`TransientToken` (tak punya `delete()`); hanya token nyata yang dicabut.

**`me`** mengembalikan `UserResource` (id, role, permission) untuk frontend tahu menu
& hak yang boleh ditampilkan.

> **Catatan test:** memverifikasi logout dengan memanggil `/me` lagi di *test yang
> sama* tidak andal — guard Sanctum mem-*memoize* user antar-request dalam satu proses
> test. Jadi bukti pencabutan = baris token hilang dari DB (`PersonalAccessToken::count()`).
> Di produksi tiap request proses baru, jadi token terhapus = pasti 401.

---

## L. Slice Policy (isolasi data per role)

`AppointmentPolicy` + endpoint `GET /api/v1/appointments/{id}`. Beda dengan
**permission** (boleh-tidaknya sebuah *aksi*, mis. `appointment.write`), **Policy**
menjawab boleh-tidaknya mengakses *record tertentu* (appointment ini milik siapa).

```php
final class AppointmentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;   // admin lolos semua
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return match (true) {
            $user->hasRole('planner')      => true,                                   // monitor lintas-company
            $user->hasRole('gate-officer') => $this->atOfficerTerminal($user, $appointment),
            $user->hasRole('transporter')  => $appointment->company_id === $user->company_id,
            $user->hasRole('driver')       => $appointment->driver_id === $user->id,
            default => false,
        };
    }
}
```
- **`before()`** dijalankan sebelum method lain; kembalikan `true` → langsung izinkan,
  `null` → lanjut ke method (`view`). Cara rapi memberi admin akses penuh.
- **`match (true)`** memilih cabang berdasarkan role; tiap role punya *scope* berbeda
  sesuai `BUSINESS-FLOW.md §1`.
- **Gate officer** dibatasi ke terminalnya:
  ```php
  $appointment->loadMissing('slotWindow.gate');               // eager, BUKAN lazy
  return $appointment->slotWindow?->gate?->terminal_id === $user->terminal_id;
  ```
  `loadMissing` dipakai (bukan akses langsung `$appointment->slotWindow->gate`) supaya
  tidak melanggar `preventLazyLoading`. Operator `?->` aman bila relasi null.

**Penegakan di route** — deklaratif, tanpa kode di controller:
```php
Route::get('appointments/{appointment}', ShowAppointmentController::class)
    ->middleware('can:view,appointment');
```
- `{appointment}` = **route model binding** → Laravel ambil `Appointment` by id
  (404 otomatis bila tak ada).
- `can:view,appointment` menjalankan `AppointmentPolicy::view($user, $appointment)`
  (403 otomatis bila ditolak). Controller hanya dijalankan kalau lolos.

> **Registrasi Policy?** Tidak perlu manual — Laravel auto-discover
> `App\Models\Appointment` → `App\Policies\AppointmentPolicy` lewat konvensi nama.

**Test** menegakkan tiap baris matriks: transporter company sendiri (200) vs lain
(403), driver self (200) vs lain (403), gate officer per terminal, admin/planner
semua, 401 tanpa token, 404 id hilang.

---

## M. Slice Reschedule & Cancel (optimistic lock)

Dua aksi yang mengubah appointment yang sudah ada. Keduanya menegakkan **state
machine** (hanya boleh sebelum truk tiba) di dalam transaksi ber-lock.

### M.1 Cancel
```php
DB::transaction(function () use ($appointment) {
    $locked = Appointment::query()->whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();
    if (! $locked->status->isCancellable()) throw InvalidAppointmentStateException::cannotCancel();  // 409
    $window = $this->slots->lockForUpdate($locked->slot_window_id);
    $this->appointments->markCancelled($locked);     // status CANCELLED + container.slot_window_id = NULL
    if ($window !== null) $this->slots->decrementBooked($window);   // kuota kembali
    return $locked;
}, attempts: 3);
AppointmentCancelled::dispatch($result, $result->slot_window_id);
```
- **Kunci baris appointment** (`lockForUpdate`) supaya tidak balapan dengan gate-in
  yang berjalan bersamaan (state dicek atas data terkunci).
- `markCancelled` me-**NULL**-kan `containers.slot_window_id` → kontainer lepas dari
  window, bisa dibooking lagi (lihat bagian C).

### M.2 Reschedule — optimistic lock + kunci dua window
```php
$locked = Appointment::query()->whereKey($id)->lockForUpdate()->firstOrFail();
if (! $locked->status->isReschedulable()) throw InvalidAppointmentStateException::cannotReschedule();
if ($locked->version !== $data->expectedVersion) throw new OptimisticLockException;   // 409

$ids = [$locked->slot_window_id, $data->slotWindowId];
sort($ids);                                  // urutan kunci konsisten → cegah DEADLOCK
foreach ($ids as $id) $locks[$id] = $this->slots->lockForUpdate($id);

if (! $to->isOpen())      throw SlotUnavailableException::closed();   // 409
if (! $to->hasCapacity()) throw SlotUnavailableException::full();      // 409

$this->appointments->moveToWindow($locked, $data->slotWindowId);      // slot_window_id, version++, pindah container
$this->slots->decrementBooked($from);
$this->slots->incrementBooked($to);
```
Kenapa begini:
- **Optimistic lock (`version`)** — transporter & planner bisa membuka form yang
  sama. Yang submit dengan `version` usang ditolak **409** (re-fetch lalu coba lagi).
  Beda dengan `lockForUpdate` (pessimistic) yang mengunci baris; di sini kita pakai
  keduanya: lock baris saat eksekusi, `version` untuk konflik antar-sesi/form.
- **Kunci dua window berurutan id** — kalau A→B dan B→A terjadi bersamaan, mengunci
  selalu dengan urutan id menaik mencegah *deadlock* saling-tunggu.
- **Pindah kuota** = `decrement` window lama + `increment` window baru dalam satu
  transaksi. Container ikut pindah (`moveToWindow` update `slot_window_id`); kalau
  kontainer sudah ada di window tujuan → unik-violation → `DuplicateBookingException`.

### M.3 Event → satu listener via interface
Booking, cancel, reschedule sama-sama mengubah ketersediaan slot. Daripada tiga
listener, ketiganya implement interface:
```php
interface AffectsSlotAvailability { public function windowIdsToRefresh(): array; }
```
Listener `InvalidateSlotAvailabilityCache::handle(AffectsSlotAvailability $event)`
membuang cache window terdampak. **Dispatcher Laravel mencocokkan listener lewat
interface event** (auto-discovery dari type-hint), jadi cukup satu listener untuk
ketiga event. `windowIdsToRefresh()`: booking `[baru]`, cancel `[lama]`, reschedule
`[lama, baru]`.

### M.4 Policy & route
Ability baru `update` (reschedule) & `cancel` di `AppointmentPolicy` (transporter
company sendiri; planner/admin override lintas-company). Route:
```php
Route::post('appointments/{appointment}/reschedule', RescheduleAppointmentController::class)
    ->middleware(['can:update,appointment', 'idempotency']);
Route::post('appointments/{appointment}/cancel', CancelAppointmentController::class)
    ->middleware(['can:cancel,appointment', 'idempotency']);
```

---

## N. Slice Gate-in / Gate-out (idempoten + transaksi gate)

Dua aksi petugas gate (`BUSINESS-FLOW.md §3.5/§3.6`). Alur state:
`CONFIRMED → ARRIVED → IN_PROGRESS` (gate-in, MVP satu aksi) → `COMPLETED` (gate-out).

### N.1 Action — guard idempoten di dalam lock
```php
// GateInAction::execute(Appointment $appointment, int $processedBy)
[$result, $changed] = DB::transaction(function () use ($appointment, $processedBy): array {
    $locked = Appointment::query()->whereKey($appointment->getKey())->lockForUpdate()->firstOrFail();

    if ($locked->isGatedIn()) {
        return [$locked, false];                 // double-tap / retry → no-op (idempoten)
    }
    if (! $locked->status->canGateIn()) {
        throw InvalidAppointmentStateException::cannotGateIn();   // 409
    }
    $this->appointments->recordGateIn($locked, $processedBy);     // buat IN + status IN_PROGRESS
    return [$locked, true];
}, attempts: 3);

if ($changed) {
    TruckGatedIn::dispatch($result);             // efek samping (broadcast + TOS) pasca-commit
}
```
**Kenapa kembalikan tuple `[model, $changed]`:** event hanya di-dispatch kalau benar
ada perubahan. Saat permintaan kembar (sudah `IN_PROGRESS`), kita tetap balas **200**
dengan state terkini tapi **tidak** memancarkan event/transaksi gate kedua. Idempotensi
berlapis: middleware `Idempotency-Key` (HTTP) → guard `isGatedIn()` (Action) → unik
`(appointment_id, type)` (DB).

`GateOutAction` sama polanya: no-op bila sudah `COMPLETED`, jika tidak butuh
`canGateOut()` (hanya `IN_PROGRESS`).

### N.2 Repository — tulis transaksi gate + dorong status
```php
public function recordGateIn(Appointment $appointment, int $processedBy): void
{
    $this->recordGateEvent($appointment, GateTransactionType::IN, $processedBy);
    // ARRIVED → IN_PROGRESS: dua save = dua entri Activity Log → audit transisi utuh.
    $appointment->status = AppointmentStatus::ARRIVED;     $appointment->save();
    $appointment->status = AppointmentStatus::IN_PROGRESS; $appointment->save();
}
```
**Kenapa dua `save()`:** state machine §2 hanya mengizinkan `CONFIRMED→ARRIVED→IN_PROGRESS`,
bukan lompat langsung. Dua langkah menghasilkan jejak audit yang jujur (truk *tiba*
lalu *mulai diproses*), meski di MVP terjadi dalam satu aksi.

### N.3 Policy `process` + route
```php
public function process(User $user, Appointment $appointment): bool
{
    return $user->hasRole('gate-officer') && $this->atOfficerTerminal($user, $appointment);
}
```
Gate officer hanya boleh memproses appointment di **terminalnya** (admin lolos via
`before()`). Route memakai middleware Policy + idempotency:
```php
Route::post('appointments/{appointment}/gate-in', GateInController::class)
    ->middleware(['can:process,appointment', 'idempotency']);
```

### N.4 Event TOS lewat interface bersama
`TruckGatedIn`/`TruckGatedOut` meng-implement `RecordsGateEvent`
(`gateAppointment()`, `gateType()`) — cermin pola `AffectsSlotAvailability`. Satu
listener `ProcessGateEventOnTos` menangkap keduanya lewat type-hint interface lalu
mendorong `ProcessGateEventJob` (detail efek eksternal di bagian P).

---

## O. Slice Job No-show & Reminder (scheduler + queue)

Dua job latar (`CLAUDE.md` hardening Queue). Nilai grace & lead diambil dari
**config**, bukan hardcode (`config/tas.php`).

### O.1 NoShowSweepJob — sapu berkala, kembalikan kuota
```php
final class NoShowSweepJob implements ShouldQueue
{
    public function middleware(): array
    {
        return [(new WithoutOverlapping('no-show-sweep'))->dontRelease()];  // 1 sweep jalan
    }

    public function handle(AppointmentRepositoryInterface $appointments, MarkNoShowAction $action): void
    {
        $grace = (int) config('tas.no_show_grace_minutes', 30);
        foreach ($appointments->dueForNoShow(Carbon::now(), $grace) as $appointment) {
            try {
                $action->execute($appointment);
            } catch (InvalidAppointmentStateException) {
                // balapan: keburu gate-in/cancel antara query & lock → lewati aman
            }
        }
    }
}
```
- **`WithoutOverlapping('no-show-sweep')->dontRelease()`:** kalau sweep sebelumnya
  masih jalan, eksekusi yang baru dibuang (bukan diantre ulang) — cegah dobel sapu.
- **Job = data layer (orkestrasi tipis), logika transisi tetap di Action.** Job hanya
  menemukan kandidat lalu memanggil `MarkNoShowAction` per appointment.

Pencarian kandidat di repository **portabel lintas-driver**:
```php
public function dueForNoShow(CarbonInterface $now, int $graceMinutes): Collection
{
    return Appointment::query()
        ->whereIn('status', [AppointmentStatus::BOOKED, AppointmentStatus::CONFIRMED])
        ->whereHas('slotWindow', fn ($q) => $q->whereDate('date', '<=', $now->toDateString()))
        ->with('slotWindow')->get()
        ->filter(function (Appointment $a) use ($now, $graceMinutes): bool {
            $deadline = $a->slotWindow->date->copy()
                ->setTimeFromTimeString($a->slotWindow->end_time)
                ->addMinutes($graceMinutes);
            return $deadline->lessThan($now);     // window.end + grace sudah lewat
        })->values();
}
```
**Kenapa saring kasar di DB lalu refine di PHP:** menggabung `date` + `end_time` jadi
satu timestamp via ekspresi SQL berbeda antara SQLite (test) & MySQL (prod). Filter
PHP (Carbon) menjaga query tetap portabel.

`MarkNoShowAction` = kembar `CancelAppointmentAction` (lock baris + window, cek
`canMarkNoShow()`, `markNoShow()` me-NULL-kan container, `decrementBooked` balikin
kuota) tapi status akhir `NO_SHOW` + event `AppointmentNoShow` (impl
`AffectsSlotAvailability` → cache & broadcast otomatis ikut).

**Penjadwalan** (`routes/console.php`):
```php
Schedule::job(new NoShowSweepJob)->everyFiveMinutes();
```

### O.2 AppointmentReminderJob — unik per appointment
```php
final class AppointmentReminderJob implements ShouldBeUnique, ShouldQueue
{
    public function uniqueId(): string { return (string) $this->appointmentId; }

    public function handle(): void
    {
        $appointment = Appointment::query()->with(['driver','slotWindow'])->find($this->appointmentId);
        if ($appointment === null) return;
        // Hanya ingatkan bila masih menunggu kedatangan (tahan reschedule/cancel/no-show).
        if (! in_array($appointment->status, [AppointmentStatus::BOOKED, AppointmentStatus::CONFIRMED], true)) {
            return;
        }
        Notification::send($appointment->driver, new AppointmentReminderNotification($appointment));
    }
}
```
- **`ShouldBeUnique` + `uniqueId` = appointment id:** booking yang dobel-tap tidak
  menjadwalkan dua reminder.
- **Cek status saat job berjalan** (bukan saat dijadwalkan) membuat reminder *self-healing*:
  kalau appointment sudah batal/pindah, job diam.

Dijadwalkan **delayed** oleh listener saat booking:
```php
// ScheduleAppointmentReminder::handle(AppointmentBooked $event)
$remindAt = $window->date->copy()->setTimeFromTimeString($window->start_time)
    ->subMinutes((int) config('tas.reminder_lead_minutes', 120));
AppointmentReminderJob::dispatch($appointment->id)
    ->delay($remindAt->isFuture() ? $remindAt : Carbon::now());     // mepet → kirim segera
```

---

## P. Slice Realtime (broadcast Reverb + seam TOS)

Tujuan: sisa kuota & antrian gate tampil **live** (`slot.{gateId}`,
`gate.queue.{terminalId}`), dan gate event terdorong ke TOS lewat seam yang bisa di-swap.

### P.1 Broadcast event payload datar
```php
final class SlotAvailabilityChanged implements ShouldBroadcast
{
    public function broadcastOn(): array { return [new PrivateChannel("slot.{$this->gateId}")]; }
    public function broadcastAs(): string { return 'slot.availability.changed'; }
    public function broadcastWith(): array { return ['gate_id' => $this->gateId, 'windows' => $this->windows]; }
}
```
**Kenapa event broadcast TERPISAH dari event domain:** `AppointmentBooked` dll.
membawa model penuh & dipakai banyak listener. Event broadcast membawa **payload datar**
(array sisa kuota, bukan model) → kontrak WebSocket stabil untuk frontend dan tak bocor
struktur internal.

### P.2 Listener menjembatani domain → broadcast
```php
// BroadcastSlotAvailability::handle(AffectsSlotAvailability $event)
$windows = SlotWindow::query()->whereKey($event->windowIdsToRefresh())
    ->get(['id','gate_id','date','capacity','booked_count','status']);
foreach ($windows->groupBy('gate_id') as $gateId => $group) {
    SlotAvailabilityChanged::dispatch((int) $gateId, $group->map(fn (SlotWindow $w) => [
        'id' => $w->id, 'remaining' => $w->remaining(), /* ... */
    ])->values()->all());
}
```
Listener ini meng-handle **interface** `AffectsSlotAvailability` — jadi otomatis ikut
**semua** event slot yang sudah ada (booking/cancel/reschedule/no-show/open/close)
tanpa perubahan. `BroadcastGateQueue` serupa untuk `RecordsGateEvent`.

### P.3 Channel authorization
```php
// routes/channels.php
Broadcast::channel('slot.{gateId}', fn (User $user, int $gateId) => $user->can('slot.read'));
Broadcast::channel('gate.queue.{terminalId}', function (User $user, int $terminalId): bool {
    if ($user->hasAnyRole(['admin','planner','driver'])) return true;
    return $user->hasRole('gate-officer') && $user->terminal_id === $terminalId;
});
```

### P.4 Seam TOS (efek eksternal yang bisa di-swap)
```php
interface GateEventGateway { public function push(Appointment $appointment, GateTransactionType $type): void; }
```
`LoggingGateEventGateway` (default, hanya log) di-bind di `AppServiceProvider`.
`ProcessGateEventJob` memanggilnya dengan **guard idempoten**:
```php
public function handle(GateEventGateway $tos): void
{
    $appointment = Appointment::query()->find($this->appointmentId);
    if ($appointment === null) return;
    $recorded = $appointment->gateTransactions()->where('type', $this->type->value)->exists();
    if (! $recorded) return;                 // event kembar / retry → jangan push dua kali
    $tos->push($appointment, $this->type);
}
```
Job `ShouldBeUnique` + `WithoutOverlapping` per appointment. Saat integrasi TOS nyata
tiba, cukup ganti binding `GateEventGateway` — Action/Job tak berubah.

> **Catatan test:** `phpunit.xml` set `BROADCAST_CONNECTION=null` agar event
> `ShouldBroadcast` tidak menembak driver `log`. Test memverifikasi *dispatch* event
> broadcast (Event::fake parsial) + nama channel, bukan koneksi WebSocket nyata.

---

## Q. Slice Endpoint Pendukung (me/today + utilisasi)

Dua endpoint baca (`BUSINESS-FLOW.md §3.4 & §3.7`).

### Q.1 Jadwal hari-H driver
```php
// AppointmentRepository::todayForDriver(int $driverId, string $date)
return Appointment::query()
    ->where('driver_id', $driverId)
    ->whereHas('slotWindow', fn ($q) => $q->whereDate('date', $date))
    ->with(['truck','driver','company','slotWindow.gate','containers'])   // eager → cegah N+1
    ->get();
```
Otorisasi lewat `TodayAppointmentsRequest::authorize()` → scope `appointment.read.self`.

### Q.2 Laporan utilisasi — agregat via `withCount`
```php
// SlotRepository::utilization(int $gateId, string $date)
return SlotWindow::query()->where('gate_id', $gateId)->whereDate('date', $date)
    ->withCount([
        'appointments as completed_count' => fn ($q) => $q->where('status', AppointmentStatus::COMPLETED->value),
        'appointments as no_show_count'   => fn ($q) => $q->where('status', AppointmentStatus::NO_SHOW->value),
        'appointments as cancelled_count' => fn ($q) => $q->where('status', AppointmentStatus::CANCELLED->value),
        'appointments as active_count'    => fn ($q) => $q->whereIn('status', $active),
    ])->orderBy('start_time')->get();
```
- **`withCount` dengan alias + closure:** satu query menghasilkan beberapa hitungan
  per window (completed/no_show/cancelled/active) tanpa N+1.
- Resource pakai `whenCounted('completed')` (baca atribut `completed_count`).
- Controller menambah ringkasan total via `->additional(['meta' => ['summary' => ...]])`
  pada `AnonymousResourceCollection` → output tetap lewat Resource.
- Otorisasi `UtilizationReportRequest`: **planner/admin saja** (agregat lintas-company);
  laporan company-scoped transporter terpisah/menyusul.

---

## R. Slice Slot-window Management (open/close)

Planner membuka/menutup jendela slot (`BUSINESS-FLOW.md §3.1`). Inilah hulu dari
seluruh alur booking.

### R.1 Open — create + unik sebagai jaring
```php
// OpenSlotWindowAction::execute(OpenSlotWindowData $data)
try {
    $window = $this->slots->create($data);          // status OPEN, booked_count 0
} catch (UniqueConstraintViolationException) {
    throw new DuplicateSlotWindowException;          // unik (gate_id,date,start_time) → 409
}
SlotWindowOpened::dispatch($window->id);
```
Validasi di `OpenSlotWindowRequest`: `date` ≥ hari ini, jam `H:i:s` + `end_time` setelah
`start_time`, `capacity` 1..1000. Otorisasi `slot.manage`.

### R.2 Close — status, bukan delete; ber-lock
```php
// CloseSlotWindowAction::execute(SlotWindow $window)
$closed = DB::transaction(function () use ($window): SlotWindow {
    $locked = SlotWindow::query()->whereKey($window->getKey())->lockForUpdate()->firstOrFail();
    if ($locked->status === SlotWindowStatus::CLOSED) return $locked;   // idempoten
    $this->slots->markClosed($locked);
    return $locked;
}, attempts: 3);
SlotWindowClosed::dispatch($closed->id);
```
- **CLOSED, bukan delete:** appointment yang sudah ada di window itu tetap valid;
  hanya booking baru yang ditolak (`isOpen()` di `BookAppointmentAction`).
- **`lockForUpdate`:** menutup sambil ada booking yang memegang window → kita menunggu
  lock booking lepas dulu, mencegah booking menyelinap setelah window "ditutup".

### R.3 Gratis dapat realtime
`SlotWindowOpened`/`SlotWindowClosed` meng-implement `AffectsSlotAvailability`, jadi
**listener cache-invalidate + broadcast yang sudah ada langsung jalan** — window baru
muncul / window tertutup hilang dari endpoint `availability`, tanpa listener tambahan.
Ini buah dari mendesain efek samping di sekitar *interface*, bukan kelas konkret.

---

## S. Slice Hardening (rate limiting)

Memenuhi kontrak `CLAUDE.md` → Hardening §rate limit yang sebelumnya belum terpasang.
Tiga **named limiter** didaftarkan sekali, lalu dipasang via alias `throttle:*` di route.

### S.1 Nilai di config (tunable)
```php
// config/tas.php
'rate_limits' => [
    'login'   => (int) env('TAS_RL_LOGIN', 5),    // anti brute-force
    'api'     => (int) env('TAS_RL_API', 60),      // batas umum endpoint ber-auth
    'booking' => (int) env('TAS_RL_BOOKING', 10),  // lebih ketat: anti bot borong slot
],
```

### S.2 Daftarkan limiter (provider)
```php
// AppServiceProvider::configureRateLimiters()
RateLimiter::for('login', fn (Request $r): Limit => Limit::perMinute($limits['login'])
    ->by($r->input('email').'|'.$r->ip()));            // kunci per kredensial, bukan global

RateLimiter::for('api', fn (Request $r): Limit => Limit::perMinute($limits['api'])
    ->by((string) ($r->user()?->getAuthIdentifier() ?? $r->ip())));

RateLimiter::for('booking', fn (Request $r): Limit => Limit::perMinute($limits['booking'])
    ->by((string) ($r->user()?->getAuthIdentifier() ?? $r->ip())));
```
- **`login` dikunci `email|ip`:** brute-force satu akun tak menghabiskan kuota akun lain,
  dan tak bisa di-bypass cuma dengan ganti target email.
- **`booking` dikunci user id:** "stricter than `api`" → satu transporter tak bisa
  membombardir endpoint booking memborong slot, tanpa mengganggu transporter lain.

### S.3 Pasang di route
```php
// routes/api.php
Route::post('login', LoginController::class)->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
    Route::post('appointments', BookAppointmentController::class)
        ->middleware(['throttle:booking', 'idempotency']);   // booking = api + booking
    // ...
});
```
Lewat batas → `429 Too Many Requests` (+ header `Retry-After`). Test di
`tests/Feature/Hardening/` (login 429 + keyed-by-email, booking 429 per user).
`CACHE_STORE=array` di test → counter limiter ter-reset tiap test (tak ada bleed antar-test).

### S.4 Yang sengaja DITUNDA — token abilities
Login mencetak token dengan abilities = **seluruh** permission role; tak ada jalur token
ber-scope sempit. Maka middleware `abilities:` (Sanctum) akan **redundan** dengan Policy +
FormRequest `can()` — ia tak pernah bisa menolak yang otorisasi role belum tolak. Ditegakkan
nanti saat aplikasi benar-benar menerbitkan token sempit (mis. token mobile read-only).
Lihat `HANDOVER.md` → *Senior review (2026-06-28)*.

### S.5 Backlog hardening (3 fix dari review)

**1. Optimistic `version` di cancel (opsional, backward compatible).** `reschedule` sudah
cek `version`; `cancel` dulu tidak → edit konkuren tak terdeteksi. Sekarang:
```php
// CancelAppointmentRequest: 'version' => ['nullable','integer','min:1']
// CancelAppointmentAction::execute(Appointment $a, ?int $expectedVersion = null)
if ($expectedVersion !== null && $locked->version !== $expectedVersion) {
    throw new OptimisticLockException;   // 409 version_conflict
}
```
Tanpa `version` → cancel tetap jalan (klien lama aman). Dengan `version` usang → 409.

**2. `dueForNoShow` dipindai chunked (memori terbatas).** Dulu `->get()` seluruh kandidat
ke memori lalu filter. Sekarang `chunkById` — hanya N baris di-hydrate per iterasi:
```php
$chunkSize = (int) config('tas.no_show_chunk_size', 500);   // tunable
Appointment::query()->whereIn('status', [...])->whereHas('slotWindow', ...)->with('slotWindow')
    ->chunkById($chunkSize, function (EloquentCollection $chunk) use (...): void {
        foreach ($chunk as $a) { /* hitung window.end + grace; push bila lewat */ }
    });
```
Aman: `dueForNoShow` hanya **membaca** (mutasi status terjadi belakangan di job), jadi
tak ada konflik chunk-while-mutating. Test menyetel `no_show_chunk_size=2` + 5 due →
membuktikan benar lintas-batas chunk.

**3. Idempotency: lock TTL 10→60 dtk + hash key.** Lock 10 dtk bisa lebih pendek dari
durasi handler berat (booking + broadcast) → kedaluwarsa di tengah → duplikat menyelinap.
```php
$lockSeconds = (int) config('tas.idempotency.lock_seconds', 60);   // > worst-case handler
$cacheKey = 'idem:'.$scope.':'.hash('sha256', $key);   // bounded lintas store, anti injeksi
```
Test: replay tetap benar walau Idempotency-Key panjang/berkarakter aneh (buah hashing);
request kembar saat lock ditahan → 409.

---

## T. Slice Read Referensi (gates + fleet)

Master data read untuk frontend (dropdown gate, form booking). Tetap menghormati
layer: **tanpa query di controller** → lewat repository ber-interface.

### T.1 GET /gates
`GateRepositoryInterface::all(?int $terminalId)` (impl `GateRepository`, di-bind di
`AppServiceProvider`) → `Gate::query()->when($terminalId, ...)->orderBy(...)`.
Controller invokable `ListGatesController` panggil repo, balikan `GateResource::collection`.
Otorisasi `slot.read` di `ListGatesRequest` (filter opsional `terminal` ber-`exists`).

### T.2 GET /me/fleet
`FleetRepositoryInterface`: `trucksForCompany($id)` + `driversForCompany($id)`
(sopir = `User::query()->where('company_id',$id)->role('driver','api')` — Spatie scope).
`MyFleetController` ambil `company_id` dari user (null → 403), balikan
`{data:{trucks:TruckResource[], drivers:DriverResource[]}}`. Otorisasi `fleet.manage`
(`FleetRequest`). Reuse `TruckResource`/`DriverResource` yang sudah ada.

> **Kenapa repository untuk read sederhana?** Konsistensi: semua akses data lewat
> repo ber-interface (mudah di-mock/swap), dan kontrak melarang query di controller.
> Beda dengan Slot/Appointment repo, ini read murni (tanpa lock/transaksi).

---

## U. Read endpoints persona (booking list + gate queue)

Dua endpoint read ber-scope untuk meng-unblock UI persona. Pola sama: scope di
repository (bukan controller), otorisasi di FormRequest, output via Resource.

### U.1 GET /me/appointments — daftar "Booking Saya" transporter
`AppointmentRepository::forCompany($companyId, ?$status)` → `where('company_id', …)`
+ filter status opsional, eager-load relasi tampilan, `orderByDesc('id')` (terbaru
dulu). `MyAppointmentsController` cek `company_id` (null → 403, mis. planner tak punya
company) lalu balikan `AppointmentResource::collection`. Filter status ber-`Rule::enum`.

> **Kenapa wajib `company_id` (403 untuk planner)?** Endpoint ini *self-service*
> transporter (mirip `/me/fleet`). Planner/admin lihat lintas-company lewat laporan
> agregat (`/reports/utilization`), bukan daftar mentah ini.

### U.2 GET /gate/queue — antrian gate-officer
`AppointmentRepository::queueForTerminal($terminalId, $date)` → status `CONFIRMED`
(siap gate-in) & `IN_PROGRESS` (siap gate-out), disaring ke terminal officer:
```php
->whereHas('slotWindow', fn ($q) => $q->whereDate('date', $date))
->whereRelation('slotWindow.gate', 'terminal_id', $terminalId)
```
`GateQueueController` cek `terminal_id` (null → 403). Output `AppointmentResource`
(+ `gateIn`/`gateOut` di-eager-load untuk jejak waktu).

> **Kenapa `whereRelation('slotWindow.gate', …)` bukan nested `whereHas`?** Pada
> PHPStan level 8, parameter closure `whereHas('gate', fn ($g) => …)` yang ber-nested
> kehilangan tipe model (`$g` jadi `Builder<Model>` generik) → `terminal_id` dianggap
> kolom asing. `whereRelation` dot-notation menghindari closure → tipe aman.
>
> **Kenapa urutan tak di-`sortBy` di repo?** `->sortBy(fn ($a) => $a->slotWindow->start_time)`
> membuat Larastan *flip-flop* antara `nullsafe.neverNull` & `property.nonObject` (tak
> ada bentuk yang lolos). Solusi: repo balikan tak terurut, **klien yang mengurutkan**
> by `start_time` (konsisten dgn jadwal driver). Pelajaran: hindari sort by kolom
> relasi di Collection saat analisis statis ketat.

---

## V. Admin CRUD master data (terminal/gate/company/user)

CRUD master data untuk role **admin** (`BUSINESS-FLOW.md §1`). Beda dengan slice domain
(booking/gate) yang penuh lock & state machine, ini CRUD lurus — tapi tetap **menghormati
layer yang sama**: controller invokable tipis → Action 1-tugas → Repository ber-interface
→ Resource keluar. Empat entitas: `Terminal`, `Gate`, `TransportCompany`, `User`.

### V.1 Permission & route group
Permission baru di `RolePermissionSeeder` (guard `api`): `terminal.manage`, `gate.manage`,
`company.manage` (plus `user.manage` yang sudah ada). Semua masuk `admin → *`. Route
dikelompok di bawah `admin/`:
```php
// routes/api.php (di dalam group auth:sanctum)
Route::prefix('admin')->group(function (): void {
    Route::get('terminals', ListTerminalsController::class);
    Route::post('terminals', CreateTerminalController::class);
    Route::get('terminals/{terminal}', ShowTerminalController::class);
    Route::put('terminals/{terminal}', UpdateTerminalController::class);
    Route::delete('terminals/{terminal}', DeleteTerminalController::class);
    // pola sama untuk gates, companies, users (20 controller invokable total)
});
```
Otorisasi tiap aksi ditegakkan di **FormRequest** (`UpsertTerminalRequest`, dst.) via
`->can('terminal.manage')` — bukan di controller.

### V.2 Hapus aman — `EntityInUseException` (409)
Aturan inti CRUD ini: **jangan biarkan data yatim**. Hapus ditolak bila masih ada dependen,
lewat exception ber-`render()` yang memetakan ke **409** (bukan cascade delete yang
menghancurkan riwayat appointment):
```php
final class EntityInUseException extends RuntimeException
{
    public static function terminal(): self { return new self('Terminal masih memiliki gate...'); }
    // gate(), company() serupa
    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'error' => 'entity_in_use'], 409);
    }
}
```
Cek dependen ada di **repository** (data layer), bukan Action:
```php
// TerminalRepository::delete()
public function delete(Terminal $terminal): void
{
    if ($terminal->gates()->exists()) { throw EntityInUseException::terminal(); }
    $terminal->delete();
}
```
Guard per entitas: terminal←gate · gate←slot window · company←user/appointment ·
user←diri sendiri (yang terakhir `abort(422)` di `UserRepository::delete($user, $actor)`).

### V.3 User: password & role sync
`UserData` (DTO) memuat `password` **nullable** — wajib saat create, opsional saat update:
```php
// UserRepository::update()
$fields = ['name' => $data->name, 'email' => $data->email, /* terminal_id, company_id */];
if ($data->password !== null && $data->password !== '') {
    $fields['password'] = Hash::make($data->password);     // hanya hash bila diisi
}
$user->update($fields);
$user->syncRoles([$data->role]);                            // Spatie: role tunggal
return $user->fresh(['roles', 'terminal', 'company']) ?? $user;
```
- **Password hanya di-hash bila diisi** → edit user tanpa ganti password tak menimpa hash lama.
- **`syncRoles`** (Spatie) mengganti role lama dengan yang baru.
- **`fresh([...])`** memuat ulang relasi setelah `syncRoles` supaya Resource tak lazy-load.
- Hapus user → cabut semua token dulu (`$user->tokens()->delete()`), lalu hapus.

### V.4 PHPStan level 8 — jebakan route binding & relasi nullable
CRUD ini memunculkan pola PHPStan yang berulang (semua diperbaiki tanpa `@phpstan-ignore`):
- **`$this->route('terminal')` bertipe `object|string`**, bukan `Terminal` → tak punya `->id`.
  Solusi: `$model = $this->route('terminal'); $id = $model instanceof Terminal ? $model->id : null;`
  (dipakai di rule `unique` agar abaikan diri sendiri saat update).
- **`$user->roles->first()?->name` gagal** (`Model` tak deklarasi `$name`) →
  `->getAttribute('name')` di `AdminUserResource`.
- **Relasi nullable di `whenLoaded`** (`$this->terminal->id` saat `Terminal|null`) →
  cek eksplisit `$this->terminal === null ? null : [...]`.
- **`->role($role, 'api')` di dalam `when()` closure** kehilangan narrowing `string|null`
  → ganti ke `if ($role !== null) { $query->role($role, 'api'); }` eksplisit.

### V.5 Test
`tests/Feature/Admin/{Terminal,Gate,Company,User}CrudTest.php` — happy path + edge tiap
entitas (list/create/show/update/delete, **409 saat ada dependen**, 422 self-delete,
403 tanpa permission). Catatan Pest: di closure `function (): void { ... }` pakai
**`$this->seed(RolePermissionSeeder::class)`** (method TestCase), bukan global `seed()`
yang hanya tersedia di arrow function `fn () => seed(...)`.

> **Frontend admin** (AdminPage 4-tab, `useAdmin` composable, invalidasi cache): di
> `docs/FRONTEND.md §4`.

---

## Frontend (Vue SPA)

Penjelasan kode frontend dipisah ke **`docs/FRONTEND.md`** (arsitektur SPA, pola
TanStack Query, tiap halaman/komponen + *kenapa*, pola test Vitest). CODE-WALKTHROUGH
ini fokus backend.

---

## Penutup: pola yang akan terus dipakai

Slice booking di atas menjadi **cetak biru** untuk seluruh slice backend (gate-in/out,
no-show/reminder, realtime, endpoint pendukung, slot-window management, rate-limit hardening,
read referensi, read persona, admin CRUD master data):
- **Action** (`final class`, `declare(strict_types=1)`) memanggil enum state machine
  + `DB::transaction` + `lockForUpdate`; efek samping lewat **Event** pasca-commit.
- **DTO** (Laravel Data) untuk input, **Resource** untuk output, **FormRequest**
  untuk validasi+otorisasi — semua bertipe ketat (lolos PHPStan 8).
- **Repository** (interface + impl, di-bind di provider) untuk semua akses data.
- **TDD**: tulis test (happy + edge: 409 penuh, idempotency, `version` clash) dulu.

Referensi lanjutan: `CLAUDE.md` (aturan), `BUSINESS-FLOW.md` (domain),
`SETUP-GUIDE.md` (operasional), `HANDOVER.md` (langkah berikutnya).
