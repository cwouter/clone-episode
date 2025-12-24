<?php

namespace App\Jobs;

use App\Events\RecursiveCloneBlocksCommand;
use App\Events\RevertRecursiveCloneBlocksCommand;
use App\Services\BlockService;
use App\Services\IdempotencyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Log\Logger;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RecursiveCloneBlocksCommandHandler implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected RecursiveCloneBlocksCommand $command)
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(BlockService $blockService, IdempotencyService $idempotencyService, Logger $log): void
    {
        $log->info(self::class . ' started', ['command' => $this->command]);

        if ($idempotencyService->isProcessed($this->command->transactionId)) {
            return;
        }

        try {
            $blockMapping = $blockService->cloneBlocks(
                $this->command->blocks,
                $this->command->newItemUuid
            );

            if ($blockMapping !== []) {
                $blockService->cloneBlockFields($blockMapping);
                $blockService->cloneMedia($blockMapping, $this->command->newItemUuid);
            }

            $idempotencyService->markAsProcessed($this->command->transactionId, (int)config('cloning.transaction_ttl'));
        } catch (Throwable $e) {
            $log->error("Failed to clone blocks: " . $e->getMessage());
            $this->performRollback($e);

            throw $e;
        }
    }

    public function performRollback(Throwable $e): void
    {
        $this->fail($e);
        $command = RevertRecursiveCloneBlocksCommand::fromCommand($this->command);
        $command->itemUuid = $this->command->newItemUuid;
        RevertRecursiveCloneBlocksCommandHandler::dispatch($command);
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
