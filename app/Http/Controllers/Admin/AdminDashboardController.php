<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'tenants' => Tenant::count(),
            'active_tenants' => Tenant::where('is_active', true)->count(),
            'users' => User::count(),
            'projects' => Project::count(),
            'conversations' => Conversation::count(),
        ];

        $recentTenants = Tenant::with('users')->latest()->limit(10)->get();
        $recentUsers = User::with('tenant')->latest()->limit(10)->get();
        $recentProjects = Project::with(['tenant', 'conversations'])->latest()->limit(10)->get();

        return view('admin.dashboard', compact('stats', 'recentTenants', 'recentUsers', 'recentProjects'));
    }
}
