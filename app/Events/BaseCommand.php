<?php

namespace App\Events;

use Illuminate\Support\Str;

class BaseCommand
{
    public string $transactionId;

    public function __construct(public string $traceId)
    {
        $this->transactionId = (string) Str::uuid();
    }

    public static function fromCommand(BaseCommand $command): static
    {
        return new static($command->traceId);
    }
}
