<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Project;
use App\Services\TelegramBotService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MessageAttachmentService
{
    public const MAX_ATTACHMENTS = 3;

    public const MAX_FILE_SIZE_KB = 5120;

    /**
     * Persist widget-uploaded attachments and return normalized metadata.
     *
     * @param  array<int, UploadedFile>  $attachments
     * @return array<int, array<string, mixed>>
     */
    public function storeUploadedAttachments(array $attachments, Project $project, Conversation $conversation): array
    {
        Log::info('Persisting widget-uploaded attachments.', [
            'project_id' => $project->id,
            'conversation_id' => $conversation->id,
            'attachment_count' => count($attachments),
        ]);

        return array_values(array_filter(array_map(
            fn (mixed $attachment): ?array => $attachment instanceof UploadedFile
                ? $this->storeUploadedFile($attachment, $project, $conversation)
                : null,
            $attachments,
        )));
    }

    /**
     * Persist a Telegram attachment locally and return normalized metadata.
     *
     * @param  array<string, mixed>  $telegramMessage
     * @return array<int, array<string, mixed>>
     */
    public function storeTelegramAttachments(
        TelegramBotService $telegramBotService,
        string $botToken,
        array $telegramMessage,
        Project $project,
        Conversation $conversation,
    ): array {
        $attachments = [];

        if (isset($telegramMessage['document']) && is_array($telegramMessage['document'])) {
            $documentAttachment = $this->storeTelegramFile(
                $telegramBotService,
                $botToken,
                $telegramMessage['document'],
                $project,
                $conversation,
                'file'
            );

            if ($documentAttachment !== null) {
                $attachments[] = $documentAttachment;
            }
        }

        if (isset($telegramMessage['photo']) && is_array($telegramMessage['photo'])) {
            $largestPhoto = Arr::last($telegramMessage['photo']);

            if (is_array($largestPhoto)) {
                $photoAttachment = $this->storeTelegramFile(
                    $telegramBotService,
                    $botToken,
                    $largestPhoto,
                    $project,
                    $conversation,
                    'image'
                );

                if ($photoAttachment !== null) {
                    $attachments[] = $photoAttachment;
                }
            }
        }

        Log::info('Persisted Telegram attachments for admin reply.', [
            'project_id' => $project->id,
            'conversation_id' => $conversation->id,
            'attachment_count' => count($attachments),
            'telegram_message_id' => $telegramMessage['message_id'] ?? null,
        ]);

        return $attachments;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeForApi(array $attachment): array
    {
        return [
            'id' => $attachment['id'] ?? null,
            'name' => $attachment['name'] ?? $attachment['original_name'] ?? 'attachment',
            'original_name' => $attachment['original_name'] ?? $attachment['name'] ?? 'attachment',
            'mime_type' => $attachment['mime_type'] ?? null,
            'size' => $attachment['size'] ?? null,
            'url' => $attachment['url'] ?? null,
            'source' => $attachment['source'] ?? 'widget',
        ];
    }

    protected function storeUploadedFile(UploadedFile $attachment, Project $project, Conversation $conversation): array
    {
        $extension = strtolower($attachment->getClientOriginalExtension() ?: $attachment->extension() ?: 'bin');
        $storedName = sprintf('%s.%s', Str::uuid()->toString(), $extension);
        $directory = $this->buildAttachmentDirectory($project, $conversation);
        $storedPath = $attachment->storeAs($directory, $storedName, 'private');

        Log::info('Stored widget attachment file.', [
            'project_id' => $project->id,
            'conversation_id' => $conversation->id,
            'stored_path' => $storedPath,
            'mime_type' => $attachment->getMimeType(),
            'size' => $attachment->getSize(),
        ]);

        return [
            'id' => Str::uuid()->toString(),
            'name' => $storedName,
            'original_name' => $this->sanitizeFileName($attachment->getClientOriginalName()),
            'mime_type' => $attachment->getMimeType(),
            'size' => $attachment->getSize(),
            'path' => $storedPath,
            'url' => route('widget.attachments.download', [$project->id, $conversation->id, $storedName]),
            'source' => 'widget',
        ];
    }

    /**
     * @param  array<string, mixed>  $telegramFile
     * @return array<string, mixed>|null
     */
    protected function storeTelegramFile(
        TelegramBotService $telegramBotService,
        string $botToken,
        array $telegramFile,
        Project $project,
        Conversation $conversation,
        string $defaultType,
    ): ?array {
        $fileId = $telegramFile['file_id'] ?? null;

        if (! is_string($fileId) || $fileId === '') {
            Log::warning('Skipping Telegram attachment because file_id is missing.', [
                'project_id' => $project->id,
                'conversation_id' => $conversation->id,
            ]);

            return null;
        }

        $remotePath = $telegramBotService->getFilePath($botToken, $fileId);

        if ($remotePath === null) {
            Log::warning('Skipping Telegram attachment because file path could not be resolved.', [
                'project_id' => $project->id,
                'conversation_id' => $conversation->id,
                'file_id' => $fileId,
            ]);

            return null;
        }

        $contents = $telegramBotService->downloadFile($botToken, $remotePath);

        if ($contents === null) {
            Log::warning('Skipping Telegram attachment because file download failed.', [
                'project_id' => $project->id,
                'conversation_id' => $conversation->id,
                'file_id' => $fileId,
                'remote_path' => $remotePath,
            ]);

            return null;
        }

        $originalName = $telegramFile['file_name']
            ?? basename($remotePath)
            ?? sprintf('%s.%s', $defaultType, $defaultType === 'image' ? 'jpg' : 'bin');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION) ?: ($defaultType === 'image' ? 'jpg' : 'bin'));
        $storedName = sprintf('%s.%s', Str::uuid()->toString(), $extension);
        $directory = $this->buildAttachmentDirectory($project, $conversation);
        $storedPath = $directory.'/'.$storedName;

        Storage::disk('private')->put($storedPath, $contents);

        return [
            'id' => Str::uuid()->toString(),
            'name' => $storedName,
            'original_name' => $this->sanitizeFileName($originalName),
            'mime_type' => $telegramFile['mime_type'] ?? ($defaultType === 'image' ? 'image/jpeg' : 'application/octet-stream'),
            'size' => $telegramFile['file_size'] ?? strlen($contents),
            'path' => $storedPath,
            'url' => route('widget.attachments.download', [$project->id, $conversation->id, $storedName]),
            'source' => 'telegram',
        ];
    }

    protected function buildAttachmentDirectory(Project $project, Conversation $conversation): string
    {
        return sprintf(
            'widget-attachments/project-%d/conversation-%d',
            $project->id,
            $conversation->id
        );
    }

    /**
     * Sanitize file name to prevent XSS and path traversal attacks.
     */
    protected function sanitizeFileName(string $name): string
    {
        // Remove any directory traversal attempts
        $name = basename($name);
        // Strip HTML tags
        $name = strip_tags($name);
        // Convert HTML entities to prevent stored XSS
        $name = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        // Limit length
        return mb_substr($name, 0, 255);
    }
}
