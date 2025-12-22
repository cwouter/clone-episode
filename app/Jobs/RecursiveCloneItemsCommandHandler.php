<?php

namespace App\Jobs;

use App\Events\RecursiveCloneBlocksCommand;
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

class RecursiveCloneItemsCommandHandler implements ShouldQueue
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

        $partMappingJson = Redis::get("clone:parts:{$this->command->traceId}");
        $partMapping = $partMappingJson !== null ? json_decode($partMappingJson, true) : [];

        $itemUuids = $this->command->items;

        $itemMapping = $cloneService->cloneItems($itemUuids, $partMapping);

        if ($itemMapping !== []) {
            Redis::set("clone:items:{$this->command->traceId}", json_encode($itemMapping));
        }

        $blockUuids = DB::table('blocks')
            ->whereIn('item_uuid', $itemUuids)
            ->pluck('uuid')
            ->all();

        if ($blockUuids !== []) {
            $chunks = $chunkingService->chunkArray($blockUuids, (int) config('cloning.chunk_size'));
            $commands = $chunkingService->createChunkedCommands($chunks, $this->command->traceId, RecursiveCloneBlocksCommand::class);

            foreach ($commands as $command) {
                RecursiveCloneBlocksCommandHandler::dispatch($command);
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
