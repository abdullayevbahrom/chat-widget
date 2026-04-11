<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class QueueOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $pendingJobs = Queue::size();
        $failedJobs = DB::table('failed_jobs')->count();

        return [
            Stat::make('Pending Jobs', $pendingJobs)
                ->icon('heroicon-o-clock')
                ->color('info'),
            Stat::make('Failed Jobs', $failedJobs)
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger'),
        ];
    }
}
