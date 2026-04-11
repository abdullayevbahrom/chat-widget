<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\TenantDomainResource\Pages\CreateTenantDomain;
use App\Filament\Tenant\Resources\TenantDomainResource\Pages\EditTenantDomain;
use App\Filament\Tenant\Resources\TenantDomainResource\Pages\ListTenantDomains;
use App\Filament\Tenant\Resources\TenantDomainResource\Schemas\TenantDomainForm;
use App\Filament\Tenant\Resources\TenantDomainResource\Tables\TenantDomainTable;
use App\Models\TenantDomain;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class TenantDomainResource extends Resource
{
    protected static ?string $model = TenantDomain::class;

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return Heroicon::OutlinedGlobeAlt;
    }

    public static function getNavigationLabel(): string
    {
        return 'Domains';
    }

    public static function getModelLabel(): string
    {
        return 'Domain';
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Settings';
    }

    public static function form(Schema $schema): Schema
    {
        return TenantDomainForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TenantDomainTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenantDomains::route('/'),
            'create' => CreateTenantDomain::route('/create'),
            'edit' => EditTenantDomain::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $user = Auth::user();

        if ($user === null || $user->tenant_id === null) {
            return parent::getEloquentQuery()->whereNull('id');
        }

        return parent::getEloquentQuery()->where('tenant_id', $user->tenant_id);
    }
}
