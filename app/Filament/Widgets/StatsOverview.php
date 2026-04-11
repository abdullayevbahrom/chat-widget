<?php

namespace App\Filament\Widgets;

use App\Models\Tenant;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        // Bitta aggregatsiya query bilan barcha statistikalarni olish
        $stats = DB::table('tenants')
            ->selectRaw('
                COUNT(*) as total_tenants,
                SUM(CASE WHEN is_active = true THEN 1 ELSE 0 END) as active_tenants,
                SUM(CASE WHEN DATE(created_at) = CURRENT_DATE THEN 1 ELSE 0 END) as today_tenants
            ')
            ->first();

        $totalUsers = User::count() ?? 0;

        return [
            Stat::make('Jami Tenantlar', $stats->total_tenants ?? 0)
                ->icon('heroicon-o-building-office')
                ->color('primary'),
            Stat::make('Faol Tenantlar', $stats->active_tenants ?? 0)
                ->icon('heroicon-o-check-circle')
                ->color('success'),
            Stat::make('Jami Foydalanuvchilar', $totalUsers)
                ->icon('heroicon-o-users')
                ->color('info'),
            Stat::make('Bugungi Tenantlar', $stats->today_tenants ?? 0)
                ->icon('heroicon-o-calendar')
                ->color('warning'),
        ];
    }
}
