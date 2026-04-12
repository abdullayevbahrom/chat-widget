<?php

namespace Tests\Unit;

use App\Jobs\SendTelegramNotificationJob;
use PHPUnit\Framework\TestCase;

class SendTelegramNotificationJobTest extends TestCase
{
    public function test_job_has_correct_retry_configuration(): void
    {
        $job = new SendTelegramNotificationJob(1, 1, 1, 1, []);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals([10, 30, 60], $job->backoff);
    }

    public function test_job_stores_constructor_parameters(): void
    {
        $job = new SendTelegramNotificationJob(
            42,  // tenantId
            100, // projectId
            200, // messageId
            300, // conversationId
            ['visitor_name' => 'Alice', 'visitor_email' => 'alice@test.com']
        );

        $this->assertEquals(42, $job->tenantId);
        $this->assertEquals(100, $job->projectId);
        $this->assertEquals(200, $job->messageId);
        $this->assertEquals(300, $job->conversationId);
        $this->assertEquals([
            'visitor_name' => 'Alice',
            'visitor_email' => 'alice@test.com',
        ], $job->visitorData);
    }

    public function test_job_implements_should_queue(): void
    {
        $job = new SendTelegramNotificationJob(1, 1, 1, 1, []);

        $this->assertInstanceOf(
            \Illuminate\Contracts\Queue\ShouldQueue::class,
            $job
        );
    }
}
