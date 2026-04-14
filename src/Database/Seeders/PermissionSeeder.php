<?php

namespace Nawasara\Keycloak\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'keycloak.user.view',
            'keycloak.user.manage',
            'keycloak.user.reset_password',
            'keycloak.client.view',
            'keycloak.client.manage',
            'keycloak.client.reveal_secret',
            'keycloak.session.view',
            'keycloak.session.revoke',
            'keycloak.event.view',
            'keycloak.sync.execute',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $role = Role::where('name', 'developer')->first();

        if ($role) {
            $role->givePermissionTo($permissions);
        }
    }
}
