<?php

namespace App\Jobs;

use App\Services\CloneService;
use App\Services\IdempotencyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;
use Throwable;

class RecursiveCloneBlocksCommandHandler implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public object $command;

    public function __construct(object $command)
    {
        $this->command = $command;
    }

    public function handle(CloneService $cloneService, IdempotencyService $idempotencyService): void
    {
        if ($idempotencyService->isProcessed($this->command->transactionId)) {
            return;
        }

        $itemMappingJson = Redis::get("clone:items:{$this->command->traceId}");
        $itemMapping = $itemMappingJson !== null ? json_decode($itemMappingJson, true) : [];

        $blockUuids = $this->command->blocks;

        $blockMapping = $cloneService->cloneBlocks($blockUuids, $itemMapping);

        if ($blockMapping !== []) {
            $cloneService->cloneBlockFields($blockMapping);
            $cloneService->cloneMedia($blockMapping);
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
