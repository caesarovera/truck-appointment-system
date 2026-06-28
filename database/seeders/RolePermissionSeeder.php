<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'user.manage',
            'terminal.manage',
            'gate.manage',
            'company.manage',
            'slot.manage',
            'slot.read',
            'appointment.read',
            'appointment.read.self',
            'appointment.write',
            'appointment.override',
            'fleet.manage',
            'gate.process',
            'report.read',
            'audit.read',
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'api');
        }

        $matrix = [
            'admin' => $permissions, // full
            'planner' => ['slot.manage', 'slot.read', 'appointment.read', 'appointment.override', 'report.read', 'audit.read'],
            'gate-officer' => ['gate.process', 'appointment.read', 'slot.read'],
            'transporter' => ['appointment.write', 'appointment.read', 'fleet.manage', 'slot.read', 'report.read', 'audit.read'],
            'driver' => ['appointment.read.self'],
        ];

        foreach ($matrix as $roleName => $perms) {
            $role = Role::findOrCreate($roleName, 'api');
            $role->syncPermissions($perms);
        }
    }
}
