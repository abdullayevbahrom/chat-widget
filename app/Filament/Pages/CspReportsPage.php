<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Filament\Support\Icons\Heroicon;

/**
 * CSP Violation Reports viewer for administrators.
 *
 * Displays recent Content-Security-Policy violations
 * from the application logs in a sanitized, XSS-safe format.
 */
#[\Filament\Attributes\NavigationIcon(Heroicon::ShieldExclamation)]
class CspReportsPage extends Page
{
    protected string $view = 'filament.pages.csp-reports';

    protected static ?string $navigationLabel = 'CSP Reports';

    protected static ?string $title = 'Content Security Policy Reports';

    /**
     * Get recent CSP violation reports (up to 50).
     *
     * All output values are sanitized to prevent XSS attacks.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getReports(): array
    {
        $recentReports = [];

        // Try to get from database if there's a csp_reports table
        try {
            $hasTable = DB::getSchemaBuilder()->hasTable('csp_reports');

            if (! $hasTable) {
                // Table doesn't exist yet — fall back to cache-based tracking
                return $this->getCacheReports();
            }

            $reports = DB::table('csp_reports')
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();

            foreach ($reports as $report) {
                $recentReports[] = [
                    'id' => $report->id,
                    'violated_directive' => $this->sanitize($report->violated_directive ?? 'unknown'),
                    'blocked_uri' => $this->sanitize($report->blocked_uri ?? 'unknown'),
                    'document_uri' => $this->sanitize($report->document_uri ?? 'unknown'),
                    'source_file' => $this->sanitize($report->source_file ?? 'unknown'),
                    'disposition' => $this->sanitize($report->disposition ?? 'enforce'),
                    'script_sample' => $this->sanitize($report->script_sample ?? ''),
                    'created_at' => $report->created_at,
                    'count' => $report->count ?? 1,
                ];
            }
        } catch (\Throwable $e) {
            // Log the error for debugging but don't crash the page
            \Illuminate\Support\Facades\Log::error('Failed to load CSP reports: '.$e->getMessage());

            // Return a user-friendly fallback
            $recentReports = [
                [
                    'id' => 0,
                    'violated_directive' => 'N/A',
                    'blocked_uri' => 'N/A',
                    'document_uri' => 'N/A',
                    'source_file' => 'N/A',
                    'disposition' => 'error',
                    'script_sample' => '',
                    'created_at' => now(),
                    'count' => 0,
                    'error' => 'Unable to load CSP reports. Please try again later.',
                ],
            ];
        }

        return $recentReports;
    }

    /**
     * Get CSP reports from cache-based tracking.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getCacheReports(): array
    {
        // Cache doesn't store individual reports, just counts
        // Return an empty array with instructions to set up the csp_reports table
        return [];
    }

    /**
     * Sanitize a string for safe HTML display (prevent XSS).
     *
     * Converts special characters to HTML entities.
     */
    protected function sanitize(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
