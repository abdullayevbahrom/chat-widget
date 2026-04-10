<?php

namespace App\Filament\Tenant\Resources\TenantDomainResource\Pages;

use App\Filament\Tenant\Resources\TenantDomainResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTenantDomains extends ListRecords
{
    protected static string $resource = TenantDomainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
