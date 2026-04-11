<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateUser extends Command
{
    protected $signature = 'app:create-user {email} {password} {--slug=} {--verified}';
    protected $description = 'Create test tenant user';

    public function handle()
    {
        $email = $this->argument('email');
        $password = $this->argument('password');
        $slug = $this->option('slug') ?? explode('@', $email)[0];
        $verified = $this->option('verified');

        $this->info("Creating tenant user: $email");

        try {
            $result = DB::transaction(function () use ($email, $password, $slug, $verified) {
                $tenant = Tenant::firstOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => explode('@', $email)[0],
                        'is_active' => true,
                        'plan' => 'free',
                    ]
                );

                $userData = [
                    'name' => explode('@', $email)[0],
                    'password' => bcrypt($password),
                    'tenant_id' => $tenant->id,
                    'is_super_admin' => false,
                ];
                
                if ($verified) {
                    $userData['email_verified_at'] = now();
                }

                $user = User::firstOrCreate(
                    ['email' => $email],
                    $userData
                );

                return ['tenant' => $tenant, 'user' => $user];
            });

            $this->info("✅ User created: {$result['user']->email}");
            $this->info("✅ Tenant: {$result['tenant']->slug} (ID: {$result['tenant']->id})");
            $this->info("✅ Password: $password");
            $this->info("✅ Verified: " . ($verified ? 'Yes' : 'No'));
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
