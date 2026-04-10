<?php

namespace App\Filament\Resources\Tenants\Schemas;

use App\Models\Tenant;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class TenantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                // Basic Information
                Section::make('Basic Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, $state) {
                                $baseSlug = Str::slug($state);
                                $slug = $baseSlug;
                                $counter = 1;
                                // Handle duplicate slugs by appending a number
                                while (Tenant::where('slug', $slug)->exists()) {
                                    $slug = $baseSlug.'-'.$counter;
                                    $counter++;
                                }
                                $set('slug', $slug);
                            }),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->alphaDash(),
                        TextInput::make('domain')
                            ->label('Custom Domain')
                            ->nullable()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->regex(config('domains.regex'))
                            ->rule(fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                                if (str_contains($value, '..')) {
                                    $fail('The domain must not contain consecutive dots.');
                                }
                            })
                            ->helperText('e.g. example.com'),
                        TextInput::make('subdomain')
                            ->nullable()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->alphaDash()
                            ->helperText('e.g. mycompany (becomes mycompany.yourapp.com)'),
                        Toggle::make('is_active')
                            ->default(true),
                        Select::make('plan')
                            ->required()
                            ->options([
                                'free' => 'Free',
                                'basic' => 'Basic',
                                'pro' => 'Pro',
                                'enterprise' => 'Enterprise',
                            ])
                            ->default('free'),
                        DateTimePicker::make('subscription_expires_at')
                            ->nullable(),
                    ])
                    ->columns(2),

                // Company Information
                Section::make('Company Information')
                    ->schema([
                        TextInput::make('company_name')
                            ->label('Company Name')
                            ->maxLength(255),
                        TextInput::make('company_registration_number')
                            ->label('Registration Number')
                            ->maxLength(255),
                        TextInput::make('tax_id')
                            ->label('Tax ID')
                            ->maxLength(255),
                        TextInput::make('company_city')
                            ->label('City')
                            ->maxLength(255),
                        Select::make('company_country')
                            ->label('Country')
                            ->options(config('countries'))
                            ->searchable()
                            ->rule(['nullable', 'string', 'size:2', 'in:'.implode(',', array_keys(config('countries')))]),
                        TextInput::make('website')
                            ->label('Website')
                            ->url()
                            ->nullable()
                            ->prefix('https://'),
                        Textarea::make('company_address')
                            ->label('Address')
                            ->rows(2)
                            ->columnSpanFull(),
                        FileUpload::make('logo_path')
                            ->label('Logo')
                            ->image()
                            ->directory('tenant-logos')
                            ->maxSize(2048)
                            ->nullable()
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                // Contact Information
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
                    ->columns(2)
                    ->collapsible(),

                // Settings
                KeyValue::make('settings')
                    ->nullable()
                    ->keyLabel('Key')
                    ->valueLabel('Value')
                    ->columnSpanFull()
                    ->helperText('Key-value pairs stored as JSON.'),
            ]);
    }
}
