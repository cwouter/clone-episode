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
        $s3Client = new S3Client([
            'version' => 'latest',
            'region' => config('filesystems.disks.s3.region'),
            'endpoint' => config('filesystems.disks.s3.endpoint'),
            'use_path_style_endpoint' => config('filesystems.disks.s3.use_path_style_endpoint'),
            'credentials' => [
                'key' => config('filesystems.disks.s3.key'),
                'secret' => config('filesystems.disks.s3.secret'),
            ],
        ]);

        $bucket = config('filesystems.disks.s3.bucket');

        $episode = DB::selectOne('INSERT INTO episodes (title, description) VALUES (?, ?) RETURNING uuid', [
            'Sample Episode for Testing',
            'This is a test episode with nested structure',
        ]);

        $episodeUuid = $episode->uuid;

        for ($p = 1; $p <= 5; $p++) {
            $part = DB::selectOne('INSERT INTO parts (episode_uuid, name, description) VALUES (?, ?, ?) RETURNING uuid', [
                $episodeUuid,
                'Part '.$p,
                'Description for part '.$p,
            ]);

            $partUuid = $part->uuid;

            for ($i = 1; $i <= 10; $i++) {
                $item = DB::selectOne('INSERT INTO items (part_uuid, name, details) VALUES (?, ?, ?) RETURNING uuid', [
                    $partUuid,
                    'Item '.$i.' of Part '.$p,
                    'Details for item '.$i,
                ]);

                $itemUuid = $item->uuid;

                for ($b = 1; $b <= 3; $b++) {
                    $blockType = $b === 1 ? 'text' : ($b === 2 ? 'image' : 'video');

                    $block = DB::selectOne('INSERT INTO blocks (item_uuid, type, description) VALUES (?, ?, ?) RETURNING uuid', [
                        $itemUuid,
                        $blockType,
                        'Block '.$b.' description',
                    ]);

                    $blockUuid = $block->uuid;

                    for ($bf = 1; $bf <= 2; $bf++) {
                        DB::insert('INSERT INTO block_fields (block_uuid, field_name, field_value, field_type) VALUES (?, ?, ?, ?)', [
                            $blockUuid,
                            'field_'.$bf,
                            'Value '.$bf.' for block',
                            'string',
                        ]);
                    }

                    if (in_array($b, [2, 3], true)) {
                        $extension = $b === 2 ? 'jpg' : 'mp4';
                        $s3Key = 'media/'.Str::uuid().'.'.$extension;

                        $dummyContent = 'Dummy '.$blockType.' content for testing';

                        $s3Client->putObject([
                            'Bucket' => $bucket,
                            'Key' => $s3Key,
                            'Body' => $dummyContent,
                            'ContentType' => $b === 2 ? 'image/jpeg' : 'video/mp4',
                        ]);

                        $url = $s3Client->getObjectUrl($bucket, $s3Key);

                        DB::insert(
                            'INSERT INTO media (block_uuid, media_type, s3_key, s3_bucket, url, metadata) VALUES (?, ?, ?, ?, ?, ?::jsonb)',
                            [
                                $blockUuid,
                                $b === 2 ? 'image/jpeg' : 'video/mp4',
                                $s3Key,
                                $bucket,
                                $url,
                                json_encode(['size' => strlen($dummyContent), 'width' => 800, 'height' => 600]),
                            ]
                        );
                    }
                }
            }
        }

        $this->command?->info('Seeded 1 episode with UUID: '.$episodeUuid);
    }
}
