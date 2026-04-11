<?php

namespace App\Filament\Tenant\Resources\ConversationResource\Pages;

use App\Filament\Tenant\Resources\ConversationResource;
use App\Models\Conversation;
use App\Services\ConversationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewConversation extends ViewRecord
{
    protected static string $resource = ConversationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reopen')
                ->label('Reopen')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('warning')
                ->visible(fn (Conversation $record): bool => $record->isClosed())
                ->requiresConfirmation()
                ->action(function (Conversation $record): void {
                    $service = app(ConversationService::class);
                    $service->reopenConversation($record);

                    Notification::make()
                        ->title('Conversation Reopened')
                        ->success()
                        ->send();

                    $this->redirect(static::getUrl(['record' => $record->getKey()]));
                }),
            Action::make('close')
                ->label('Close')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->visible(fn (Conversation $record): bool => $record->isOpen())
                ->requiresConfirmation()
                ->action(function (Conversation $record): void {
                    $service = app(ConversationService::class);
                    $service->closeConversation($record);

                    Notification::make()
                        ->title('Conversation Closed')
                        ->success()
                        ->send();

                    $this->redirect(static::getUrl(['record' => $record->getKey()]));
                }),
            Action::make('archive')
                ->label('Archive')
                ->icon(Heroicon::OutlinedArchiveBox)
                ->color('gray')
                ->visible(fn (Conversation $record): bool => $record->isOpen() || $record->isClosed())
                ->requiresConfirmation()
                ->action(function (Conversation $record): void {
                    $service = app(ConversationService::class);
                    $service->archiveConversation($record);

                    Notification::make()
                        ->title('Conversation Archived')
                        ->success()
                        ->send();

                    $this->redirect(static::getUrl(['record' => $record->getKey()]));
                }),
        ];
    }

    public function getContentTabLabel(): ?string
    {
        return 'Details';
    }
}
