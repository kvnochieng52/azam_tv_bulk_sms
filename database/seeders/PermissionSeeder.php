<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */ public function run()
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            'user.create',
            'user.edit',
            'user.view',
            'user.delete',
            'role.create',
            'role.edit',
            'role.view',
            'role.delete',
            'permission.create',
            'permission.edit',
            'permission.view',
            'permission.delete',
            'sms.create',
            'sms.edit',
            'sms.view',
            'sms.delete',
            'sms.view.all',
            'sms.view.own',
            'report.view',
            'report.view.all',
            'report.view.own',
            'report.download',
            'contact.create',
            'contact.edit',
            'contact.view',
            'contact.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web'
            ]);
        }

        $this->command->info('Permissions seeded successfully!');
    }
}
