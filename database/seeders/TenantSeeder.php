<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('TenantSeeder should not be run in production. It creates test accounts with weak passwords.');
        }

        $tenants = [
            [
                'name' => 'Acme Corporation',
                'slug' => 'acme',
                'is_active' => true,
                'plan' => 'enterprise',
                'subscription_expires_at' => now()->addYear(),
            ],
            [
                'name' => 'Startup Labs',
                'slug' => 'startup-labs',
                'is_active' => true,
                'plan' => 'pro',
                'subscription_expires_at' => now()->addMonths(6),
            ],
            [
                'name' => 'Small Biz Co',
                'slug' => 'small-biz',
                'is_active' => true,
                'plan' => 'basic',
                'subscription_expires_at' => now()->addMonths(3),
            ],
            [
                'name' => 'Free Trial User',
                'slug' => 'free-trial',
                'is_active' => true,
                'plan' => 'free',
                'subscription_expires_at' => null,
            ],
            [
                'name' => 'Expired Tenant',
                'slug' => 'expired',
                'is_active' => false,
                'plan' => 'basic',
                'subscription_expires_at' => now()->subMonths(2),
            ],
        ];

        foreach ($tenants as $tenantData) {
            $slug = $tenantData['slug'];
            $tenant = Tenant::firstOrCreate(
                ['slug' => $slug],
                $tenantData
            );

            // Create users for this tenant
            $tenantAdminEmail = "admin@{$slug}.test";
            User::firstOrCreate(
                ['email' => $tenantAdminEmail],
                [
                    'name' => "{$tenantData['name']} Admin",
                    'password' => Hash::make('password'),
                    'tenant_id' => $tenant->id,
                    'is_super_admin' => false,
                    'email_verified_at' => now(),
                ]
            );

            // Create a second user for tenants with pro+ plans
            if (in_array($tenantData['plan'], ['pro', 'enterprise'])) {
                $memberEmail = "member@{$slug}.test";
                User::firstOrCreate(
                    ['email' => $memberEmail],
                    [
                        'name' => "{$tenantData['name']} Member",
                        'password' => Hash::make('password'),
                        'tenant_id' => $tenant->id,
                        'is_super_admin' => false,
                        'email_verified_at' => now(),
                    ]
                );
            }
        }
    }
}
