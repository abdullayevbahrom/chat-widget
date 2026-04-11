<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WidgetAttachmentController extends Controller
{
    /**
     * Serve a private attachment download with authorization check.
     */
    public function download(Request $request, int $projectId, int $conversationId, string $fileName): StreamedResponse
    {
        // Validate fileName to prevent path traversal attacks
        if (! $this->isValidFileName($fileName)) {
            Log::warning('Attachment download rejected: invalid file name.', [
                'project_id' => $projectId,
                'conversation_id' => $conversationId,
                'file_name' => $fileName,
            ]);

            abort(400, 'Invalid file name');
        }

        /** @var Project|null $project */
        $project = $request->get('project');

        if ($project === null || $project->id !== $projectId) {
            Log::warning('Attachment download rejected: project mismatch.', [
                'project_id' => $projectId,
                'conversation_id' => $conversationId,
                'file_name' => $fileName,
            ]);

            abort(403, 'Forbidden');
        }

        $visitor = $this->resolveBoundVisitor($request, $project);

        if ($visitor === null) {
            Log::warning('Attachment download rejected: visitor not authenticated.', [
                'project_id' => $projectId,
                'conversation_id' => $conversationId,
                'file_name' => $fileName,
            ]);

            abort(403, 'Forbidden');
        }

        // Verify the conversation belongs to this visitor
        $conversation = Conversation::withoutGlobalScopes()
            ->where('id', $conversationId)
            ->where('project_id', $projectId)
            ->where('visitor_id', $visitor->id)
            ->first();

        if ($conversation === null) {
            Log::warning('Attachment download rejected: conversation does not belong to visitor.', [
                'project_id' => $projectId,
                'conversation_id' => $conversationId,
                'visitor_id' => $visitor->id,
                'file_name' => $fileName,
            ]);

            abort(403, 'Forbidden');
        }

        // Find the file in conversation messages
        $attachment = $this->findAttachmentInConversation($conversation, $fileName);

        if ($attachment === null) {
            Log::warning('Attachment download rejected: file not found in conversation.', [
                'project_id' => $projectId,
                'conversation_id' => $conversationId,
                'file_name' => $fileName,
            ]);

            abort(404, 'Attachment not found');
        }

        $storagePath = $attachment['path'] ?? null;

        // Validate that the storage path is within the conversation's directory
        if ($storagePath === null || ! $this->isPathWithinConversation($storagePath, $projectId, $conversationId)) {
            Log::warning('Attachment download rejected: path traversal attempt detected.', [
                'project_id' => $projectId,
                'conversation_id' => $conversationId,
                'storage_path' => $storagePath,
            ]);

            abort(403, 'Forbidden');
        }

        if (! Storage::disk('private')->exists($storagePath)) {
            Log::warning('Attachment file does not exist on disk.', [
                'project_id' => $projectId,
                'conversation_id' => $conversationId,
                'storage_path' => $storagePath,
            ]);

            abort(404, 'Attachment not found');
        }

        Log::info('Serving private attachment download.', [
            'project_id' => $projectId,
            'conversation_id' => $conversationId,
            'file_name' => $fileName,
            'visitor_id' => $visitor->id,
        ]);

        return Storage::disk('private')->download(
            $storagePath,
            $attachment['original_name'] ?? $attachment['name'] ?? $fileName
        );
    }

    /**
     * Validate that a file name is safe (no path traversal).
     */
    protected function isValidFileName(string $fileName): bool
    {
        // Reject empty names
        if (trim($fileName) === '') {
            return false;
        }

        // Reject path traversal patterns
        if (str_contains($fileName, '..') || str_contains($fileName, '/') || str_contains($fileName, '\\')) {
            return false;
        }

        // Reject null bytes
        if (str_contains($fileName, "\0")) {
            return false;
        }

        // Only allow alphanumeric, dots, hyphens, underscores
        return preg_match('/^[a-zA-Z0-9._\-]+$/', $fileName) === 1;
    }

    /**
     * Validate that a storage path is within the expected conversation directory.
     */
    protected function isPathWithinConversation(string $storagePath, int $projectId, int $conversationId): bool
    {
        $normalizedPath = str_replace('\\', '/', $storagePath);
        $expectedPrefix = sprintf(
            'widget-attachments/project-%d/conversation-%d/',
            $projectId,
            $conversationId,
        );

        if (! str_starts_with($normalizedPath, $expectedPrefix)) {
            return false;
        }

        if (str_contains($normalizedPath, '../')) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function findAttachmentInConversation(Conversation $conversation, string $fileName): ?array
    {
        // Use eager loading with a limit to prevent loading all messages
        // and query the JSON column directly to narrow down results
        $messages = $conversation->messages()
            ->where('attachments', 'like', '%"name":"'.str_replace(['%', '_'], ['\%', '\_'], $fileName).'"%')
            ->get(['id', 'attachments']);

        foreach ($messages as $message) {
            $attachments = $message->attachments ?? [];

            foreach ($attachments as $attachment) {
                if (($attachment['name'] ?? '') === $fileName) {
                    return $attachment;
                }
            }
        }

        return null;
    }

    protected function resolveBoundVisitor(Request $request, Project $project): ?\App\Models\Visitor
    {
        $token = $this->getVisitorTokenFromRequest($request);

        if (blank($token)) {
            return null;
        }

        $payload = $this->decodeVisitorToken($token);

        if ($payload === null || ($payload['project_id'] ?? null) !== $project->id) {
            return null;
        }

        return \App\Models\Visitor::query()
            ->whereKey($payload['visitor_id'])
            ->where('tenant_id', $project->tenant_id)
            ->where('session_id', $payload['session_id'])
            ->first();
    }

    protected function getVisitorTokenFromRequest(Request $request): ?string
    {
        /** @var Project|null $project */
        $project = $request->get('project');

        return $project !== null ? $request->cookie('widget_visitor_'.$project->id) : null;
    }

    /**
     * @return array{project_id:int, visitor_id:int, session_id:string}|null
     */
    protected function decodeVisitorToken(string $token): ?array
    {
        try {
            /** @var mixed $decoded */
            $decoded = json_decode(\Illuminate\Support\Facades\Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            return null;
        }

        if (
            ! is_array($decoded)
            || ! isset($decoded['project_id'], $decoded['visitor_id'], $decoded['session_id'])
            || ! is_int($decoded['project_id'])
            || ! is_int($decoded['visitor_id'])
            || ! is_string($decoded['session_id'])
        ) {
            return null;
        }

        return $decoded;
    }
}
