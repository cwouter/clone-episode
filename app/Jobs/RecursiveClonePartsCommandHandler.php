<?php

namespace App\Jobs;

use App\Events\RecursiveCloneItemsCommand;
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

class RecursiveClonePartsCommandHandler implements ShouldQueue
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

        $newEpisodeUuid = (string) Redis::get("clone:episode:{$this->command->traceId}");

        if ($newEpisodeUuid === '') {
            logger()->error('Missing episode mapping for trace', [
                'trace_id' => $this->command->traceId,
            ]);

            return;
        }

        $partUuids = $this->command->parts;

        $partMapping = $cloneService->cloneParts($partUuids, $newEpisodeUuid);

        if ($partMapping !== []) {
            Redis::set("clone:parts:{$this->command->traceId}", json_encode($partMapping));
        }

        $itemUuids = DB::table('items')
            ->whereIn('part_uuid', $partUuids)
            ->pluck('uuid')
            ->all();

        if ($itemUuids !== []) {
            $chunks = $chunkingService->chunkArray($itemUuids, (int) config('cloning.chunk_size'));
            $commands = $chunkingService->createChunkedCommands($chunks, $this->command->traceId, RecursiveCloneItemsCommand::class);

            foreach ($commands as $command) {
                RecursiveCloneItemsCommandHandler::dispatch($command);
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
