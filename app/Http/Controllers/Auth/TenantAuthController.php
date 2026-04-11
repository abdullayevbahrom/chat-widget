<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class TenantAuthController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::guard('tenant_user')->check()) {
            return redirect('/dashboard');
        }
        
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::guard('tenant_user')->attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended('/dashboard');
        }

        return back()->withErrors([
            'email' => __('These credentials do not match our records.'),
        ])->onlyInput('email');
    }

    public function showRegistrationForm()
    {
        if (Auth::guard('tenant_user')->check()) {
            return redirect('/dashboard');
        }
        
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()->uncompromised()],
        ]);

        $user = DB::transaction(function () use ($data) {
            $emailUsername = explode('@', $data['email'])[0];
            $baseSlug = str($emailUsername)->slug()->toString();
            $slug = $baseSlug;
            $counter = 1;

            while (Tenant::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }

            $tenant = Tenant::create([
                'name' => $emailUsername,
                'slug' => $slug,
                'is_active' => true,
                'plan' => 'free',
                'subscription_expires_at' => null,
            ]);

            $user = User::create([
                'name' => $emailUsername,
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'tenant_id' => $tenant->id,
                'is_super_admin' => false,
                'email_verified_at' => now(),
            ]);

            return $user;
        });

        Auth::guard('tenant_user')->login($user);

        return redirect('/dashboard');
    }

    public function logout(Request $request)
    {
        Auth::guard('tenant_user')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/auth/login');
    }
}
