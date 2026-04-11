<?php

namespace App\Filament\Pages;

use App\Models\Tenant;
use App\Models\User;
use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse as RegistrationResponseContract;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Livewire\Features\SupportRedirects\Redirector;

class TenantRegistrationResponse implements RegistrationResponseContract
{
    public function toResponse($request): RedirectResponse | Redirector
    {
        return redirect('/app');
    }
}

class TenantRegister extends BaseRegister
{
    protected static string $routePath = 'register';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Password')
            ->password()
            ->required()
            ->minLength(8)
            ->rule(
                Password::min(8)
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
            // Generate unique tenant slug from email
            $emailUsername = explode('@', $data['email'])[0];
            $baseSlug = str($emailUsername)->slug()->toString();
            $slug = $baseSlug;
            $counter = 1;

            while (Tenant::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }

            // Create the tenant
            $tenant = Tenant::create([
                'name' => $emailUsername,
                'slug' => $slug,
                'is_active' => true,
                'plan' => 'free',
                'subscription_expires_at' => null,
            ]);

            // Create the user and link to tenant
            $user = User::create([
                'name' => $emailUsername,
                'email' => $data['email'],
                'password' => $data['password'],
                'tenant_id' => $tenant->id,
                'is_super_admin' => false,
            ]);

            return $user;
        });
    }

    public function register(): ?RegistrationResponseContract
    {
        try {
            $this->rateLimit(2);
        } catch (\DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();
            return null;
        }

        if ($this->isRegisterRateLimited($this->data['email'] ?? '')) {
            return null;
        }

        $user = $this->wrapInDatabaseTransaction(function (): Model {
            $this->callHook('beforeValidate');

            $data = $this->form->getState();

            $this->callHook('afterValidate');

            $data = $this->mutateFormDataBeforeRegister($data);

            $this->callHook('beforeRegister');

            $user = $this->handleRegistration($data);

            $this->form->model($user)->saveRelationships();

            $this->callHook('afterRegister');

            return $user;
        });

        event(new \Filament\Auth\Events\Registered($user));

        $this->sendEmailVerificationNotification($user);

        // tenant_user guard orqali login qilish
        Auth::guard('tenant_user')->login($user);
        session()->regenerate();

        return new TenantRegistrationResponse();
    }

    protected function getRedirectUrl(): string
    {
        return '/app';
    }
}
