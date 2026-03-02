<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OwnerSeeder extends Seeder
{
    public function run(): void
    {
        if (User::where('role', User::ROLE_ADMIN)->exists()) {
            return;
        }
        User::create([
            'name' => 'Admin',
            'email' => env('OWNER_EMAIL', 'owner@studiosalaru.com'),
            'password' => Hash::make(env('OWNER_PASSWORD', 'password')),
            'role' => User::ROLE_ADMIN,
        ]);
    }
}
