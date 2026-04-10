<?php

namespace App\Filament\Resources\TenantDomains;

use Filament\Forms\Components\Select;
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
                Select::make('tenant_id')
                    ->label('Tenant')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('domain')
                    ->required()
                    ->maxLength(255)
                    ->unique(
                        table: \App\Models\TenantDomain::class,
                        column: 'domain',
                        ignorable: fn ($record) => $record
                    )
                    ->regex('/^[a-zA-Z0-9][a-zA-Z0-9\-\.]*\.[a-zA-Z]{2,}$/'),
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
