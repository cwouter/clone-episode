<?php

namespace App\Services;

use Aws\S3\S3Client;
use Illuminate\Support\Facades\DB;

class EpisodeService
{
    protected string $bucket;

    public function __construct(protected S3Client $s3Client)
    {
        $this->bucket = config('filesystems.disks.s3.bucket');
    }

    /**
     * @throws \Throwable
     */
    public function cloneEpisode(string $episodeUuid): string
    {
        return DB::transaction(function () use ($episodeUuid) {
            $result = DB::selectOne(
                'INSERT INTO episodes (title, description)
             SELECT title, description
             FROM episodes
             WHERE uuid = ?
             RETURNING uuid as new_uuid',
                [$episodeUuid]
            );

            if (!$result) {
                throw new \RuntimeException("Could not create episode");
            }

            return $result->new_uuid;
        });
    }

    /**
     * @throws \Throwable
     */
    public function deleteEpisode(string $episodeUuid): int
    {
        return DB::transaction(function () use ($episodeUuid) {
            return DB::affectingStatement("
                DELETE FROM episodes
                WHERE uuid = ?
            ", [$episodeUuid]);
        });
    }
}
