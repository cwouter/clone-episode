<?php

namespace App\Jobs;

use App\Events\RevertRecursiveCloneItemsCommand;
use App\Events\RevertRecursiveClonePartsCommand;
use App\Services\ItemsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Log\Logger;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class RevertRecursiveCloneItemsCommandHandler implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected RevertRecursiveCloneItemsCommand $command)
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(ItemsService $itemsService, Logger $log): void
    {
        $log->info(self::class . ' started', ['command' => $this->command]);

        try {
            $itemsService->deleteItems($this->command->partUuid);

            $this->revertParent();
        } catch (Throwable $e) {
            $log->error("Failed to revert items: " . $e->getMessage());
            throw $e;
        }
    }

    public function revertParent(): void
    {
        $row = DB::selectOne("
            SELECT p.episode_uuid
            FROM parts as p WHERE uuid = ?
        ", [$this->command->partUuid]);

        $command = RevertRecursiveClonePartsCommand::fromCommand($this->command);
        $command->episodeUuid = $row->episode_uuid;
        RevertRecursiveClonePartsCommandHandler::dispatch($command);
    }

    public function failed(Throwable $exception): void
    {
        logger()->error('Revert Items Job failed', [
            'job' => static::class,
            'transaction_id' => $this->command->transactionId ?? null,
            'trace_id' => $this->command->traceId ?? null,
            'error' => $exception->getMessage(),
        ]);

        $this->revertParent();
    }
}
