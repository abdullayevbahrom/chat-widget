<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminTenantController extends Controller
{
    public function index()
    {
        $tenants = Tenant::withCount('users', 'projects')
            ->latest()
            ->paginate(20);
        return view('admin.tenants.index', compact('tenants'));
    }

    public function create()
    {
        return view('admin.tenants.form', ['tenant' => null]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|unique:tenants,slug|max:255',
            'plan' => 'required|in:free,pro,enterprise',
            'is_active' => 'boolean',
            'user_email' => 'required|email|unique:users,email',
            'user_password' => 'required|min:8',
        ]);

        $tenant = Tenant::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'plan' => $data['plan'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['user_email'],
            'password' => Hash::make($data['user_password']),
            'tenant_id' => $tenant->id,
            'is_super_admin' => false,
        ]);

        return redirect()->route('admin.tenants.index')->with('success', 'Tenant created successfully.');
    }

    public function edit(Tenant $tenant)
    {
        return view('admin.tenants.form', compact('tenant'));
    }

    public function update(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:tenants,slug,' . $tenant->id,
            'plan' => 'required|in:free,pro,enterprise',
            'is_active' => 'boolean',
        ]);

        $tenant->update($data);
        return redirect()->route('admin.tenants.index')->with('success', 'Tenant updated successfully.');
    }

    public function destroy(Tenant $tenant)
    {
        DB::transaction(function () use ($tenant): void {
            User::where('tenant_id', $tenant->id)->delete();
            $tenant->delete();
        });

        return redirect()->route('admin.tenants.index')->with('success', 'Tenant deleted successfully.');
    }
}
