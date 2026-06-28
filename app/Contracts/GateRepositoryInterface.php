<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Gate;
use Illuminate\Support\Collection;

interface GateRepositoryInterface
{
    /**
     * Daftar gate (referensi untuk dropdown). Bila $terminalId diisi → hanya
     * gate di terminal itu.
     *
     * @return Collection<int, Gate>
     */
    public function all(?int $terminalId): Collection;
}
