<?php

namespace App\Filament\Tenant\Resources\ProjectResource\Pages;

use App\Filament\Tenant\Resources\ProjectResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListProjects extends ListRecords
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('New Project')
                ->url(fn (): string => static::$resource::getUrl('create'))
                ->icon(Heroicon::OutlinedPlus),
        ];
    }
}
