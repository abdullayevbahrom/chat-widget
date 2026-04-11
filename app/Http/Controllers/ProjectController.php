<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProjectController extends Controller
{
    /**
     * Display a listing of the tenant's projects.
     */
    public function index(): View
    {
        $projects = Project::latest()->paginate(10);

        return view('tenant.projects.index', compact('projects'));
    }

    /**
     * Show the form for creating a new project.
     */
    public function create(): View
    {
        $project = new Project();
        $project->is_active = true;
        $project->settings = ['widget' => [
            'theme' => 'light',
            'position' => 'bottom-right',
            'width' => 400,
            'height' => 600,
            'primary_color' => '#6366f1',
            'custom_css' => '',
        ]];

        return view('tenant.projects.form', compact('project'));
    }

    /**
     * Store a newly created project in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'theme' => ['required', 'string', 'in:light,dark,auto'],
            'position' => ['required', 'string', 'in:bottom-right,bottom-left,top-right,top-left'],
            'width' => ['required', 'integer', 'min:200', 'max:800'],
            'height' => ['required', 'integer', 'min:200', 'max:1200'],
            'primary_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'custom_css' => ['nullable', 'string', 'max:50000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user = Auth::guard('tenant_user')->user();

        $project = new Project();
        $project->tenant_id = $user->tenant_id;
        $project->name = $validated['name'];
        $project->is_active = $request->boolean('is_active', true);
        $project->settings = [
            'widget' => [
                'theme' => $validated['theme'],
                'position' => $validated['position'],
                'width' => (int) $validated['width'],
                'height' => (int) $validated['height'],
                'primary_color' => $validated['primary_color'],
                'custom_css' => $validated['custom_css'] ?? '',
            ],
        ];

        $project->save();

        // Generate widget key
        $plaintextKey = $project->generateWidgetKey();

        return redirect()
            ->route('dashboard.projects.edit', $project)
            ->with('success', 'Project created successfully.')
            ->with('widget_key', $plaintextKey);
    }

    /**
     * Show the form for editing the specified project.
     */
    public function edit(Project $project): View
    {
        return view('tenant.projects.form', compact('project'));
    }

    /**
     * Update the specified project in storage.
     */
    public function update(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'theme' => ['required', 'string', 'in:light,dark,auto'],
            'position' => ['required', 'string', 'in:bottom-right,bottom-left,top-right,top-left'],
            'width' => ['required', 'integer', 'min:200', 'max:800'],
            'height' => ['required', 'integer', 'min:200', 'max:1200'],
            'primary_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'custom_css' => ['nullable', 'string', 'max:50000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $project->name = $validated['name'];
        $project->is_active = $request->boolean('is_active', true);
        $project->settings = [
            'widget' => [
                'theme' => $validated['theme'],
                'position' => $validated['position'],
                'width' => (int) $validated['width'],
                'height' => (int) $validated['height'],
                'primary_color' => $validated['primary_color'],
                'custom_css' => $validated['custom_css'] ?? '',
            ],
        ];

        $project->save();

        return redirect()
            ->route('dashboard.projects.edit', $project)
            ->with('success', 'Project updated successfully.');
    }

    /**
     * Remove the specified project from storage.
     */
    public function destroy(Project $project): RedirectResponse
    {
        $project->delete();

        return redirect()
            ->route('dashboard.projects.index')
            ->with('success', 'Project deleted successfully.');
    }

    /**
     * Regenerate the widget key for the specified project.
     */
    public function regenerateKey(Project $project): RedirectResponse
    {
        $plaintextKey = $project->regenerateWidgetKey();

        return redirect()
            ->route('dashboard.projects.edit', $project)
            ->with('success', 'Widget key regenerated successfully.')
            ->with('widget_key', $plaintextKey);
    }
}
