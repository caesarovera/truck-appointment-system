<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property int|null $terminal_id
 * @property int|null $company_id
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected string $guard_name = 'api';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'terminal_id',
        'company_id',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Gate officer's assigned terminal.
     *
     * @return BelongsTo<Terminal, $this>
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }

    /**
     * Transporter/driver's company.
     *
     * @return BelongsTo<TransportCompany, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(TransportCompany::class, 'company_id');
    }

    /** @return HasMany<Appointment, $this> */
    public function driverAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'driver_id');
    }
}
