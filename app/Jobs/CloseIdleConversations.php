<?php

namespace App\Jobs;

use App\Services\ConversationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class CloseIdleConversations implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct() {}

    /**
     * Execute the job.
     */
    public function handle(ConversationService $conversationService): void
    {
        $timeoutMinutes = config('widget.idle_conversation_timeout_minutes', 1440);
        $cutoff = Carbon::now()->subMinutes($timeoutMinutes);

        Log::info('Running CloseIdleConversations job.', [
            'timeout_minutes' => $timeoutMinutes,
            'cutoff' => $cutoff->toISOString(),
        ]);

        $closedCount = $conversationService->closeConversationsOlderThan($cutoff);

        Log::info('CloseIdleConversations job completed.', [
            'closed_count' => $closedCount,
        ]);
    }
}
