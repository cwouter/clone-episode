<?php

namespace App\Services;

use App\Events\RecursiveCloneBlocksCommand;
use App\Events\RecursiveCloneItemsCommand;
use App\Events\RecursiveClonePartsCommand;
use Illuminate\Support\Str;

class ChunkingService
{
    public function chunkArray(array $items, int $chunkSize): array
    {
        return array_chunk($items, $chunkSize);
    }

    public function createChunkedCommands(array $chunks, string $traceId, string $commandClass): array
    {
        $commands = [];

        foreach ($chunks as $chunk) {
            $command = new $commandClass();
            $command->traceId = $traceId;
            $command->transactionId = (string) Str::uuid();

            if ($command instanceof RecursiveClonePartsCommand) {
                $command->parts = $chunk;
            } elseif ($command instanceof RecursiveCloneItemsCommand) {
                $command->items = $chunk;
            } elseif ($command instanceof RecursiveCloneBlocksCommand) {
                $command->blocks = $chunk;
            }

            $commands[] = $command;
        }

        return $commands;
    }
}
