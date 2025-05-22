<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Admin role gets all permissions
        $admin = Role::findByName('admin');
        $permissions = Permission::all();
        $admin->givePermissionTo($permissions);

        // Manager permissions
        $manager = Role::findByName('manager');
        $manager->givePermissionTo([
            'sms.create',
            'sms.edit',
            'sms.view',
            'sms.delete',
            'sms.view.all',
            'report.view',
            'report.view.all',
            'report.download',
            'contact.create',
            'contact.edit',
            'contact.view',
            'contact.delete',
        ]);

        $agent = Role::findByName('agent');
        $agent->givePermissionTo([
            'sms.create',
            'sms.edit',
            'sms.view',
            'sms.delete',
            'sms.view.own',
            'report.view',
            'report.view.own',
            'report.download',
            'contact.create',
            'contact.edit',
            'contact.view',
        ]);

        // Update DatabaseSeeder to include this
        $this->command->info('Role-Permission assignments seeded!');
    }
}
