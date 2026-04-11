<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Health check endpoint for monitoring.
 *
 * Provides operational visibility into database, redis,
 * queue, and Reverb connectivity.
 */
class HealthController extends Controller
{
    public function index(): JsonResponse
    {
        $checks = [];
        $overallStatus = 'ok';

        // Database check
        $checks['database'] = $this->checkDatabase();

        // Redis check
        $checks['redis'] = $this->checkRedis();

        // Queue check
        $checks['queue'] = $this->checkQueue();

        // Reverb check
        $checks['reverb'] = $this->checkReverb();

        // Determine overall status
        foreach ($checks as $check) {
            if ($check['status'] === 'critical') {
                $overallStatus = 'critical';
                break;
            }

            if ($check['status'] === 'warning') {
                $overallStatus = 'degraded';
            }
        }

        return response()->json([
            'status' => $overallStatus,
            'checks' => $checks,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Check database connectivity.
     */
    protected function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'ok',
                'latency_ms' => $latency,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'critical',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check Redis connectivity.
     */
    protected function checkRedis(): array
    {
        try {
            $start = microtime(true);
            Redis::ping();
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'status' => 'ok',
                'latency_ms' => $latency,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'critical',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check queue health (failed jobs in the last hour).
     */
    protected function checkQueue(): array
    {
        try {
            $failedCount = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subHour())
                ->count();

            $status = $failedCount > 10 ? 'warning' : 'ok';

            return [
                'status' => $status,
                'failed_jobs_last_hour' => $failedCount,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'warning',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check Reverb configuration.
     */
    protected function checkReverb(): array
    {
        $driver = config('broadcasting.default');

        return [
            'status' => $driver === 'reverb' ? 'ok' : 'warning',
            'driver' => $driver,
        ];
    }
}
