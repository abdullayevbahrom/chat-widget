<?php

namespace App\Filament\Resources\TenantDomains\Pages;

use App\Filament\Resources\TenantDomains\TenantDomainResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTenantDomain extends CreateRecord
{
    protected static string $resource = TenantDomainResource::class;
}
