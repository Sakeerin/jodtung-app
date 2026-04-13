<?php

namespace App\Jobs;

use App\Services\Line\LineEventHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessLineEvent implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /**
     * Max retry count before giving up.
     */
    public int $tries = 3;

    /**
     * Timeout in seconds per attempt.
     */
    public int $timeout = 60;

    /**
     * Create a new job instance.
     *
     * @param object $event The deserialized LINE webhook event object
     */
    public function __construct(
        public readonly object $event,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(LineEventHandler $handler): void
    {
        $handler->handleEvent($this->event);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessLineEvent job failed permanently', [
            'event_type' => get_class($this->event),
            'error'      => $exception->getMessage(),
        ]);
    }
}
