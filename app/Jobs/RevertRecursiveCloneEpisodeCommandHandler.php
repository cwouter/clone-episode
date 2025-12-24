<?php

namespace App\Jobs;

use App\Events\RevertRecursiveCloneEpisodeCommand;
use App\Services\EpisodeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Log\Logger;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RevertRecursiveCloneEpisodeCommandHandler implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected RevertRecursiveCloneEpisodeCommand $command)
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(EpisodeService $episodeService, Logger $log): void
    {
        $log->info(self::class . ' started', ['command' => $this->command]);

        try {
            $episodeService->deleteEpisode($this->command->episodeUuid);
        } catch (Throwable $e) {
            $log->error("Failed to revert episode {$this->command->episodeUuid}: " . $e->getMessage());
            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        logger()->error('Revert Episode Job failed', [
            'job' => static::class,
            'transaction_id' => $this->command->transactionId ?? null,
            'trace_id' => $this->command->traceId ?? null,
            'error' => $exception->getMessage(),
        ]);
    }
}
