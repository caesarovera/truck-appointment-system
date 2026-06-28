<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Ketersediaan slot live: siapa pun yang boleh membaca slot (admin/planner/gate/
// transporter). Lihat docs/BUSINESS-FLOW.md §1.
Broadcast::channel('slot.{gateId}', function (User $user, int $gateId): bool {
    return $user->can('slot.read');
});

// Antrian gate per terminal: admin/planner & driver boleh pantau; gate officer
// hanya terminal tempat ia ditugaskan.
Broadcast::channel('gate.queue.{terminalId}', function (User $user, int $terminalId): bool {
    if ($user->hasAnyRole(['admin', 'planner', 'driver'])) {
        return true;
    }

    return $user->hasRole('gate-officer') && $user->terminal_id === $terminalId;
});
