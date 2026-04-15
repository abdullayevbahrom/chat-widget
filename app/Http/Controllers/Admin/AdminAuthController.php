<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAuthController extends Controller
{
    /**
     * Log out the admin user and redirect to home page.
     */
    public function logout(Request $request)
    {
        // Ikkala guard'ni ham logout qilamiz (web va tenant_user bitta session ishlatadi)
        Auth::guard('web')->logout();
        Auth::guard('tenant_user')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
