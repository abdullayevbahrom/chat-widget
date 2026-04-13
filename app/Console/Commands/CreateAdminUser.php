<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create';
    protected $description = 'Create a new admin (super admin) user';

    public function handle()
    {
        $email = 'widget-chat@gmail.com';
        $password = '!1Widgetchat';
        $name = 'Admin';

        if (User::where('email', $email)->exists()) {
            $this->error("User with email '{$email}' already exists!");
            return 0;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_super_admin' => true,
            'email_verified_at' => now(),
        ]);

        $this->info("Admin user created successfully!");
        $this->table(
            ['ID', 'Name', 'Email', 'Is Super Admin'],
            [
                [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->is_super_admin ? 'Yes' : 'No',
                ]
            ]
        );

        return 0;
    }

    protected function getOptions()
    {
        return [
            ['email', null, null, 'Email address', null],
            ['password', null, null, 'Password', null],
            ['name', null, null, 'User name', null],
        ];
    }
}
