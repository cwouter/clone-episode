<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class IdempotencyService
{
    public function isProcessed(string $transactionId): bool
    {
        return (bool) Redis::exists("transaction:{$transactionId}");
    }

    public function markAsProcessed(string $transactionId, int $ttl = 3600): void
    {
        Redis::setex("transaction:{$transactionId}", $ttl, 'processed');
    }
}
