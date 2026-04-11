<?php

namespace App\Filament\Resources\TenantDomains\Pages;

use App\Filament\Resources\TenantDomains\TenantDomainResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTenantDomain extends EditRecord
{
    protected static string $resource = TenantDomainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
