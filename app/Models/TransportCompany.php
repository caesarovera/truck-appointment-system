<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TransportCompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransportCompany extends Model
{
    /** @use HasFactory<TransportCompanyFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['code', 'name'];

    /** @return HasMany<Truck, $this> */
    public function trucks(): HasMany
    {
        return $this->hasMany(Truck::class, 'company_id');
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'company_id');
    }

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'company_id');
    }
}
