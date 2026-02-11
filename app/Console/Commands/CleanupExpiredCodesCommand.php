<?php

namespace App\Console\Commands;

use App\Services\ConnectionService;
use Illuminate\Console\Command;

class CleanupExpiredCodesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'line:cleanup-codes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired LINE connection codes';

    /**
     * Execute the console command.
     */
    public function handle(ConnectionService $connectionService): int
    {
        $deleted = $connectionService->cleanupExpiredCodes();

        if ($deleted > 0) {
            $this->info("Cleaned up {$deleted} expired connection code(s).");
        } else {
            $this->info('No expired connection codes to clean up.');
        }

        return Command::SUCCESS;
    }
}
