<?php

namespace App\Filament\Tenant\Resources\TenantDomainResource\Pages;

use App\Filament\Tenant\Resources\TenantDomainResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTenantDomain extends CreateRecord
{
    protected static string $resource = TenantDomainResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
