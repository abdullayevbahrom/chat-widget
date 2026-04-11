<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\ConversationResource\Pages\ListConversations;
use App\Filament\Tenant\Resources\ConversationResource\Pages\ViewConversation;
use App\Models\Conversation;
use App\Services\ConversationService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class ConversationResource extends Resource
{
    protected static ?string $model = Conversation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Conversations';

    protected static ?string $modelLabel = 'Conversation';

    protected static UnitEnum|string|null $navigationGroup = 'Support';

    protected static ?int $navigationSort = 20;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                \Filament\Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Conversation::STATUS_OPEN => 'success',
                        Conversation::STATUS_CLOSED => 'warning',
                        Conversation::STATUS_ARCHIVED => 'gray',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('subject')
                    ->label('Subject')
                    ->limit(40)
                    ->placeholder('—')
                    ->searchable(),
                \Filament\Tables\Columns\TextColumn::make('visitor.name')
                    ->label('Visitor')
                    ->placeholder('Anonymous')
                    ->searchable(),
                \Filament\Tables\Columns\TextColumn::make('project.name')
                    ->label('Project')
                    ->searchable(),
                \Filament\Tables\Columns\TextColumn::make('assignedUser.name')
                    ->label('Assigned To')
                    ->placeholder('Unassigned')
                    ->searchable(),
                \Filament\Tables\Columns\TextColumn::make('last_message_at')
                    ->label('Last Message')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
                \Filament\Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        Conversation::STATUS_OPEN => 'Open',
                        Conversation::STATUS_CLOSED => 'Closed',
                        Conversation::STATUS_ARCHIVED => 'Archived',
                    ]),
                \Filament\Tables\Filters\SelectFilter::make('project_id')
                    ->label('Project')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\Action::make('reopen')
                    ->label('Reopen')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('warning')
                    ->visible(fn (Conversation $record): bool => $record->isClosed())
                    ->requiresConfirmation()
                    ->modalDescription('Are you sure you want to reopen this conversation?')
                    ->action(function (Conversation $record): void {
                        $service = app(ConversationService::class);
                        $service->reopenConversation($record);

                        Notification::make()
                            ->title('Conversation Reopened')
                            ->success()
                            ->send();
                    }),
                \Filament\Actions\Action::make('close')
                    ->label('Close')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (Conversation $record): bool => $record->isOpen())
                    ->requiresConfirmation()
                    ->modalDescription('Are you sure you want to close this conversation?')
                    ->action(function (Conversation $record): void {
                        $service = app(ConversationService::class);
                        $service->closeConversation($record);

                        Notification::make()
                            ->title('Conversation Closed')
                            ->success()
                            ->send();
                    }),
                \Filament\Actions\Action::make('archive')
                    ->label('Archive')
                    ->icon(Heroicon::OutlinedArchiveBox)
                    ->color('gray')
                    ->visible(fn (Conversation $record): bool => $record->isOpen() || $record->isClosed())
                    ->requiresConfirmation()
                    ->modalDescription('Are you sure you want to archive this conversation?')
                    ->action(function (Conversation $record): void {
                        $service = app(ConversationService::class);
                        $service->archiveConversation($record);

                        Notification::make()
                            ->title('Conversation Archived')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('last_message_at', 'desc')
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('close')
                        ->label('Close Selected')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Support\Collection $records): void {
                            $service = app(ConversationService::class);
                            $count = 0;

                            foreach ($records as $record) {
                                if ($record instanceof Conversation && $record->canTransitionTo(Conversation::STATUS_CLOSED)) {
                                    $service->closeConversation($record);
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->title("{$count} conversation(s) closed")
                                ->success()
                                ->send();
                        }),
                    \Filament\Actions\BulkAction::make('archive')
                        ->label('Archive Selected')
                        ->icon(Heroicon::OutlinedArchiveBox)
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Support\Collection $records): void {
                            $service = app(ConversationService::class);
                            $count = 0;

                            foreach ($records as $record) {
                                if ($record instanceof Conversation && $record->canTransitionTo(Conversation::STATUS_ARCHIVED)) {
                                    $service->archiveConversation($record);
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->title("{$count} conversation(s) archived")
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConversations::route('/'),
            'view' => ViewConversation::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $user = Auth::user();

        if ($user === null || ($user->tenant_id === null && ! $user->isSuperAdmin())) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        $query = parent::getEloquentQuery()
            ->with(['visitor', 'project', 'assignedUser', 'closedUser']);

        if (! $user->isSuperAdmin()) {
            $query->where('tenant_id', $user->tenant_id);
        }

        return $query;
    }
}
