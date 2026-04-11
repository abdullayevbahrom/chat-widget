<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class TenantProfileController extends Controller
{
    /**
     * Display the tenant profile page.
     */
    public function index(): View
    {
        $user = Auth::guard('tenant_user')->user();
        $tenant = $user->tenant;

        return view('tenant.profile', compact('tenant'));
    }

    /**
     * Update the tenant profile.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = Auth::guard('tenant_user')->user();
        $tenant = $user->tenant;

        $validated = $request->validate([
            'company_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'company_address' => ['nullable', 'string', 'max:1000'],
            'company_city' => ['nullable', 'string', 'max:255'],
            'company_country' => ['nullable', 'string', 'size:2'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'primary_contact_name' => ['nullable', 'string', 'max:255'],
            'primary_contact_title' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
        ]);

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($tenant->logo_path && Storage::disk('public')->exists($tenant->logo_path)) {
                Storage::disk('public')->delete($tenant->logo_path);
            }

            $logoPath = $request->file('logo')->store('logos', 'public');
            $validated['logo_path'] = $logoPath;
        }

        // Remove logo from validated array (handled separately)
        unset($validated['logo']);

        $tenant->update($validated);

        return redirect()
            ->route('dashboard.profile')
            ->with('success', 'Profile updated successfully.');
    }
}
