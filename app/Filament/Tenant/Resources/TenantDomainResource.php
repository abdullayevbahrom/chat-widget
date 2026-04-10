<?php

namespace App\Filament\Tenant\Resources;

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

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?string $navigationLabel = 'Domains';

    protected static ?string $modelLabel = 'Domain';

    protected static UnitEnum|string|null $navigationGroup = 'Settings';

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
