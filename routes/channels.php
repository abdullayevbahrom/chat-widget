<?php

use App\Models\Conversation;
use App\Services\WidgetBootstrapService;
use App\Services\WidgetKeyService;
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

/**
 * Trusted origins for WebSocket connections.
 * Only requests from these origins will be allowed.
 */
function isTrustedOrigin(string $origin): bool
{
    $trustedOrigins = array_filter([
        config('app.url'),
        ...config('cors.allowed_origins', []),
    ]);

    if (empty($trustedOrigins)) {
        Log::warning('WebSocket auth: no trusted origins configured.');

        return false;
    }

    foreach ($trustedOrigins as $trusted) {
        if (rtrim($origin, '/') === rtrim($trusted, '/')) {
            return true;
        }
    }

    return false;
}

// Tenant private channel authorization.
// Only authenticated users belonging to the tenant can listen.
Broadcast::channel('tenant.{tenantId}.conversations', function (Request $request, int $tenantId) {
    $origin = $request->header('Origin');

    if ($origin && ! isTrustedOrigin($origin)) {
        Log::warning('Tenant broadcast auth rejected: untrusted origin.', [
            'tenant_id' => $tenantId,
            'origin' => $origin,
        ]);

        return false;
    }

    // Must be authenticated as tenant user
    $user = $request->user();

    if ($user === null || $user->tenant_id !== $tenantId) {
        Log::warning('Tenant broadcast auth rejected: user not authorized for tenant.', [
            'tenant_id' => $tenantId,
            'user_id' => $user?->id,
        ]);

        return false;
    }

    // Additional check: ensure the tenant context matches
    $currentTenant = \App\Models\Tenant::current();

    if ($currentTenant !== null && $currentTenant->id !== $tenantId) {
        Log::warning('Tenant broadcast auth rejected: tenant context mismatch.', [
            'requested_tenant_id' => $tenantId,
            'context_tenant_id' => $currentTenant->id,
        ]);

        return false;
    }

    return true;
});

// Widget private channel authorization.
// Widget visitors authenticate via the bootstrap token / widget key
// passed through Echo's authorizer (X-Widget-Bootstrap / X-Widget-Key headers).
//
// Authorization validates that the requesting token/key belongs to the
// conversation being accessed, preventing unauthorized cross-conversation listening.
Broadcast::channel('widget.conversation.{conversationId}', function (Request $request, int $conversationId) {
    $origin = $request->header('Origin');

    if ($origin && ! isTrustedOrigin($origin)) {
        Log::warning('Widget broadcast auth rejected: untrusted origin.', [
            'conversation_id' => $conversationId,
            'origin' => $origin,
        ]);

        return false;
    }

    $bootstrapToken = $request->header('X-Widget-Bootstrap');
    $widgetKey = $request->header('X-Widget-Key');
    $visitorId = $request->input('visitor_id');

    if (! $bootstrapToken && ! $widgetKey) {
        Log::warning('Widget broadcast auth rejected: no authentication headers.', [
            'conversation_id' => $conversationId,
        ]);

        return false;
    }

    // visitor_id is mandatory to prevent BOLA (Broken Object Level Authorization)
    if (! $visitorId) {
        Log::warning('Widget broadcast auth rejected: missing visitor_id.', [
            'conversation_id' => $conversationId,
        ]);

        return false;
    }

    // Validate the conversation exists and belongs to an active project
    $conversation = Conversation::with('project')->find($conversationId);

    if (! $conversation || ! $conversation->project) {
        Log::warning('Widget broadcast auth rejected: conversation or project not found.', [
            'conversation_id' => $conversationId,
        ]);

        return false;
    }

    if (! $conversation->project->is_active) {
        Log::warning('Widget broadcast auth rejected: project is inactive.', [
            'conversation_id' => $conversationId,
            'project_id' => $conversation->project->id,
        ]);

        return false;
    }

    // Verify visitor ID matches the conversation's visitor to prevent ID enumeration
    if ((int) $visitorId !== $conversation->visitor_id) {
        Log::warning('Widget broadcast auth rejected: visitor ID mismatch.', [
            'conversation_id' => $conversationId,
            'provided_visitor_id' => $visitorId,
            'conversation_visitor_id' => $conversation->visitor_id,
        ]);

        return false;
    }

    // If using widget key, validate it matches the conversation's project
    if ($widgetKey) {
        /** @var WidgetKeyService $keyService */
        $keyService = app(WidgetKeyService::class);
        $project = $keyService->validateKey($widgetKey);

        if (! $project || $project->id !== $conversation->project->id) {
            Log::warning('Widget broadcast auth rejected: widget key does not match conversation project.', [
                'conversation_id' => $conversationId,
                'expected_project_id' => $conversation->project->id,
            ]);

            return false;
        }
    }

    // If using bootstrap token, validate it and check origin match
    if ($bootstrapToken) {
        /** @var WidgetBootstrapService $bootstrapService */
        $bootstrapService = app(WidgetBootstrapService::class);
        $tokenPayload = $bootstrapService->decodeToken($bootstrapToken);

        if (! $tokenPayload || $tokenPayload['project_id'] !== $conversation->project->id) {
            Log::warning('Widget broadcast auth rejected: bootstrap token invalid or project mismatch.', [
                'conversation_id' => $conversationId,
                'expected_project_id' => $conversation->project->id,
            ]);

            return false;
        }

        // Additionally verify visitor ID from token if available
        if ($visitorId && isset($tokenPayload['visitor_id']) && (int) $tokenPayload['visitor_id'] !== $visitorId) {
            Log::warning('Widget broadcast auth rejected: visitor ID mismatch with bootstrap token.', [
                'conversation_id' => $conversationId,
                'token_visitor_id' => $tokenPayload['visitor_id'],
                'provided_visitor_id' => $visitorId,
            ]);

            return false;
        }
    }

    return true;
});
