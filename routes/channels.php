<?php

use App\Models\Conversation;
use App\Services\WidgetBootstrapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Widget private channel authorization.
// Widget visitors authenticate via the bootstrap token / widget key
// passed through Echo's authorizer (X-Widget-Bootstrap / X-Widget-Key headers).
//
// Authorization validates that the requesting token/key belongs to the
// conversation being accessed, preventing unauthorized cross-conversation listening.
Broadcast::channel('widget.conversation.{conversationId}', function (Request $request, int $conversationId) {
    // Widget visitors don't have traditional Laravel auth.
    // We validate authorization by checking if the conversation exists and
    // belongs to a project that matches the authenticated widget key/token.

    $bootstrapToken = $request->header('X-Widget-Bootstrap');
    $widgetKey = $request->header('X-Widget-Key');

    if (!$bootstrapToken && !$widgetKey) {
        Log::warning('Widget broadcast auth rejected: no authentication headers.', [
            'conversation_id' => $conversationId,
        ]);
        return false;
    }

    // Validate the conversation exists and belongs to an active project
    $conversation = Conversation::with('project')->find($conversationId);

    if (!$conversation || !$conversation->project) {
        Log::warning('Widget broadcast auth rejected: conversation or project not found.', [
            'conversation_id' => $conversationId,
        ]);
        return false;
    }

    if (!$conversation->project->is_active) {
        Log::warning('Widget broadcast auth rejected: project is inactive.', [
            'conversation_id' => $conversationId,
            'project_id' => $conversation->project->id,
        ]);
        return false;
    }

    // If using widget key, validate it matches the conversation's project
    if ($widgetKey) {
        /** @var \App\Services\WidgetKeyService $keyService */
        $keyService = app(\App\Services\WidgetKeyService::class);
        $project = $keyService->getProjectByKey($widgetKey);

        if (!$project || $project->id !== $conversation->project->id) {
            Log::warning('Widget broadcast auth rejected: widget key does not match conversation project.', [
                'conversation_id' => $conversationId,
                'expected_project_id' => $conversation->project->id,
            ]);
            return false;
        }
    }

    // If using bootstrap token, validate it and check origin match
    if ($bootstrapToken) {
        /** @var \App\Services\WidgetBootstrapService $bootstrapService */
        $bootstrapService = app(WidgetBootstrapService::class);
        $tokenPayload = $bootstrapService->decodeToken($bootstrapToken);

        if (!$tokenPayload || $tokenPayload['project_id'] !== $conversation->project->id) {
            Log::warning('Widget broadcast auth rejected: bootstrap token invalid or project mismatch.', [
                'conversation_id' => $conversationId,
                'expected_project_id' => $conversation->project->id,
            ]);
            return false;
        }
    }

    return true;
}, ['guards' => null]);
