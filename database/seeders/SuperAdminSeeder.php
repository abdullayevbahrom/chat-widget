<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = config('super_admin.email', 'admin@example.com');
        $password = config('super_admin.password', 'changeme-secure-password-' . bin2hex(random_bytes(8)));

        // Prevent accidental deployment with default credentials
        if (
            $password === 'password' ||
            $password === 'changeme-secure-password' ||
            str_starts_with($password, 'changeme-secure-password-')
        ) {
            throw new RuntimeException(
                'SECURITY ERROR: Default password is not allowed. '
                . 'Please set a strong password via environment variable SUPER_ADMIN_PASSWORD.'
            );
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Super Admin',
                'password' => Hash::make($password),
                'is_super_admin' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
