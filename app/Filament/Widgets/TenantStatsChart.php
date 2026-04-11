<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class TenantStatsChart extends ChartWidget
{
    public function getHeading(): string
    {
        return 'Tenantlar — Plan Bo\'yicha';
    }

    protected function getData(): array
    {
        // Bitta GROUP BY query bilan barcha plan statistikalarni olish
        $planStats = DB::table('tenants')
            ->select('plan', DB::raw('COUNT(*) as count'))
            ->groupBy('plan')
            ->pluck('count', 'plan')
            ->toArray();

        $plans = ['free', 'basic', 'pro', 'enterprise'];
        $colors = ['#6B7280', '#3B82F6', '#F59E0B', '#10B981'];

        $data = [];
        foreach ($plans as $plan) {
            $data[] = $planStats[$plan] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Tenantlar soni',
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderColor' => $colors,
                ],
            ],
            'labels' => ['Free', 'Basic', 'Pro', 'Enterprise'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
