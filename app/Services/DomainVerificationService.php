<?php

namespace App\Services;

use App\Models\ProjectDomain;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

        $domainHost = $domain->getHostForVerification();

        if ($domainHost === null) {
            $domain->markAsFailed('Domain origin is invalid. Store the domain as a valid http/https origin.');

            return false;
        }

        $hostname = "_widget-verify.{$domainHost}";
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
        $baseOrigin = ProjectDomain::normalizeDomainInput($domain->domain);

        if ($baseOrigin === null) {
            $domain->markAsFailed('Domain origin is invalid. Store the domain as a valid http/https origin.');

            return false;
        }

        $httpTargetError = $this->validateHttpVerificationTarget($baseOrigin);

        if ($httpTargetError !== null) {
            Log::warning('Rejected unsafe HTTP domain verification target.', [
                'project_domain_id' => $domain->id,
                'domain' => $domain->domain,
                'reason' => $httpTargetError,
            ]);

            $domain->markAsFailed($httpTargetError);

            return false;
        }

        $url = "{$baseOrigin}/.well-known/widget-verify";

        $retries = 0;
        while ($retries < $this->maxRetries) {
            try {
                $response = Http::timeout($this->httpTimeout)
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

    protected function validateHttpVerificationTarget(string $baseOrigin): ?string
    {
        $host = parse_url($baseOrigin, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return 'Domain origin is invalid. Store the domain as a valid http/https origin.';
        }

        $normalizedHost = strtolower($host);

        if ($this->isInternalHostname($normalizedHost)) {
            return 'HTTP verification target is not allowed for localhost, internal, or reserved hostnames.';
        }

        if (filter_var($normalizedHost, FILTER_VALIDATE_IP) !== false) {
            return $this->isPublicIpAddress($normalizedHost)
                ? null
                : 'HTTP verification target is not allowed for private, reserved, or loopback IP addresses.';
        }

        $resolvedAddresses = $this->resolveHostIpAddresses($normalizedHost);

        foreach ($resolvedAddresses as $resolvedAddress) {
            if (! $this->isPublicIpAddress($resolvedAddress)) {
                return 'HTTP verification target resolves to a private, reserved, or internal IP address.';
            }
        }

        return null;
    }

    protected function isInternalHostname(string $host): bool
    {
        if ($host === 'localhost' || Str::endsWith($host, '.localhost')) {
            return true;
        }

        if (! str_contains($host, '.') && filter_var($host, FILTER_VALIDATE_IP) === false) {
            return true;
        }

        return Str::endsWith($host, [
            '.local',
            '.internal',
            '.intranet',
            '.corp',
            '.home',
            '.lan',
            '.test',
            '.example',
            '.invalid',
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected function resolveHostIpAddresses(string $host): array
    {
        $addresses = [];

        $ipv4Records = @dns_get_record($host, DNS_A);
        if (is_array($ipv4Records)) {
            foreach ($ipv4Records as $record) {
                if (isset($record['ip']) && is_string($record['ip'])) {
                    $addresses[] = $record['ip'];
                }
            }
        }

        if (defined('DNS_AAAA')) {
            $ipv6Records = @dns_get_record($host, DNS_AAAA);
            if (is_array($ipv6Records)) {
                foreach ($ipv6Records as $record) {
                    if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                        $addresses[] = $record['ipv6'];
                    }
                }
            }
        }

        return array_values(array_unique($addresses));
    }

    protected function isPublicIpAddress(string $ipAddress): bool
    {
        return filter_var(
            $ipAddress,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
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
