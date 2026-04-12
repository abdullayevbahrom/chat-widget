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
                // Profile fields
                'company_name' => 'Acme Corporation',
                'company_registration_number' => '123456789',
                'tax_id' => 'US-TAX-987654321',
                'company_address' => '123 Innovation Drive, Tech Valley',
                'company_city' => 'San Francisco',
                'company_country' => 'US',
                'contact_phone' => '+1-555-0100',
                'contact_email' => 'contact@acme.example.com',
                'website' => 'https://acme.example.com',
                'primary_contact_name' => 'John Smith',
                'primary_contact_title' => 'CTO',
            ],
            [
                'name' => 'Startup Labs',
                'slug' => 'startup-labs',
                'is_active' => true,
                'plan' => 'pro',
                'subscription_expires_at' => now()->addMonths(6),
                // Profile fields
                'company_name' => 'Startup Labs Inc.',
                'company_registration_number' => '987654321',
                'tax_id' => 'US-TAX-123456789',
                'company_address' => '456 Startup Avenue',
                'company_city' => 'Austin',
                'company_country' => 'US',
                'contact_phone' => '+1-555-0200',
                'contact_email' => 'hello@startuplabs.io',
                'website' => 'https://startuplabs.io',
                'primary_contact_name' => 'Jane Doe',
                'primary_contact_title' => 'CEO',
            ],
            [
                'name' => 'Small Biz Co',
                'slug' => 'small-biz',
                'is_active' => true,
                'plan' => 'basic',
                'subscription_expires_at' => now()->addMonths(3),
                // Profile fields
                'company_name' => 'Small Biz Co',
                'company_registration_number' => null,
                'tax_id' => null,
                'company_address' => '789 Main Street',
                'company_city' => 'Portland',
                'company_country' => 'US',
                'contact_phone' => '+1-555-0300',
                'contact_email' => 'info@smallbiz.co',
                'website' => null,
                'primary_contact_name' => 'Bob Johnson',
                'primary_contact_title' => 'Owner',
            ],
            [
                'name' => 'Free Trial User',
                'slug' => 'free-trial',
                'is_active' => true,
                'plan' => 'free',
                'subscription_expires_at' => null,
                // Profile fields
                'company_name' => null,
                'company_registration_number' => null,
                'tax_id' => null,
                'company_address' => null,
                'company_city' => null,
                'company_country' => null,
                'contact_phone' => null,
                'contact_email' => null,
                'website' => null,
                'primary_contact_name' => null,
                'primary_contact_title' => null,
            ],
            [
                'name' => 'Expired Tenant',
                'slug' => 'expired',
                'is_active' => false,
                'plan' => 'basic',
                'subscription_expires_at' => now()->subMonths(2),
                // Profile fields
                'company_name' => 'Expired Tenant Ltd.',
                'company_registration_number' => '555666777',
                'tax_id' => 'GB-TAX-555666777',
                'company_address' => '321 Old Road',
                'company_city' => 'London',
                'company_country' => 'GB',
                'contact_phone' => '+44-555-0400',
                'contact_email' => 'info@expired.example.com',
                'website' => null,
                'primary_contact_name' => 'Alice Brown',
                'primary_contact_title' => 'Manager',
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
