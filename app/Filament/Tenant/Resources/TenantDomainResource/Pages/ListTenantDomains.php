<?php

namespace App\Filament\Tenant\Resources\TenantDomainResource\Pages;

use App\Filament\Tenant\Resources\TenantDomainResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListTenantDomains extends ListRecords
{
    protected static string $resource = TenantDomainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('New Domain')
                ->url(fn (): string => static::$resource::getUrl('create'))
                ->icon(Heroicon::OutlinedPlus),
        ];
    }
}
