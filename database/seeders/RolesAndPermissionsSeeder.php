<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Permissions
        $permissions = [
            'manage malls', 'approve malls', 'manage subscriptions', // Super Admin
            'manage products', 'manage branches', 'manage cashier', // Mall Owner
            'view products', 'create orders', 'view own orders', // Customer
            'scan qr', 'checkout' // Cashier
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create Roles and assign permissions
        $superAdmin = Role::create(['name' => 'super-admin']);
        $superAdmin->givePermissionTo(Permission::all());

        $mallOwner = Role::create(['name' => 'mall-owner']);
        $mallOwner->givePermissionTo(['manage products', 'manage branches', 'manage cashier', 'view own orders']);

        $customer = Role::create(['name' => 'customer']);
        $customer->givePermissionTo(['view products', 'create orders', 'view own orders']);

        $cashier = Role::create(['name' => 'cashier']);
        $cashier->givePermissionTo(['scan qr', 'checkout', 'view products']);
    }
}
