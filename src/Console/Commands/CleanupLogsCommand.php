<?php

declare(strict_types=1);

namespace HmacAuth\Console\Commands;

use HmacAuth\Repositories\ApiRequestLogRepository;
use Illuminate\Console\Command;

/**
 * Command to clean up old API request logs.
 */
class CleanupLogsCommand extends Command
{
    protected $signature = 'hmac:cleanup
                            {--days=30 : Delete logs older than this many days}
                            {--dry-run : Show what would be deleted without actually deleting}';

    protected $description = 'Clean up old API request logs';

    public function __construct(
        private readonly ApiRequestLogRepository $logRepository,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        if ($days < 1) {
            $this->error('Days must be at least 1');

            return self::FAILURE;
        }

        $cutoffDate = now()->subDays($days);

        $this->info("Cleaning up logs older than {$cutoffDate->toDateString()}...");

        if ($dryRun) {
            $count = $this->logRepository->countOlderThan($days);
            $this->info("[DRY RUN] Would delete {$count} log entries");

            return self::SUCCESS;
        }

        $deleted = $this->logRepository->deleteOlderThan($days);

        $this->info("Deleted {$deleted} log entries");

        return self::SUCCESS;
    }
}
