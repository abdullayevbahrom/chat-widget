<?php

namespace App\Http\Controllers\Api;

use App\Models\Project;
use App\Models\Tenant;
use App\Services\WidgetKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

class ProjectController extends Controller
{
    public function __construct(
        protected WidgetKeyService $widgetKeyService,
    ) {}

    /**
     * Display a listing of the tenant's projects.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Project::query();

        if ($user->isSuperAdmin()) {
            // Super admins can see all projects
        } else {
            if ($user->tenant_id === null) {
                return response()->json(['data' => []]);
            }
            $query->where('tenant_id', $user->tenant_id);
        }

        $projects = $query->latest()->paginate();

        return response()->json(['data' => $projects]);
    }

    /**
     * Store a newly created project.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'alpha_dash', 'max:255'],
            'description' => ['nullable', 'string'],
            'primary_domain' => ['nullable', 'string', 'max:255'],
            'settings' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);

        $tenantId = $user->isSuperAdmin()
            ? $request->validate(['tenant_id' => ['required', 'exists:tenants,id']])['tenant_id']
            : $user->tenant_id;

        if ($tenantId === null) {
            return response()->json(['message' => 'No tenant associated with this user.'], 403);
        }

        // Auto-generate slug from name if not provided
        if (empty($validated['slug'])) {
            $baseSlug = \Illuminate\Support\Str::slug($validated['name']);
            $slug = $baseSlug;
            $counter = 1;

            // Ensure slug is unique per tenant
            while (Project::where('tenant_id', $tenantId)->where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }

            $validated['slug'] = $slug;
        }

        Tenant::findOrFail($tenantId);

        // Check uniqueness of slug per tenant
        $exists = Project::where('tenant_id', $tenantId)
            ->where('slug', $validated['slug'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'slug' => ['This slug is already in use for your tenant.'],
            ]);
        }

        $validated['tenant_id'] = $tenantId;
        $validated['is_active'] = $validated['is_active'] ?? true;

        $project = Project::create($validated);

        // Auto-generate widget key
        $widgetKey = $this->widgetKeyService->generateKey($project);

        $this->logAudit('project_created', $project, $user);

        return response()->json([
            'data' => $project,
            'widget_key' => $widgetKey,
            'message' => 'Project created successfully. Widget key generated — copy it now, it will not be shown again.',
        ], 201);
    }

    /**
     * Display the specified project.
     */
    public function show(Request $request, Project $project): JsonResponse
    {
        $user = $request->user();

        if (! $user->isSuperAdmin() && $user->tenant_id !== $project->tenant_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json(['data' => $project]);
    }

    /**
     * Update the specified project.
     */
    public function update(Request $request, Project $project): JsonResponse
    {
        $user = $request->user();

        if (! $user->isSuperAdmin() && $user->tenant_id !== $project->tenant_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'alpha_dash', 'max:255'],
            'description' => ['nullable', 'string'],
            'primary_domain' => ['nullable', 'string', 'max:255'],
            'settings' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);

        // Check uniqueness of slug per tenant if changing
        if (isset($validated['slug'])) {
            $exists = Project::where('tenant_id', $project->tenant_id)
                ->where('slug', $validated['slug'])
                ->where('id', '!=', $project->id)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'slug' => ['This slug is already in use for your tenant.'],
                ]);
            }
        }

        $project->update($validated);

        $this->logAudit('project_updated', $project, $user);

        return response()->json([
            'data' => $project,
            'message' => 'Project updated successfully.',
        ]);
    }

    /**
     * Remove the specified project.
     */
    public function destroy(Request $request, Project $project): JsonResponse
    {
        $user = $request->user();

        if (! $user->isSuperAdmin() && $user->tenant_id !== $project->tenant_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $hasActiveConversations = $project->hasActiveConversations();
        $activeConversationsCount = $project->activeConversationsCount();

        $this->logAudit('project_deleted', $project, $user);

        $project->delete();

        $response = ['message' => 'Project deleted successfully.'];

        if ($hasActiveConversations) {
            $response['warning'] = "This project had {$activeConversationsCount} active conversation(s) that were also deleted.";
        }

        return response()->json($response);
    }

    /**
     * Regenerate the widget key for the specified project.
     */
    public function regenerateKey(Request $request, Project $project): JsonResponse
    {
        $user = $request->user();

        if (! $user->isSuperAdmin() && $user->tenant_id !== $project->tenant_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $newKey = $this->widgetKeyService->regenerateKey($project);

        $this->logAudit('project_key_regenerated', $project, $user);

        return response()->json([
            'data' => $project,
            'widget_key' => $newKey,
            'message' => 'Widget key regenerated. Old key is now invalid.',
        ]);
    }

    /**
     * Revoke the widget key for the specified project.
     */
    public function revokeKey(Request $request, Project $project): JsonResponse
    {
        $user = $request->user();

        if (! $user->isSuperAdmin() && $user->tenant_id !== $project->tenant_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $this->widgetKeyService->revokeKey($project);

        $this->logAudit('project_key_revoked', $project, $user);

        return response()->json([
            'data' => $project,
            'message' => 'Widget key revoked successfully.',
        ]);
    }

    /**
     * Log audit events for project operations.
     */
    protected function logAudit(string $event, Project $project, $user): void
    {
        logger()->info("Audit: {$event}", [
            'project_id' => $project->id,
            'project_name' => $project->name,
            'tenant_id' => $project->tenant_id,
            'action_by' => $user->id,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
