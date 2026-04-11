<?php

namespace App\Filament\Pages;

use App\Settings\PlatformSettings;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ManagePlatformSettings extends SettingsPage
{
    protected static string $settings = PlatformSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Platform Settings';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return 'Platform Settings';
    }

    protected static ?string $title = 'Platform Settings';

    public function content(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('site_name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('site_description')
                    ->maxLength(500)
                    ->columnSpanFull(),
                TextInput::make('site_logo_url')
                    ->nullable()
                    ->url()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Toggle::make('maintenance_mode')
                    ->default(false),
                Toggle::make('registration_enabled')
                    ->default(true),
                Repeater::make('max_tenants_per_plan')
                    ->keyValue()
                    ->label('Max Tenants Per Plan')
                    ->keyLabel('Plan')
                    ->valueLabel('Max Tenants')
                    ->columnSpanFull(),
                Select::make('default_plan')
                    ->options([
                        'free' => 'Free',
                        'basic' => 'Basic',
                        'pro' => 'Pro',
                        'enterprise' => 'Enterprise',
                    ])
                    ->default('free')
                    ->required(),
                Toggle::make('email_verification_required')
                    ->default(true),
                TextInput::make('max_projects_per_tenant')
                    ->numeric()
                    ->minValue(1)
                    ->default(10)
                    ->nullable()
                    ->suffixIcon(Heroicon::OutlinedQuestionMarkCircle)
                    ->helperText('Bo\'sh qoldirilsa — cheklov yo\'q'),
            ]);
    }
}
