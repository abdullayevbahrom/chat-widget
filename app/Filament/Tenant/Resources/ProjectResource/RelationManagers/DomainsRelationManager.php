<?php

namespace App\Filament\Tenant\Resources\ProjectResource\RelationManagers;

use App\Models\ProjectDomain;
use App\Services\DomainVerificationService;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class DomainsRelationManager extends RelationManager
{
    protected static string $relationship = 'domains';

    protected static ?string $recordTitleAttribute = 'domain';

    public function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('domain')
                    ->required()
                    ->maxLength(255)
                    ->regex('/^[a-zA-Z0-9][a-zA-Z0-9\-\.]*\.[a-zA-Z]{2,}$/')
                    ->helperText('e.g., app.example.com')
                    ->unique(
                        table: ProjectDomain::class,
                        column: 'domain',
                        ignoreRecord: true,
                    ),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
                Textarea::make('notes')
                    ->label('Notes')
                    ->nullable()
                    ->rows(3),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('domain')
                    ->label('Domain')
                    ->searchable()
                    ->sortable(),
                BadgeColumn::make('verification_status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'verified',
                        'danger' => 'failed',
                    ])
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                    ->falseIcon(Heroicon::OutlinedXCircle)
                    ->trueColor('success')
                    ->falseColor('danger'),
                TextColumn::make('verified_at')
                    ->label('Verified At')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->headerActions([
                Action::make('verify')
                    ->label('Verify Domain')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->record(fn ($record) => $record)
                    ->visible(fn ($record): bool => $record !== null && $record->verification_status !== 'verified')
                    ->requiresConfirmation()
                    ->modalHeading('Verify Domain')
                    ->modalDescription('This will check both DNS TXT record and HTTP file verification for your domain.')
                    ->action(function (ProjectDomain $record, DomainVerificationService $service) {
                        $success = $service->verify($record);

                        if ($success) {
                            Notification::make()
                                ->title('Domain Verified')
                                ->body("{$record->domain} has been successfully verified.")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Verification Failed')
                                ->body($record->verification_error ?? 'Could not verify domain ownership.')
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->recordActions([
                Action::make('verify')
                    ->label('Verify')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->visible(fn (ProjectDomain $record): bool => $record->verification_status !== 'verified')
                    ->requiresConfirmation()
                    ->action(function (ProjectDomain $record, DomainVerificationService $service) {
                        $success = $service->verify($record);

                        if ($success) {
                            Notification::make()
                                ->title('Domain Verified')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Verification Failed')
                                ->body($record->verification_error ?? 'Could not verify domain ownership.')
                                ->danger()
                                ->send();
                        }
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
