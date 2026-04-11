<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Project;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::guard('tenant_user')->user();
        $tenant = $user->tenant;

        if (!$tenant) {
            abort(403, 'No tenant associated with this account.');
        }

        $stats = [
            'projects' => Project::where('tenant_id', $tenant->id)->count(),
            'conversations' => Conversation::whereHas('project', fn($q) => $q->where('tenant_id', $tenant->id))->count(),
            'open_conversations' => Conversation::where('status', 'open')
                ->whereHas('project', fn($q) => $q->where('tenant_id', $tenant->id))->count(),
        ];

        $recentConversations = Conversation::whereHas('project', fn($q) => $q->where('tenant_id', $tenant->id))
            ->with(['project', 'visitor'])
            ->latest()
            ->limit(10)
            ->get();

        $projects = Project::where('tenant_id', $tenant->id)->latest()->limit(5)->get();

        return view('tenant.dashboard', compact('stats', 'recentConversations', 'projects', 'tenant'));
    }
}
