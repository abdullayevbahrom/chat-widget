<?php

namespace App\Filament\Tenant\Resources\ProjectResource\RelationManagers;

use App\Models\ProjectDomain;
use App\Services\DomainVerificationService;
use Closure;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
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
                    ->helperText('Use the full origin, for example https://app.example.com or http://localhost:3000')
                    ->rule(function () {
                        return function (string $attribute, mixed $value, Closure $fail): void {
                            if (! is_string($value)) {
                                $fail('Enter a valid origin such as https://app.example.com or http://localhost:3000.');

                                return;
                            }

                            $normalizedDomain = ProjectDomain::normalizeDomainInput($value);

                            if ($normalizedDomain === null) {
                                $fail('Enter a valid origin such as https://app.example.com or http://localhost:3000.');

                                return;
                            }

                            $projectId = $this->getOwnerRecord()->getKey();
                            $recordId = $this->getMountedTableActionRecord()?->getKey();

                            if (ProjectDomain::existsForProject($projectId, $normalizedDomain, $recordId)) {
                                $fail('This domain is already added to this project.');
                            }
                        };
                    }),
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
                    BulkAction::make('bulkVerify')
                        ->label('Verify Selected')
                        ->icon(Heroicon::OutlinedShieldCheck)
                        ->requiresConfirmation()
                        ->modalHeading('Verify selected domains?')
                        ->modalDescription('This will check both DNS TXT record and HTTP file verification for each selected domain.')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, DomainVerificationService $service) {
                            $successCount = 0;
                            $failedCount = 0;
                            $errors = [];

                            foreach ($records as $record) {
                                if ($record->verification_status === 'verified') {
                                    continue;
                                }

                                $success = $service->verify($record);

                                if ($success) {
                                    $successCount++;
                                } else {
                                    $failedCount++;
                                    $errors[] = "{$record->domain}: " . ($record->verification_error ?? 'Unknown error');
                                }
                            }

                            $body = "{$successCount} domain(s) verified successfully.";

                            if ($failedCount > 0) {
                                $body .= " {$failedCount} failed.";
                                if (count($errors) <= 5) {
                                    $body .= "\n\n" . implode("\n", $errors);
                                }
                            }

                            Notification::make()
                                ->title('Bulk Verification Complete')
                                ->body($body)
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }
}
