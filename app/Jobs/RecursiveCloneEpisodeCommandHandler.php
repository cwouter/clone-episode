<?php

namespace App\Jobs;

use App\Events\RecursiveClonePartsCommand;
use App\Services\ChunkingService;
use App\Services\CloneService;
use App\Services\IdempotencyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class RecursiveCloneEpisodeCommandHandler implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public object $command;

    public function __construct(object $command)
    {
        $this->command = $command;
    }

    public function handle(CloneService $cloneService, ChunkingService $chunkingService, IdempotencyService $idempotencyService): void
    {
        if ($idempotencyService->isProcessed($this->command->transactionId)) {
            return;
        }

        $episodeMapping = $cloneService->cloneEpisode($this->command->episodeUuid);
        $newEpisodeUuid = $episodeMapping['new_uuid'];

        Redis::set("clone:episode:{$this->command->traceId}", $newEpisodeUuid);

        $partUuids = DB::table('parts')
            ->where('episode_uuid', $this->command->episodeUuid)
            ->pluck('uuid')
            ->all();

        if ($partUuids !== []) {
            $chunks = $chunkingService->chunkArray($partUuids, (int) config('cloning.chunk_size'));
            $commands = $chunkingService->createChunkedCommands($chunks, $this->command->traceId, RecursiveClonePartsCommand::class);

            foreach ($commands as $command) {
                RecursiveClonePartsCommandHandler::dispatch($command);
            }
        }

        $idempotencyService->markAsProcessed($this->command->transactionId, (int) config('cloning.transaction_ttl'));
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
