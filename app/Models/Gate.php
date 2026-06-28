<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gate extends Model
{
    /** @use HasFactory<GateFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['terminal_id', 'code', 'name'];

    /** @return BelongsTo<Terminal, $this> */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }

    /** @return HasMany<SlotWindow, $this> */
    public function slotWindows(): HasMany
    {
        return $this->hasMany(SlotWindow::class);
    }
}
