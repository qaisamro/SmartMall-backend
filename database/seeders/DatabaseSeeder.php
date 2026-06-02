<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        // Create Super Admin
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@smartmall.ps',
            'password' => Hash::make('password'),
        ]);
        $admin->assignRole('super-admin');

        // Create a Sample Mall Owner
        $owner = User::create([
            'name' => 'Mall Owner',
            'email' => 'owner@smartmall.ps',
            'password' => Hash::make('password'),
        ]);
        $owner->assignRole('mall-owner');
    }
}
