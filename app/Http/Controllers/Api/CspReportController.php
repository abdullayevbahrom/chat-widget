<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CSP Violation Report Receiver.
 *
 * Receives Content-Security-Policy violation reports from browsers
 * and logs them for security monitoring. This helps detect potential
 * XSS attempts or misconfigured CSP policies.
 *
 * All logged values are treated as plain text and never rendered as HTML
 * to prevent XSS attacks when viewing reports in the admin panel.
 */
class CspReportController extends Controller
{
    /**
     * Handle a CSP violation report from a browser.
     *
     * This endpoint accepts both the standard CSP report format
     * and the newer CSP Report API format (report-to).
     */
    public function store(Request $request): JsonResponse
    {
        // Try standard CSP report format first
        $report = $request->input('csp-report');

        // Fallback to newer report-to format
        if ($report === null) {
            $report = $request->all();
        }

        if (! is_array($report) || $report === []) {
            return response()->json(['error' => 'Invalid report format'], 400);
        }

        // Extract key fields for logging
        // IMPORTANT: All values are treated as data, never rendered as HTML
        $documentUri = (string) ($report['document-uri'] ?? 'unknown');
        $violatedDirective = (string) ($report['violated-directive'] ?? $report['violatedDirective'] ?? 'unknown');
        $effectiveDirective = (string) ($report['effective-directive'] ?? 'unknown');
        $blockedUri = (string) ($report['blocked-uri'] ?? 'unknown');
        $sourceFile = (string) ($report['source-file'] ?? 'unknown');
        $lineNumber = isset($report['line-number']) ? (int) $report['line-number'] : null;
        $columnNumber = isset($report['column-number']) ? (int) $report['column-number'] : null;
        $statusCode = isset($report['status-code']) ? (int) $report['status-code'] : null;
        $disposition = (string) ($report['disposition'] ?? 'enforce');
        $originalPolicy = isset($report['original-policy']) ? (string) $report['original-policy'] : null;
        $scriptSample = isset($report['script-sample']) ? (string) $report['script-sample'] : null;

        // Sanitize all values to prevent log injection and XSS
        $documentUri = $this->sanitizeForLog($documentUri);
        $violatedDirective = $this->sanitizeForLog($violatedDirective);
        $blockedUri = $this->sanitizeForLog($blockedUri);
        $sourceFile = $this->sanitizeForLog($sourceFile);
        $disposition = $this->sanitizeForLog($disposition);
        $scriptSample = $this->sanitizeForLog($scriptSample ?? '');
        $effectiveDirective = $this->sanitizeForLog($effectiveDirective);

        // Rate limit logging to prevent log flooding
        // Only log unique violation patterns (by directive + blocked URI)
        $cacheKey = 'csp-report:'.md5("{$violatedDirective}:{$blockedUri}");
        $recentCount = cache()->get($cacheKey, 0);

        if ($recentCount < 100) {
            cache()->increment($cacheKey, 1, 3600); // Reset after 1 hour

            Log::warning('CSP violation report received.', [
                'violated_directive' => $violatedDirective,
                'effective_directive' => $effectiveDirective,
                'blocked_uri' => $blockedUri,
                'document_uri' => $documentUri,
                'source_file' => $sourceFile,
                'line_number' => $lineNumber,
                'column_number' => $columnNumber,
                'status_code' => $statusCode,
                'disposition' => $disposition,
                'script_sample' => $scriptSample,
            ]);

            // Store in database for admin viewing (if table exists)
            $this->storeReport(
                $violatedDirective,
                $blockedUri,
                $documentUri,
                $sourceFile,
                $disposition,
                $effectiveDirective,
                $lineNumber,
                $columnNumber,
                $statusCode,
                $originalPolicy,
                $scriptSample
            );
        }

        // Always return 204 No Content — the browser doesn't need a response body
        return response()->json(['status' => 'received'], 204);
    }

    /**
     * Store a CSP violation report in the database.
     */
    protected function storeReport(
        string $violatedDirective,
        string $blockedUri,
        string $documentUri,
        string $sourceFile,
        string $disposition,
        string $effectiveDirective,
        ?int $lineNumber,
        ?int $columnNumber,
        ?int $statusCode,
        ?string $originalPolicy,
        ?string $scriptSample
    ): void {
        try {
            // Check if csp_reports table exists
            $hasTable = DB::getSchemaBuilder()->hasTable('csp_reports');

            if (! $hasTable) {
                return;
            }

            // Check if we already have this pattern in the last hour
            $existing = DB::table('csp_reports')
                ->where('violated_directive', $violatedDirective)
                ->where('blocked_uri', $blockedUri)
                ->where('created_at', '>=', now()->subHour())
                ->first();

            if ($existing !== null) {
                // Increment count
                DB::table('csp_reports')
                    ->where('id', $existing->id)
                    ->increment('count');
            } else {
                // Sanitize all values before DB insertion to prevent XSS
                $sanitizedViolatedDirective = htmlspecialchars($violatedDirective, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $sanitizedBlockedUri = htmlspecialchars($blockedUri, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $sanitizedDocumentUri = htmlspecialchars($documentUri, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $sanitizedSourceFile = htmlspecialchars($sourceFile, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $sanitizedDisposition = htmlspecialchars($disposition, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $sanitizedEffectiveDirective = htmlspecialchars($effectiveDirective, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $sanitizedOriginalPolicy = $originalPolicy !== null ? htmlspecialchars($originalPolicy, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : null;
                $sanitizedScriptSample = $scriptSample !== null ? htmlspecialchars($scriptSample, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : null;

                // Insert new report
                DB::table('csp_reports')->insert([
                    'violated_directive' => $sanitizedViolatedDirective,
                    'blocked_uri' => $sanitizedBlockedUri,
                    'document_uri' => $sanitizedDocumentUri,
                    'source_file' => $sanitizedSourceFile,
                    'disposition' => $sanitizedDisposition,
                    'effective_directive' => $sanitizedEffectiveDirective,
                    'line_number' => $lineNumber,
                    'column_number' => $columnNumber,
                    'status_code' => $statusCode,
                    'original_policy' => $sanitizedOriginalPolicy,
                    'script_sample' => $sanitizedScriptSample,
                    'count' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            // Silently fail — logging is still happening
            Log::debug('Failed to store CSP report in database: '.$e->getMessage());
        }
    }

    /**
     * Sanitize a string for safe logging and display.
     *
     * Removes control characters and truncates to prevent log injection.
     */
    protected function sanitizeForLog(string $value, int $maxLength = 500): string
    {
        // Remove control characters (except newlines and tabs)
        $sanitized = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/u', '', $value);

        // Truncate to prevent excessively long entries
        if (strlen($sanitized) > $maxLength) {
            $sanitized = substr($sanitized, 0, $maxLength).'...';
        }

        return $sanitized;
    }
}
