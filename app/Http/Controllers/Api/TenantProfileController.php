<?php

namespace App\Http\Controllers\Api;

use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TenantProfileController extends Controller
{
    /**
     * Display the authenticated user's tenant profile.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->tenant->id === null) {
            return response()->json([
                'message' => 'No tenant associated with this user.',
            ], 404);
        }

        $tenant = Tenant::findOrFail($user->tenant->id);

        return response()->json([
            'data' => $tenant,
        ]);
    }

    /**
     * Update the authenticated user's tenant profile.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->tenant->id === null) {
            return response()->json([
                'message' => 'No tenant associated with this user.',
            ], 404);
        }

        $tenant = Tenant::findOrFail($user->tenant->id);

        $validated = $request->validate([
            'company_name' => ['sometimes', 'required', 'string', 'max:255'],
            'company_registration_number' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:255'],
            'company_address' => ['nullable', 'string'],
            'company_city' => ['nullable', 'string', 'max:255'],
            'company_country' => ['nullable', 'string', 'size:2', Rule::in(array_keys(config('countries')))],
            'contact_phone' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'primary_contact_name' => ['nullable', 'string', 'max:255'],
            'primary_contact_title' => ['nullable', 'string', 'max:255'],
            'settings' => ['nullable', 'array'],
        ]);

        // Sanitize settings JSON input
        if (isset($validated['settings']) && is_array($validated['settings'])) {
            $validated['settings'] = $this->sanitizeSettings($validated['settings']);
        }

        // Handle logo path: validate, prevent path traversal, delete old on replacement
        if (!empty($validated['logo_path'])) {
            // Prevent path traversal: only allow filenames in tenant-logos directory
            $basePath = 'tenant-logos/';
            $logoPath = $validated['logo_path'];

            // Extract only the filename to prevent directory traversal
            $filename = basename($logoPath);

            // Validate that the filename looks like a legitimate image
            if (!preg_match('/^[a-zA-Z0-9_\-]+\.(jpg|jpeg|png|gif|webp|svg)$/i', $filename)) {
                return response()->json([
                    'message' => 'Invalid logo path. Only image filenames (jpg, png, gif, webp, svg) are allowed.',
                ], 422);
            }

            // Reconstruct the safe path
            $safePath = $basePath . $filename;

            // Verify the file exists within the allowed directory
            $fullPath = storage_path('app/public/' . $safePath);
            $realPath = realpath($fullPath);
            $allowedDir = realpath(storage_path('app/public/' . $basePath));

            if ($realPath === false || $allowedDir === false || !str_starts_with($realPath, $allowedDir)) {
                return response()->json([
                    'message' => 'Logo file not found in the allowed directory.',
                ], 422);
            }

            // Use the validated path
            $validated['logo_path'] = $safePath;

            // Delete old logo if it exists and is different
            if ($tenant->logo_path && $tenant->logo_path !== $safePath) {
                Storage::disk('public')->delete($tenant->logo_path);
            }
        }

        $tenant->update($validated);

        $this->logAudit('tenant_profile_updated_via_api', $tenant, $user);

        return response()->json([
            'data' => $tenant,
            'message' => 'Profile updated successfully.',
        ]);
    }

    /**
     * Sanitize settings array to prevent injection of unexpected keys and values.
     * Only allows scalar values (string, int, bool, float, null).
     * Blocks prototype injection keys like __proto__, constructor, prototype.
     */
    protected function sanitizeSettings(array $settings): array
    {
        $sanitized = [];
        $blockedKeys = ['__proto__', 'constructor', 'prototype'];

        foreach ($settings as $key => $value) {
            // Only allow string keys and scalar values (fix operator precedence with explicit grouping)
            if (is_string($key) && (is_scalar($value) || $value === null)) {
                // Block prototype injection keys
                if (in_array($key, $blockedKeys, true)) {
                    continue;
                }
                // Strip tags from string values
                $sanitized[$key] = is_string($value) ? strip_tags($value) : $value;
            }
        }

        return $sanitized;
    }

    /**
     * Log audit events for tenant profile changes.
     */
    protected function logAudit(string $event, Tenant $tenant, $user): void
    {
        logger()->info("Audit: {$event}", [
            'tenant_id' => $tenant->id,
            'tenant_slug' => $tenant->slug,
            'updated_by' => $user->id,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
