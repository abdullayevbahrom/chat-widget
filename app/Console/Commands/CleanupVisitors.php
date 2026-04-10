<?php

namespace App\Console\Commands;

use App\Services\VisitorTrackingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupVisitors extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Uses the cleanup_after_days config value as default (Issue #8).
     */
    protected $signature = 'visitor:cleanup {--days= : Number of days to keep visitor records (default: config value)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old visitor records from the database';

    /**
     * Execute the console command.
     */
    public function handle(VisitorTrackingService $visitorTrackingService): int
    {
        // Use config value as default instead of hardcoded 90 (Issue #8)
        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : config('visitor-tracking.cleanup_after_days', 90);

        $this->info("Cleaning up visitor records older than {$days} days...");

        $deletedCount = $visitorTrackingService->cleanupOldVisitors($days);

        $this->info("Successfully deleted {$deletedCount} old visitor record(s).");

        Log::info("Visitor cleanup completed: {$deletedCount} records deleted (older than {$days} days).");

        return Command::SUCCESS;
    }
}
