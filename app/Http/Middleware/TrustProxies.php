<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

/**
 * Trust Proxies Middleware
 *
 * Configure trusted proxies based on environment variable.
 * In production, set TRUSTED_PROXIES to your load balancer/proxy IPs.
 */
class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies;

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;

    /**
     * Create a new middleware instance.
     */
    public function __construct()
    {
        $trustedProxies = env('TRUSTED_PROXIES');

        // Only trust proxies if explicitly configured
        if (! empty($trustedProxies)) {
            // Never trust wildcard in production
            if ($trustedProxies === '*') {
                $this->proxies = null;
            } else {
                $this->proxies = array_map('trim', explode(',', $trustedProxies));
            }
        }
    }
}
