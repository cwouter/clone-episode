<?php

namespace Database\Seeders;

use Aws\S3\S3Client;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EpisodeSeeder extends Seeder
{
    protected string $bucket;

    public function __construct(protected S3Client $s3Client)
    {
        $this->bucket = config('filesystems.disks.s3.bucket');
    }

    public function run(): void
    {
        $episode = $this->createEpisode();
        $episodeUuid = $episode->uuid;

        for ($p = 1; $p <= 2; $p++) {
            $part = $this->createPart($episodeUuid, $p);
            $partUuid = $part->uuid;

            for ($i = 1; $i <= 2; $i++) {
                $item = $this->createItem($partUuid, $i, $p);
                $itemUuid = $item->uuid;

                for ($b = 1; $b <= 3; $b++) {
                    $blockType = $b === 1 ? 'text' : ($b === 2 ? 'image' : 'video');

                    $block = $this->createBlock($itemUuid, $blockType, $b);
                    $blockUuid = $block->uuid;

                    for ($bf = 1; $bf <= 2; $bf++) {
                        $this->createBlockField($blockUuid, $bf);
                    }

                    if (in_array($b, [2, 3], true)) {
                        $this->createMedia($itemUuid, $blockUuid, $blockType, $b);
                    }
                }
            }
        }

        $this->command?->info('Seeded 1 episode with UUID: ' . $episodeUuid);
    }

    protected function createEpisode(): mixed
    {
        return DB::selectOne('INSERT INTO episodes (title, description) VALUES (?, ?) RETURNING uuid', [
            'Sample Episode for Testing',
            'This is a test episode with nested structure',
        ]);
    }

    protected function createPart(string $episodeUuid, int $p): mixed
    {
        return DB::selectOne('INSERT INTO parts (episode_uuid, name, description) VALUES (?, ?, ?) RETURNING uuid', [
            $episodeUuid,
            'Part ' . $p,
            'Description for part ' . $p,
        ]);
    }

    protected function createItem(string $partUuid, int $i, int $p): mixed
    {
        return DB::selectOne('INSERT INTO items (part_uuid, name, details) VALUES (?, ?, ?) RETURNING uuid', [
            $partUuid,
            'Item ' . $i . ' of Part ' . $p,
            'Details for item ' . $i,
        ]);
    }

    protected function createBlock(string $itemUuid, string $blockType, int $i): mixed
    {
        return DB::selectOne('INSERT INTO blocks (item_uuid, type, description) VALUES (?, ?, ?) RETURNING uuid', [
            $itemUuid,
            $blockType,
            'Block ' . $i . ' description',
        ]);
    }

    protected function createBlockField(string $blockUuid, int $blockField): void
    {
        DB::insert('INSERT INTO block_fields (block_uuid, field_name, field_value, field_type) VALUES (?, ?, ?, ?)', [
            $blockUuid,
            'field_' . $blockField,
            'Value ' . $blockField . ' for block',
            'string',
        ]);
    }

    protected function createMedia(string $itemUuid, string $blockUuid, string $blockType, int $i): void
    {
        $extension = $i === 2 ? 'jpg' : 'mp4';
        $contentType = $i === 2 ? 'image/jpeg' : 'video/mp4';

        $s3Key = 'media/' . $itemUuid . '/' . Str::uuid() . '.' . $extension;
        $dummyContent = 'Dummy ' . $blockType . ' content for testing';

        $this->s3Client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $s3Key,
            'Body' => $dummyContent,
            'ContentType' => $contentType,
        ]);

        $url = $this->s3Client->getObjectUrl($this->bucket, $s3Key);

        DB::insert(
            'INSERT INTO media (block_uuid, media_type, s3_key, s3_bucket, url, metadata) VALUES (?, ?, ?, ?, ?, ?::jsonb)',
            [
                $blockUuid,
                $contentType,
                $s3Key,
                $this->bucket,
                $url,
                json_encode(['size' => strlen($dummyContent), 'width' => 800, 'height' => 600]),
            ]
        );
    }
}
