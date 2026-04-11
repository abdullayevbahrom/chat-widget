<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form as SchemaForm;
use Filament\Schemas\Schema;
use Filament\Forms\Form;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TenantProfile extends Page
{
    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-building-office';
    }

    public static function getNavigationLabel(): string
    {
        return 'Profile';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
    }

    public function getView(): string
    {
        return 'filament.tenant.pages.tenant-profile';
    }

    public ?array $data = [];

    public function mount(): void
    {
        $tenant = $this->getTenant();

        if ($tenant === null) {
            Notification::make()
                ->title('No tenant found')
                ->danger()
                ->send();

            return;
        }

        $this->form->fill($tenant->toArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Company Information')
                    ->schema([
                        TextInput::make('company_name')
                            ->label('Company Name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('company_registration_number')
                            ->label('Company Registration Number')
                            ->maxLength(255),
                        TextInput::make('tax_id')
                            ->label('Tax ID')
                            ->maxLength(255),
                        Textarea::make('company_address')
                            ->label('Company Address')
                            ->rows(3),
                        TextInput::make('company_city')
                            ->label('City')
                            ->maxLength(255),
                        Select::make('company_country')
                            ->label('Country')
                            ->options($this->getCountryOptions())
                            ->searchable()
                            ->placeholder('Select a country')
                            ->rule(['string', 'size:2', Rule::in(array_keys(config('countries')))]),
                        TextInput::make('website')
                            ->label('Website')
                            ->url()
                            ->nullable()
                            ->prefix('https://'),
                        FileUpload::make('logo_path')
                            ->label('Company Logo')
                            ->image()
                            ->directory('tenant-logos')
                            ->maxSize(2048)
                            ->imageResizeMode('contain')
                            ->imageCropAspectRatio('1:1')
                            ->nullable()
                            ->columnSpanFull()
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/gif'])
                            ->rules([
                                'mimes:png,jpeg,jpg,webp,gif',
                                'max:2048',
                            ])
                            ->deleteUploadedFileUsing(function (Tenant $tenant, string $statePath): void {
                                if ($tenant->logo_path) {
                                    Storage::disk('public')->delete($tenant->logo_path);
                                }
                            }),
                    ])
                    ->columns(2),

                Section::make('Contact Information')
                    ->schema([
                        TextInput::make('contact_email')
                            ->label('Contact Email')
                            ->email()
                            ->nullable()
                            ->maxLength(255),
                        TextInput::make('contact_phone')
                            ->label('Contact Phone')
                            ->tel()
                            ->nullable()
                            ->maxLength(255),
                        TextInput::make('primary_contact_name')
                            ->label('Primary Contact Name')
                            ->nullable()
                            ->maxLength(255),
                        TextInput::make('primary_contact_title')
                            ->label('Primary Contact Title')
                            ->nullable()
                            ->maxLength(255),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $tenant = $this->getTenant();

        if ($tenant === null) {
            Notification::make()
                ->title('No tenant found')
                ->danger()
                ->send();

            return;
        }

        $validatedData = $this->form->getState();

        // Handle logo upload: delete old file when replacing
        $this->handleLogoUpload($tenant, $validatedData);

        $tenant->update($validatedData);

        $this->logAudit('tenant_profile_updated', $tenant);

        Notification::make()
            ->title('Profile updated successfully')
            ->success()
            ->send();
    }

    /**
     * Delete old logo when a new one is uploaded.
     */
    protected function handleLogoUpload(Tenant $tenant, array &$data): void
    {
        if (!empty($data['logo_path']) && $data['logo_path'] !== $tenant->logo_path) {
            if ($tenant->logo_path) {
                Storage::disk('public')->delete($tenant->logo_path);
            }
        }
    }

    /**
     * Log audit events for tenant profile changes.
     */
    protected function logAudit(string $event, Tenant $tenant): void
    {
        logger()->info("Audit: {$event}", [
            'tenant_id' => $tenant->id,
            'tenant_slug' => $tenant->slug,
            'updated_by' => Auth::id(),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Changes')
                ->submit('save'),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }

    public function getFormContentComponent(): Component
    {
        return SchemaForm::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make($this->getFormActions())
                    ->alignment($this->getFormActionsAlignment())
                    ->fullWidth($this->hasFullWidthFormActions())
                    ->sticky($this->areFormActionsSticky())
                    ->key('form-actions'),
            ]);
    }

    protected function getTenant(): ?Tenant
    {
        $user = Auth::user();

        if ($user === null || $user->tenant_id === null) {
            return null;
        }

        return $user->tenant;
    }

    protected function getCountryOptions(): array
    {
        return config('countries');
    }
}
