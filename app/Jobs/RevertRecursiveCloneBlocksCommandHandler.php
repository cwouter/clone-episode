<?php

namespace App\Jobs;

use App\Events\RevertRecursiveCloneBlocksCommand;
use App\Events\RevertRecursiveCloneItemsCommand;
use App\Services\BlockService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Log\Logger;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class RevertRecursiveCloneBlocksCommandHandler implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected RevertRecursiveCloneBlocksCommand $command)
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(BlockService $blockService, Logger $log): void
    {
        $log->info(self::class . ' started', ['command' => $this->command]);

        try {
            $blockService->deleteMediaByItem($this->command->itemUuid);
            $blockService->deleteBlockFieldsByItem($this->command->itemUuid);
            $blockService->deleteBlocks($this->command->itemUuid);

            $this->revertParent();
        } catch (Throwable $e) {
            $log->error("Failed to revert blocks: " . $e->getMessage());

            throw $e;
        }
    }

    public function revertParent(): void
    {
        $row = DB::selectOne("
            SELECT i.part_uuid
            FROM items as i WHERE uuid = ?
        ", [$this->command->itemUuid]);

        $command = RevertRecursiveCloneItemsCommand::fromCommand($this->command);
        $command->partUuid = $row->part_uuid;
        RevertRecursiveCloneItemsCommandHandler::dispatch($command);
    }

    public function failed(Throwable $exception): void
    {
        logger()->error('Revert Blocks Job failed', [
            'job' => static::class,
            'transaction_id' => $this->command->transactionId ?? null,
            'trace_id' => $this->command->traceId ?? null,
            'error' => $exception->getMessage(),
        ]);

        $this->revertParent();
    }
}
