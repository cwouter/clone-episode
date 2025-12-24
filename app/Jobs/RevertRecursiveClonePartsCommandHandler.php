<?php

namespace App\Jobs;

use App\Events\RevertRecursiveCloneEpisodeCommand;
use App\Events\RevertRecursiveClonePartsCommand;
use App\Services\PartsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Log\Logger;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RevertRecursiveClonePartsCommandHandler implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected RevertRecursiveClonePartsCommand $command)
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(PartsService $partsService, Logger $log): void
    {
        $log->info(self::class . ' started', ['command' => $this->command]);

        try {
            $partsService->deleteParts($this->command->episodeUuid);

            $this->revertParent();
        } catch (Throwable $e) {
            $log->error("Failed to revert parts: " . $e->getMessage());
            throw $e;
        }
    }

    public function revertParent(): void
    {
        $command = RevertRecursiveCloneEpisodeCommand::fromCommand($this->command);
        $command->episodeUuid = $this->command->episodeUuid;
        RevertRecursiveCloneEpisodeCommandHandler::dispatch($command);
    }

    public function failed(Throwable $exception): void
    {
        logger()->error('Revert Parts Job failed', [
            'job' => static::class,
            'transaction_id' => $this->command->transactionId ?? null,
            'trace_id' => $this->command->traceId ?? null,
            'error' => $exception->getMessage(),
        ]);

        $this->revertParent();
    }
}
