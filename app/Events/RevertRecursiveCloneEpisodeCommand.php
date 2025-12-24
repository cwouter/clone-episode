<?php

namespace App\Events;

class RevertRecursiveCloneEpisodeCommand extends BaseCommand
{
    public string $episodeUuid;
}
