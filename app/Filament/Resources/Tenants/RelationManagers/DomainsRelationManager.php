<?php

namespace App\Filament\Resources\Tenants\RelationManagers;

use App\Rules\UniqueTenantDomain;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DomainsRelationManager extends RelationManager
{
    protected static string $relationship = 'domains';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('domain')
                    ->label('Domen')
                    ->required()
                    ->maxLength(255)
                    ->regex('/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i')
                    ->rule(
                        fn ($record) => new UniqueTenantDomain(
                            tenantId: $this->ownerRecord->id,
                            ignoreId: $record?->id,
                        ),
                    ),
                Toggle::make('is_active')
                    ->label('Faol')
                    ->default(true),
                Textarea::make('notes')
                    ->label('Izoh')
                    ->maxLength(500)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('domain')
            ->columns([
                TextColumn::make('domain')
                    ->label('Domen')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Faol')
                    ->boolean(),
                TextColumn::make('notes')
                    ->label('Izoh')
                    ->limit(50)
                    ->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
