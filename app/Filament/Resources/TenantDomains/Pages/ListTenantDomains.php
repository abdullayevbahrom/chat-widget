<?php

namespace App\Filament\Resources\TenantDomains\Pages;

use App\Filament\Resources\TenantDomains\TenantDomainResource;
use Filament\Resources\Pages\ListRecords;

class ListTenantDomains extends ListRecords
{
    protected static string $resource = TenantDomainResource::class;
}
