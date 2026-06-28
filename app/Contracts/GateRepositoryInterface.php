<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DataTransferObjects\Admin\GateData;
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

    /** @return Collection<int, Gate> with terminal eager-loaded */
    public function allForAdmin(?int $terminalId = null): Collection;

    public function findForAdmin(int $id): Gate;

    public function create(GateData $data): Gate;

    public function update(Gate $gate, GateData $data): Gate;

    public function delete(Gate $gate): void;
}
