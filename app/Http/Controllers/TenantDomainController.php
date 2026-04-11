<?php

namespace App\Http\Controllers;

use App\Models\TenantDomain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TenantDomainController extends Controller
{
    /**
     * Display a listing of the tenant's domains.
     */
    public function index(): View
    {
        $domains = TenantDomain::latest()->paginate(10);

        return view('tenant.domains.index', compact('domains'));
    }

    /**
     * Show the form for creating a new domain.
     */
    public function create(): View
    {
        $domain = new TenantDomain();
        $domain->is_active = true;

        return view('tenant.domains.form', compact('domain'));
    }

    /**
     * Store a newly created domain in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'domain' => [
                'required',
                'string',
                'max:255',
                'unique:tenant_domains,domain',
                'regex:'.config('domains.regex', '/^[a-zA-Z0-9]([a-zA-Z0-9\-]*\.)*[a-zA-Z0-9\-]+\.[a-zA-Z]{2,}$/'),
            ],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = Auth::guard('tenant_user')->user();

        $domain = new TenantDomain();
        $domain->tenant_id = $user->tenant_id;
        $domain->domain = strtolower(trim($validated['domain']));
        $domain->is_active = $request->boolean('is_active', true);
        $domain->notes = $validated['notes'] ?? null;

        // Generate verification token
        $domain->generateVerificationToken();

        $domain->save();

        return redirect()
            ->route('dashboard.domains.index')
            ->with('success', 'Domain added successfully. Please verify it to activate the widget.');
    }

    /**
     * Show the form for editing the specified domain.
     */
    public function edit(TenantDomain $domain): View
    {
        return view('tenant.domains.form', compact('domain'));
    }

    /**
     * Update the specified domain in storage.
     */
    public function update(Request $request, TenantDomain $domain): RedirectResponse
    {
        $validated = $request->validate([
            'domain' => [
                'required',
                'string',
                'max:255',
                'unique:tenant_domains,domain,'.$domain->id,
                'regex:'.config('domains.regex', '/^[a-zA-Z0-9]([a-zA-Z0-9\-]*\.)*[a-zA-Z0-9\-]+\.[a-zA-Z]{2,}$/'),
            ],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $domain->domain = strtolower(trim($validated['domain']));
        $domain->is_active = $request->boolean('is_active', true);
        $domain->notes = $validated['notes'] ?? null;

        $domain->save();

        return redirect()
            ->route('dashboard.domains.index')
            ->with('success', 'Domain updated successfully.');
    }

    /**
     * Remove the specified domain from storage.
     */
    public function destroy(TenantDomain $domain): RedirectResponse
    {
        $domain->delete();

        return redirect()
            ->route('dashboard.domains.index')
            ->with('success', 'Domain deleted successfully.');
    }

    /**
     * Verify the specified domain.
     */
    public function verify(Request $request, TenantDomain $domain): RedirectResponse
    {
        // Check if the domain is already verified
        if ($domain->is_verified) {
            return redirect()
                ->route('dashboard.domains.index')
                ->with('error', 'This domain is already verified.');
        }

        // Check verification token if provided via query param
        $token = $request->query('token');
        if ($token !== null && $token === $domain->verification_token) {
            $domain->markAsVerified();

            return redirect()
                ->route('dashboard.domains.index')
                ->with('success', 'Domain verified successfully!');
        }

        // Generate a new verification token
        $token = $domain->generateVerificationToken();

        return redirect()
            ->route('dashboard.domains.index')
            ->with('success', 'Verification token generated. Add it to your domain\'s DNS or verify via token.')
            ->with('verification_token', $token);
    }

    /**
     * Re-verify the specified domain (generate new token).
     */
    public function reverify(TenantDomain $domain): RedirectResponse
    {
        $token = $domain->generateVerificationToken();

        return redirect()
            ->route('dashboard.domains.index')
            ->with('success', 'New verification token generated.')
            ->with('verification_token', $token);
    }
}
