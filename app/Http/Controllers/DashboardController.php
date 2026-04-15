<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Project;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Ikkala guard'dan foydalanuvchini olamiz (middleware allaqachon tekshirgan)
        $user = $request->user('web') ?? $request->user('tenant_user');

        $tenant = $user->tenant;

        if ($user->isSuperAdmin()) {
            // Super admin sees all data
            $stats = [
                'projects' => Project::count(),
                'conversations' => Conversation::whereHas('project')->count(),
                'open_conversations' => Conversation::where('status', 'open')
                    ->whereHas('project')->count(),
            ];

            $recentConversations = Conversation::whereHas('project')
                ->with(['project', 'visitor'])
                ->latest()
                ->limit(10)
                ->get();

            $projects = Project::with('tenant')->latest()->limit(5)->get();

            return view('tenant.dashboard', compact('stats', 'recentConversations', 'projects', 'tenant'));
        }

        // Regular tenant user - must have a tenant
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
