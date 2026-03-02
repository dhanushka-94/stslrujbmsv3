<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SampleUsersSeeder extends Seeder
{
    /** Same password for all sample users (change in production). */
    private const SAMPLE_PASSWORD = 'password';

    /**
     * Create one sample user per role for testing all permission levels.
     */
    public function run(): void
    {
        $samples = [
            [User::ROLE_ADMIN, 'Admin Sample', 'admin@studiosalaru.com'],
            [User::ROLE_MANAGER, 'Manager Sample', 'manager@studiosalaru.com'],
            [User::ROLE_EDITOR, 'Editor Sample', 'editor@studiosalaru.com'],
            [User::ROLE_PRINTER, 'Printer Sample', 'printer@studiosalaru.com'],
            [User::ROLE_SALES, 'Sales Sample', 'sales@studiosalaru.com'],
            [User::ROLE_DELIVERY, 'Delivery Sample', 'delivery@studiosalaru.com'],
        ];

        foreach ($samples as [$role, $name, $email]) {
            User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make(self::SAMPLE_PASSWORD),
                    'role' => $role,
                ]
            );
        }
    }
}
