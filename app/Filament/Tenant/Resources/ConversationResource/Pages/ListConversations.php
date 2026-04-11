<?php

namespace App\Filament\Tenant\Resources\ConversationResource\Pages;

use App\Filament\Tenant\Resources\ConversationResource;
use Filament\Resources\Pages\ListRecords;

class ListConversations extends ListRecords
{
    protected static string $resource = ConversationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
