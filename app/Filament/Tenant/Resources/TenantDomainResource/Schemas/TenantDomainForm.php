<?php

namespace App\Filament\Tenant\Resources\TenantDomainResource;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TenantDomainForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('domain')
                    ->required()
                    ->maxLength(255)
                    ->label('Domain')
                    ->unique(
                        table: \App\Models\TenantDomain::class,
                        column: 'domain',
                        ignorable: fn ($record) => $record
                    )
                    ->regex('/^[a-zA-Z0-9][a-zA-Z0-9\-\.]*\.[a-zA-Z]{2,}$/')
                    ->helperText('e.g., app.example.com'),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
                Textarea::make('notes')
                    ->label('Notes')
                    ->nullable()
                    ->rows(3),
            ]);
    }
}
