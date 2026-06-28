<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TerminalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Terminal extends Model
{
    /** @use HasFactory<TerminalFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['code', 'name'];

    /** @return HasMany<Gate, $this> */
    public function gates(): HasMany
    {
        return $this->hasMany(Gate::class);
    }
}
