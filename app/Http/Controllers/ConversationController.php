<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ConversationController extends Controller
{
    /**
     * Display a listing of the tenant's conversations.
     */
    public function index(Request $request): View
    {
        $user = Auth::guard('tenant_user')->user();
        $tenant = $user->tenant;

        if (!$tenant) {
            abort(403, 'No tenant associated with this account.');
        }

        $query = Conversation::where('tenant_id', $tenant->id)->with(['visitor', 'project', 'latestMessages.sender'])
            ->orderBy('updated_at', 'desc');

        // Filter by status
        if ($request->filled('status')) {
            $status = $request->query('status');
            if (in_array($status, [Conversation::STATUS_OPEN, Conversation::STATUS_CLOSED, Conversation::STATUS_ARCHIVED], true)) {
                $query->where('status', $status);
            }
        }

        // Filter by project
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->query('project_id'));
        }

        $conversations = $query->paginate(15)->withQueryString();

        // Get tenant's projects for filter dropdown (TenantScope already applied)
        $projects = Project::where('tenant_id', $tenant->id)->orderBy('name')->get();

        return view('tenant.conversations.index', compact('conversations', 'projects'));
    }

    /**
     * Display the specified conversation.
     */
    public function show(Conversation $conversation): View
    {
        $conversation->load(['visitor', 'project', 'messages.sender' => function ($query) {
            $query->withTrashed();
        }]);

        $messages = $conversation->messages()->with('sender')->orderBy('created_at', 'asc')->get();

        return view('tenant.conversations.show', compact('conversation', 'messages'));
    }

    /**
     * Mark the conversation as closed.
     */
    public function close(Conversation $conversation): RedirectResponse
    {
        $user = Auth::guard('tenant_user')->user();

        try {
            $conversation->close($user->id);
        } catch (\LogicException $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('dashboard.conversations.show', $conversation)
            ->with('success', 'Conversation closed successfully.');
    }

    /**
     * Mark the conversation as open (reopened).
     */
    public function reopen(Conversation $conversation): RedirectResponse
    {
        try {
            $conversation->reopen();
        } catch (\LogicException $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('dashboard.conversations.show', $conversation)
            ->with('success', 'Conversation reopened successfully.');
    }

    /**
     * Mark the conversation as archived.
     */
    public function archive(Conversation $conversation): RedirectResponse
    {
        try {
            $conversation->archive();
        } catch (\LogicException $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('dashboard.conversations.index')
            ->with('success', 'Conversation archived successfully.');
    }
}
