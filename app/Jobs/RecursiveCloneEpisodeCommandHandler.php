<?php

namespace App\Jobs;

use App\Events\RecursiveCloneEpisodeCommand;
use App\Events\RecursiveClonePartsCommand;
use App\Events\RevertRecursiveCloneEpisodeCommand;
use App\Services\EpisodeService;
use App\Services\IdempotencyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Log\Logger;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class RecursiveCloneEpisodeCommandHandler implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected RecursiveCloneEpisodeCommand $command)
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(EpisodeService $episodeService, IdempotencyService $idempotencyService, Logger $log): void
    {
        $log->info(self::class . ' started', ['command' => $this->command]);

        if ($idempotencyService->isProcessed($this->command->transactionId)) {
            return;
        }

        try {
            $newEpisodeUuid = $episodeService->cloneEpisode($this->command->episodeUuid);

            $partUuids = DB::table('parts')
                ->where('episode_uuid', $this->command->episodeUuid)
                ->pluck('uuid')
                ->all();

            if ($partUuids !== []) {
                $chunks = array_chunk($partUuids, (int)config('cloning.chunk_size_parts'));
                foreach ($chunks as $chunk) {
                    $command = RecursiveClonePartsCommand::fromCommand($this->command);
                    $command->newEpisodeUuid = $newEpisodeUuid;
                    $command->parts = $chunk;
                    RecursiveClonePartsCommandHandler::dispatch($command);
                }
            }

            $idempotencyService->markAsProcessed($this->command->transactionId, (int)config('cloning.transaction_ttl'));
        } catch (Throwable $e) {
            $log->error("Failed to clone episode {$this->command->episodeUuid}: " . $e->getMessage());
            $this->performRollback($e);

            throw $e;
        }
    }

    public function performRollback(Throwable $e): void
    {
        $this->fail($e);
        $command = RevertRecursiveCloneEpisodeCommand::fromCommand($this->command);
        $command->episodeUuid = $this->command->episodeUuid;
        RevertRecursiveCloneEpisodeCommandHandler::dispatch($command);
    }


    public function failed(Throwable $exception): void
    {
        logger()->error('Job failed', [
            'job' => static::class,
            'transaction_id' => $this->command->transactionId ?? null,
            'trace_id' => $this->command->traceId ?? null,
            'error' => $exception->getMessage(),
        ]);
    }
}
