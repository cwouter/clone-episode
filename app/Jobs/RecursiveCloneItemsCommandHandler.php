<?php

namespace App\Jobs;

use App\Events\RecursiveCloneBlocksCommand;
use App\Events\RecursiveCloneItemsCommand;
use App\Events\RevertRecursiveCloneItemsCommand;
use App\Services\IdempotencyService;
use App\Services\ItemsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Log\Logger;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class RecursiveCloneItemsCommandHandler implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected RecursiveCloneItemsCommand $command)
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(ItemsService $itemsService, IdempotencyService $idempotencyService, Logger $log): void
    {
        $log->info(self::class . ' started', ['command' => $this->command]);

        if ($idempotencyService->isProcessed($this->command->transactionId)) {
            return;
        }

        try {
            $newItemsMapping = $itemsService->cloneItems(
                $this->command->items,
                $this->command->newPartUuid
            );

            $oldUuids = $this->getArrayString(array_keys($newItemsMapping));
            $newUuids = $this->getArrayString(array_values($newItemsMapping));

            $chunkSize = (int)config('cloning.chunk_size_blocks');

            // Fetch results in chunks to avoid hitting a memory limit.
            DB::table('blocks')
                ->select('b.uuid AS block_uuid')
                ->selectRaw('map.new_item_uuid')
                ->fromRaw("
                blocks b
                JOIN UNNEST(?::uuid[], ?::uuid[]) AS map(old_item_uuid, new_item_uuid)
                  ON b.item_uuid = map.old_item_uuid
            ", [$oldUuids, $newUuids])
                ->orderBy('map.new_item_uuid')
                ->chunk($chunkSize, fn(Collection $items) => $this->dispatchChunk($items));

            $idempotencyService->markAsProcessed($this->command->transactionId, (int)config('cloning.transaction_ttl'));
        } catch (Throwable $e) {
            $log->error("Failed to clone items: " . $e->getMessage());
            $this->performRollback($e);

            throw $e;
        }
    }

    private function getArrayString(array $uuids): string
    {
        return '{' . implode(',', $uuids) . '}';
    }

    private function dispatchChunk(Collection $items): void
    {
        // Events should be grouped per part UUID.
        // This will reduce the size of the payload sent to the event queue.
        // And makes it easier to query on the consuming side.
        $blocksByItem = $items->groupBy('new_item_uuid');
        foreach ($blocksByItem as $newItemUuid => $items) {
            $command = RecursiveCloneBlocksCommand::fromCommand($this->command);
            $command->blocks = $items->pluck('block_uuid')->all();
            $command->newItemUuid = $newItemUuid;
            RecursiveCloneBlocksCommandHandler::dispatch($command);
        }
    }

    public function performRollback(Throwable $e): void
    {
        $this->fail($e);
        $command = RevertRecursiveCloneItemsCommand::fromCommand($this->command);
        $command->partUuid = $this->command->newPartUuid;
        RevertRecursiveCloneItemsCommandHandler::dispatch($command);
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
