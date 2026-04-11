<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * CSP Violation Report Receiver.
 *
 * Receives Content-Security-Policy violation reports from browsers
 * and logs them for security monitoring. This helps detect potential
 * XSS attempts or misconfigured CSP policies.
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
        $documentUri = $report['document-uri'] ?? 'unknown';
        $violatedDirective = $report['violated-directive'] ?? $report['violatedDirective'] ?? 'unknown';
        $effectiveDirective = $report['effective-directive'] ?? 'unknown';
        $blockedUri = $report['blocked-uri'] ?? 'unknown';
        $sourceFile = $report['source-file'] ?? 'unknown';
        $lineNumber = $report['line-number'] ?? null;
        $columnNumber = $report['column-number'] ?? null;
        $statusCode = $report['status-code'] ?? null;
        $disposition = $report['disposition'] ?? 'enforce';
        $originalPolicy = $report['original-policy'] ?? null;
        $scriptSample = $report['script-sample'] ?? null;

        // Rate limit logging to prevent log flooding
        // Only log unique violation patterns (by directive + blocked-uri)
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
        }

        // Always return 204 No Content — the browser doesn't need a response body
        return response()->json(['status' => 'received'], 204);
    }
}
