<?php

namespace App\Http\Controllers\Api;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Scopes\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TenantDomainController extends Controller
{
    /**
     * Display a listing of the tenant's domains.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = TenantDomain::query()->withoutGlobalScope(TenantScope::class);

        if ($user->isSuperAdmin()) {
            // Super admins can see all domains
        } else {
            // Non-super-admin users must have a tenant_id
            if ($user->tenant_id === null) {
                return response()->json([
                    'data' => [],
                ]);
            }
            $query->where('tenant_id', $user->tenant_id);
        }

        $domains = $query->latest()->paginate();

        return response()->json([
            'data' => $domains,
        ]);
    }

    /**
     * Store a newly created domain.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'domain' => [
                'required',
                'string',
                'max:255',
                'unique:tenant_domains,domain',
                'regex:'.config('domains.regex'),
            ],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        // For super admins, validate tenant_id if provided
        if ($user->isSuperAdmin()) {
            $request->validate(['tenant_id' => ['required', 'exists:tenants,id']]);
            $validated['tenant_id'] = $request->input('tenant_id');
        } else {
            if ($user->tenant_id === null) {
                return response()->json([
                    'message' => 'No tenant associated with this user.',
                ], 403);
            }
            $validated['tenant_id'] = $user->tenant_id;
        }

        // Verify tenant exists
        Tenant::findOrFail($validated['tenant_id']);

        $validated['is_active'] = $validated['is_active'] ?? false;

        $domain = TenantDomain::create($validated);

        $this->logAudit('tenant_domain_created', $domain, $user);

        return response()->json([
            'data' => $domain,
            'message' => 'Domain added successfully.',
        ], 201);
    }

    /**
     * Display the specified domain.
     */
    public function show(Request $request, TenantDomain $domain): JsonResponse
    {
        $user = $request->user();

        if (! $user->isSuperAdmin() && $user->tenant_id !== $domain->tenant_id) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        return response()->json([
            'data' => $domain,
        ]);
    }

    /**
     * Update the specified domain.
     */
    public function update(Request $request, TenantDomain $domain): JsonResponse
    {
        $user = $request->user();

        if (! $user->isSuperAdmin() && $user->tenant_id !== $domain->tenant_id) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $validated = $request->validate([
            'domain' => [
                'sometimes',
                'string',
                'max:255',
                'unique:tenant_domains,domain,'.$domain->id,
                'regex:'.config('domains.regex'),
            ],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $domain->update($validated);

        $this->logAudit('tenant_domain_updated', $domain, $user);

        return response()->json([
            'data' => $domain,
            'message' => 'Domain updated successfully.',
        ]);
    }

    /**
     * Remove the specified domain.
     */
    public function destroy(Request $request, TenantDomain $domain): JsonResponse
    {
        $user = $request->user();

        if (! $user->isSuperAdmin() && $user->tenant_id !== $domain->tenant_id) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $this->logAudit('tenant_domain_deleted', $domain, $user);

        $domain->delete();

        return response()->json([
            'message' => 'Domain deleted successfully.',
        ]);
    }

    /**
     * Log audit events for tenant domain operations.
     */
    protected function logAudit(string $event, TenantDomain $domain, $user): void
    {
        logger()->info("Audit: {$event}", [
            'domain_id' => $domain->id,
            'domain' => $domain->domain,
            'tenant_id' => $domain->tenant_id,
            'action_by' => $user->id,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
