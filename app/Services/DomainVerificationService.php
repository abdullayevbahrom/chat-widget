<?php

namespace App\Services;

use App\Models\ProjectDomain;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DomainVerificationService
{
    /**
     * DNS verification timeout in seconds.
     */
    protected int $dnsTimeout = 5;

    /**
     * HTTP verification timeout in seconds.
     */
    protected int $httpTimeout = 10;

    /**
     * Maximum retry attempts for network errors.
     */
    protected int $maxRetries = 3;

    /**
     * Initiate verification for a domain.
     *
     * Generates a verification token and sets status to pending.
     */
    public function initiateVerification(ProjectDomain $domain): string
    {
        $token = $domain->generateVerificationToken();

        return $token;
    }

    /**
     * Verify a domain via DNS TXT record.
     *
     * Looks for a TXT record at _widget-verify.{domain} with value:
     * widget-verify={verification_token}
     */
    public function verifyViaDns(ProjectDomain $domain): bool
    {
        if (blank($domain->verification_token)) {
            $domain->markAsFailed('No verification token. Initiate verification first.');

            return false;
        }

        if (! $domain->isVerificationTokenValid()) {
            $domain->markAsFailed('Verification token expired. Please re-initiate verification.');

            return false;
        }

        $hostname = "_widget-verify.{$domain->domain}";
        $expectedValue = "widget-verify={$domain->verification_token}";

        $records = @dns_get_record($hostname, DNS_TXT);

        if ($records === false) {
            $domain->markAsFailed("DNS lookup failed for {$hostname}.");

            return false;
        }

        foreach ($records as $record) {
            if (isset($record['txt']) && str_contains($record['txt'], $expectedValue)) {
                $domain->markAsVerified();

                // Clear project's verified domains cache
                $domain->project->clearVerifiedDomainsCache();

                return true;
            }
        }

        $domain->markAsFailed("DNS TXT record not found or token mismatch for {$hostname}.");

        return false;
    }

    /**
     * Verify a domain via HTTP file verification.
     *
     * Expects a file at https://{domain}/.well-known/widget-verify
     * with content: widget-verify={verification_token}
     */
    public function verifyViaHttp(ProjectDomain $domain): bool
    {
        if (blank($domain->verification_token)) {
            $domain->markAsFailed('No verification token. Initiate verification first.');

            return false;
        }

        if (! $domain->isVerificationTokenValid()) {
            $domain->markAsFailed('Verification token expired. Please re-initiate verification.');

            return false;
        }

        $expectedContent = "widget-verify={$domain->verification_token}";
        $url = "https://{$domain->domain}/.well-known/widget-verify";

        $retries = 0;
        while ($retries < $this->maxRetries) {
            try {
                $response = Http::timeout($this->httpTimeout)
                    ->withoutVerifying()
                    ->get($url);

                if ($response->successful() && trim($response->body()) === $expectedContent) {
                    $domain->markAsVerified();

                    // Clear project's verified domains cache
                    $domain->project->clearVerifiedDomainsCache();

                    return true;
                }

                $domain->markAsFailed(
                    "HTTP verification failed. Expected: {$expectedContent}, Got: ".trim($response->body())
                );

                return false;
            } catch (\Exception $e) {
                $retries++;
                if ($retries >= $this->maxRetries) {
                    $domain->markAsFailed("HTTP verification failed after {$this->maxRetries} retries: {$e->getMessage()}");

                    return false;
                }
                sleep(1);
            }
        }

        return false;
    }

    /**
     * Verify a domain using both DNS and HTTP methods.
     *
     * Tries DNS first, falls back to HTTP.
     */
    public function verify(ProjectDomain $domain): bool
    {
        if ($this->verifyViaDns($domain)) {
            return true;
        }

        return $this->verifyViaHttp($domain);
    }
}
