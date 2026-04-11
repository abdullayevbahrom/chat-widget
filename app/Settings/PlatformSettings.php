<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class PlatformSettings extends Settings
{
    public string $site_name;
    public string $site_description;
    public ?string $site_logo_url;
    public bool $maintenance_mode;
    public bool $registration_enabled;
    public array $max_tenants_per_plan;
    public string $default_plan;
    public bool $email_verification_required;
    public ?int $max_projects_per_tenant;

    public static function group(): string
    {
        return 'platform';
    }

    public static function defaults(): array
    {
        return [
            'site_name' => config('app.name', 'My Platform'),
            'site_description' => '',
            'site_logo_url' => null,
            'maintenance_mode' => false,
            'registration_enabled' => true,
            'max_tenants_per_plan' => [
                'free' => 0,
                'basic' => 10,
                'pro' => 100,
                'enterprise' => -1, // unlimited
            ],
            'default_plan' => 'free',
            'email_verification_required' => true,
            'max_projects_per_tenant' => 10,
        ];
    }
}
