<?php

namespace App\Events;

class RecursiveCloneItemsCommand extends BaseCommand
{
    public array $items;

    public string $newPartUuid;
}
