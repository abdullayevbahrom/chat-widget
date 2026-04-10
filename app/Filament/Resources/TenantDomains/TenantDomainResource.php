<?php

namespace App\Filament\Resources\TenantDomains;

use App\Filament\Resources\TenantDomains\Pages\ListTenantDomains;
use App\Filament\Resources\TenantDomains\Schemas\TenantDomainForm;
use App\Filament\Resources\TenantDomains\Tables\TenantDomainTable;
use App\Models\TenantDomain;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TenantDomainResource extends Resource
{
    protected static ?string $model = TenantDomain::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $navigationLabel = 'Tenant Domains';

    protected static ?string $modelLabel = 'Tenant Domain';

    protected static string|UnitEnum|null $navigationGroup = 'Tenants';

    public static function form(Schema $schema): Schema
    {
        return TenantDomainForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TenantDomainTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenantDomains::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }
}
