<?php

namespace App\Http\Controllers\Api;

use App\Models\Project;
use App\Models\ProjectDomain;
use App\Services\DomainVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

class ProjectDomainController extends Controller
{
    /**
     * Display a listing of the project's domains.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = ProjectDomain::query();

        if ($request->has('project_id')) {
            $project = Project::find($request->input('project_id'));
            if ($project === null) {
                return response()->json(['message' => 'Project not found.'], 404);
            }

            if (! $user->isSuperAdmin() && $user->tenant_id !== $project->tenant_id) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            $query->where('project_id', $project->id);
        } else {
            // List all domains accessible by the user
            if (! $user->isSuperAdmin()) {
                if ($user->tenant_id === null) {
                    return response()->json(['data' => []]);
                }
                $query->whereHas('project', function ($q) use ($user) {
                    $q->where('tenant_id', $user->tenant_id);
                });
            }
        }

        $domains = $query->with('project:id,name,tenant_id')->latest()->paginate();

        return response()->json(['data' => $domains]);
    }

    /**
     * Store a newly created project domain.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'domain' => ['required', 'string', 'max:255', 'regex:'.config('domains.regex')],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $project = Project::findOrFail($validated['project_id']);

        if (! $user->isSuperAdmin() && $user->tenant_id !== $project->tenant_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Check uniqueness per project
        $exists = ProjectDomain::where('project_id', $project->id)
            ->where('domain', $validated['domain'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'domain' => ['This domain is already added to this project.'],
            ]);
        }

        $validated['is_active'] = $validated['is_active'] ?? true;

        $projectDomain = ProjectDomain::create($validated);

        $this->logAudit('project_domain_created', $projectDomain, $user);

        return response()->json([
            'data' => $projectDomain,
            'message' => 'Domain added successfully. Verification initiated.',
        ], 201);
    }

    /**
     * Display the specified project domain.
     */
    public function show(Request $request, ProjectDomain $domain): JsonResponse
    {
        $user = $request->user();

        if (! $user->isSuperAdmin() && $user->tenant_id !== $domain->project->tenant_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json(['data' => $domain]);
    }

    /**
     * Update the specified project domain.
     */
    public function update(Request $request, ProjectDomain $domain): JsonResponse
    {
        $user = $request->user();

        if (! $user->isSuperAdmin() && $user->tenant_id !== $domain->project->tenant_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'domain' => [
                'sometimes',
                'string',
                'max:255',
                'unique:project_domains,domain,'.$domain->id,
                'regex:'.config('domains.regex'),
            ],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $domain->update($validated);

        $this->logAudit('project_domain_updated', $domain, $user);

        return response()->json([
            'data' => $domain,
            'message' => 'Domain updated successfully.',
        ]);
    }

    /**
     * Remove the specified project domain.
     */
    public function destroy(Request $request, ProjectDomain $domain): JsonResponse
    {
        $user = $request->user();

        if (! $user->isSuperAdmin() && $user->tenant_id !== $domain->project->tenant_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $this->logAudit('project_domain_deleted', $domain, $user);

        $domain->delete();

        return response()->json(['message' => 'Domain deleted successfully.']);
    }

    /**
     * Verify the specified project domain.
     */
    public function verify(
        Request $request,
        ProjectDomain $domain,
        DomainVerificationService $verificationService,
    ): JsonResponse {
        $user = $request->user();

        if (! $user->isSuperAdmin() && $user->tenant_id !== $domain->project->tenant_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($domain->verification_status === 'verified') {
            return response()->json([
                'data' => $domain,
                'message' => 'Domain is already verified.',
            ]);
        }

        $success = $verificationService->verify($domain);

        if ($success) {
            return response()->json([
                'data' => $domain,
                'message' => 'Domain verified successfully.',
            ]);
        }

        return response()->json([
            'data' => $domain,
            'message' => 'Verification failed.',
            'error' => $domain->verification_error,
        ], 422);
    }

    /**
     * Log audit events for project domain operations.
     */
    protected function logAudit(string $event, ProjectDomain $domain, $user): void
    {
        logger()->info("Audit: {$event}", [
            'domain_id' => $domain->id,
            'domain' => $domain->domain,
            'project_id' => $domain->project_id,
            'action_by' => $user->id,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
