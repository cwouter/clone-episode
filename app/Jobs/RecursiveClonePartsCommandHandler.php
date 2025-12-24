<?php

namespace App\Jobs;

use App\Events\RecursiveCloneItemsCommand;
use App\Events\RecursiveClonePartsCommand;
use App\Events\RevertRecursiveClonePartsCommand;
use App\Services\IdempotencyService;
use App\Services\PartsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Log\Logger;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class RecursiveClonePartsCommandHandler implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected RecursiveClonePartsCommand $command)
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(PartsService $partsService, IdempotencyService $idempotencyService, Logger $log): void
    {
        $log->info(self::class . ' started', ['command' => $this->command]);

        if ($idempotencyService->isProcessed($this->command->transactionId)) {
            return;
        }

        try {
            $newPartsMapping = $partsService->cloneParts(
                $this->command->parts,
                $this->command->newEpisodeUuid
            );

            $oldUuids = $this->getArrayString(array_keys($newPartsMapping));
            $newUuids = $this->getArrayString(array_values($newPartsMapping));

            $chunkSize = (int)config('cloning.chunk_size_items');

            // Fetch results in chunks to avoid hitting a memory limit.
            DB::table('items')
                ->select('i.uuid AS item_uuid')
                ->selectRaw('map.new_part_uuid')
                ->fromRaw("
                items i
                JOIN UNNEST(?::uuid[], ?::uuid[]) AS map(old_part_uuid, new_part_uuid)
                  ON i.part_uuid = map.old_part_uuid
            ", [$oldUuids, $newUuids])
                ->orderBy('map.new_part_uuid')
                ->chunk($chunkSize, fn(Collection $items) => $this->dispatchChunk($items));

            $idempotencyService->markAsProcessed($this->command->transactionId, (int)config('cloning.transaction_ttl'));
        } catch (Throwable $e) {
            $log->error("Failed to clone parts: " . $e->getMessage());
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
        $itemsByPart = $items->groupBy('new_part_uuid');
        foreach ($itemsByPart as $newPartUuid => $partItems) {
            $command = RecursiveCloneItemsCommand::fromCommand($this->command);
            $command->items = $partItems->pluck('item_uuid')->all();
            $command->newPartUuid = $newPartUuid;
            RecursiveCloneItemsCommandHandler::dispatch($command);
        }
    }

    public function performRollback(Throwable $e): void
    {
        $this->fail($e);
        $command = RevertRecursiveClonePartsCommand::fromCommand($this->command);
        $command->episodeUuid = $this->command->newEpisodeUuid;
        RevertRecursiveClonePartsCommandHandler::dispatch($command);
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
