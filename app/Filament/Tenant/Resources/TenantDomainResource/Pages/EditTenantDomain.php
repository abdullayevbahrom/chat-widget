<?php

namespace App\Filament\Tenant\Resources\TenantDomainResource\Pages;

use App\Filament\Tenant\Resources\TenantDomainResource;
use Filament\Resources\Pages\EditRecord;

class EditTenantDomain extends EditRecord
{
    protected static string $resource = TenantDomainResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
