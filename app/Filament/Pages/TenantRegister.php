<?php

namespace App\Filament\Pages;

use App\Models\Tenant;
use App\Models\User;
use App\Settings\PlatformSettings;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class TenantRegister extends BaseRegister
{
    protected static string $routePath = 'register';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getTenantNameFormComponent(),
                $this->getTenantSlugFormComponent(),
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getContactPhoneFormComponent(),
            ]);
    }

    protected function getTenantNameFormComponent(): Component
    {
        return TextInput::make('tenant_name')
            ->label('Company Name')
            ->required()
            ->maxLength(255)
            ->live(onBlur: true)
            ->afterStateUpdated(function ($state, callable $set) {
                $set('tenant_slug', str($state)->slug());
            });
    }

    protected function getTenantSlugFormComponent(): Component
    {
        return TextInput::make('tenant_slug')
            ->label('Subdomain / Slug')
            ->required()
            ->maxLength(255)
            ->unique(table: Tenant::class, column: 'slug')
            ->alphaDash()
            ->helperText('This will be your unique identifier.');
    }

    protected function getContactPhoneFormComponent(): Component
    {
        return TextInput::make('contact_phone')
            ->label('Contact Phone')
            ->tel()
            ->nullable()
            ->maxLength(255);
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Password')
            ->password()
            ->required()
            ->minLength(8)
            ->rule(Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised()
            )
            ->revealable()
            ->autocomplete('new-password')
            ->same('passwordConfirmation');
    }

    protected function handleRegistration(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            // Check plan limits
            $settings = app(PlatformSettings::class);
            $plan = 'free'; // default plan for self-registration
            $maxTenants = $settings->max_tenants_per_plan[$plan] ?? 0;

            if ($maxTenants >= 0) { // 0 or positive means limited; -1 means unlimited
                $currentCount = Tenant::where('plan', $plan)->count();
                if ($currentCount >= $maxTenants) {
                    throw ValidationException::withMessages([
                        'tenant_slug' => 'The '.$plan.' plan has reached its maximum tenant limit.',
                    ]);
                }
            }

            // Create the tenant (pending activation — requires email verification)
            $tenant = Tenant::create([
                'name' => $data['tenant_name'],
                'slug' => $data['tenant_slug'],
                'is_active' => false, // Pending activation until email is verified
                'plan' => 'free',
                'subscription_expires_at' => null,
            ]);

            // Create the user and link to tenant
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'tenant_id' => $tenant->id,
                'is_super_admin' => false,
            ]);

            // If contact phone was provided, update tenant
            if (! empty($data['contact_phone'])) {
                $tenant->update(['contact_phone' => $data['contact_phone']]);
            }

            return $user;
        });
    }
}
