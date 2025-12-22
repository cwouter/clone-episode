<?php

namespace App\Services;

use Aws\S3\S3Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CloneService
{
    protected string $bucket;

    public function __construct(protected S3Client $s3Client)
    {
        $this->bucket = config('filesystems.disks.s3.bucket');
    }

    public function cloneEpisode(string $episodeUuid): array
    {
        $result = DB::selectOne(
            'INSERT INTO episodes (title, description)
             SELECT title, description
             FROM episodes
             WHERE uuid = ?
             RETURNING uuid as new_uuid, ? as old_uuid',
            [$episodeUuid, $episodeUuid]
        );

        return [
            'old_uuid' => $result->old_uuid,
            'new_uuid' => $result->new_uuid,
        ];
    }

    public function cloneParts(array $partUuids, string $newEpisodeUuid): array
    {
        if ($partUuids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($partUuids), '?'));

        $results = DB::select(
            "WITH source_parts AS (
                SELECT uuid, name, description
                FROM parts
                WHERE uuid IN ({$placeholders})
            ),
            inserted_parts AS (
                INSERT INTO parts (episode_uuid, name, description)
                SELECT ?, name, description
                FROM source_parts
                RETURNING uuid as new_uuid
            )
            SELECT
                sp.uuid as old_uuid,
                ip.new_uuid
            FROM source_parts sp
            CROSS JOIN LATERAL (
                SELECT new_uuid FROM inserted_parts LIMIT 1 OFFSET (
                    SELECT COUNT(*) FROM source_parts sp2 WHERE sp2.uuid < sp.uuid
                )
            ) ip",
            array_merge($partUuids, [$newEpisodeUuid])
        );

        $mapping = [];

        foreach ($results as $result) {
            $mapping[$result->old_uuid] = $result->new_uuid;
        }

        return $mapping;
    }

    public function cloneItems(array $itemUuids, array $partUuidMapping): array
    {
        if ($itemUuids === []) {
            return [];
        }

        DB::statement('CREATE TEMP TABLE temp_part_mapping (old_uuid UUID, new_uuid UUID)');

        foreach ($partUuidMapping as $oldUuid => $newUuid) {
            DB::insert('INSERT INTO temp_part_mapping VALUES (?, ?)', [$oldUuid, $newUuid]);
        }

        $placeholders = implode(',', array_fill(0, count($itemUuids), '?'));

        $results = DB::select(
            "WITH source_items AS (
                SELECT i.uuid, i.part_uuid, i.name, i.details
                FROM items i
                WHERE i.uuid IN ({$placeholders})
            ),
            inserted_items AS (
                INSERT INTO items (part_uuid, name, details)
                SELECT tpm.new_uuid, si.name, si.details
                FROM source_items si
                JOIN temp_part_mapping tpm ON si.part_uuid = tpm.old_uuid
                RETURNING uuid as new_uuid
            )
            SELECT
                si.uuid as old_uuid,
                ii.new_uuid
            FROM source_items si
            CROSS JOIN LATERAL (
                SELECT new_uuid FROM inserted_items LIMIT 1 OFFSET (
                    SELECT COUNT(*) FROM source_items si2 WHERE si2.uuid < si.uuid
                )
            ) ii",
            $itemUuids
        );

        DB::statement('DROP TABLE temp_part_mapping');

        $mapping = [];

        foreach ($results as $result) {
            $mapping[$result->old_uuid] = $result->new_uuid;
        }

        return $mapping;
    }

    public function cloneBlocks(array $blockUuids, array $itemUuidMapping): array
    {
        if ($blockUuids === []) {
            return [];
        }

        DB::statement('CREATE TEMP TABLE temp_item_mapping (old_uuid UUID, new_uuid UUID)');

        foreach ($itemUuidMapping as $oldUuid => $newUuid) {
            DB::insert('INSERT INTO temp_item_mapping VALUES (?, ?)', [$oldUuid, $newUuid]);
        }

        $placeholders = implode(',', array_fill(0, count($blockUuids), '?'));

        $results = DB::select(
            "WITH source_blocks AS (
                SELECT b.uuid, b.item_uuid, b.type, b.description
                FROM blocks b
                WHERE b.uuid IN ({$placeholders})
            ),
            inserted_blocks AS (
                INSERT INTO blocks (item_uuid, type, description)
                SELECT tim.new_uuid, sb.type, sb.description
                FROM source_blocks sb
                JOIN temp_item_mapping tim ON sb.item_uuid = tim.old_uuid
                RETURNING uuid as new_uuid
            )
            SELECT
                sb.uuid as old_uuid,
                ib.new_uuid
            FROM source_blocks sb
            CROSS JOIN LATERAL (
                SELECT new_uuid FROM inserted_blocks LIMIT 1 OFFSET (
                    SELECT COUNT(*) FROM source_blocks sb2 WHERE sb2.uuid < sb.uuid
                )
            ) ib",
            $blockUuids
        );

        DB::statement('DROP TABLE temp_item_mapping');

        $mapping = [];

        foreach ($results as $result) {
            $mapping[$result->old_uuid] = $result->new_uuid;
        }

        return $mapping;
    }

    public function cloneBlockFields(array $blockUuidMapping): void
    {
        if ($blockUuidMapping === []) {
            return;
        }

        DB::statement('CREATE TEMP TABLE temp_block_mapping (old_uuid UUID, new_uuid UUID)');

        foreach ($blockUuidMapping as $oldUuid => $newUuid) {
            DB::insert('INSERT INTO temp_block_mapping VALUES (?, ?)', [$oldUuid, $newUuid]);
        }

        DB::statement(
            'INSERT INTO block_fields (block_uuid, field_name, field_value, field_type)
             SELECT tbm.new_uuid, bf.field_name, bf.field_value, bf.field_type
             FROM block_fields bf
             JOIN temp_block_mapping tbm ON bf.block_uuid = tbm.old_uuid'
        );

        DB::statement('DROP TABLE temp_block_mapping');
    }

    public function cloneMedia(array $blockUuidMapping): void
    {
        if ($blockUuidMapping === []) {
            return;
        }

        DB::statement('CREATE TEMP TABLE temp_block_mapping (old_uuid UUID, new_uuid UUID)');

        foreach ($blockUuidMapping as $oldUuid => $newUuid) {
            DB::insert('INSERT INTO temp_block_mapping VALUES (?, ?) ', [$oldUuid, $newUuid]);
        }

        $mediaRecords = DB::select(
            'SELECT m.uuid, m.block_uuid, m.media_type, m.s3_key, m.s3_bucket, m.metadata, tbm.new_uuid as new_block_uuid
             FROM media m
             JOIN temp_block_mapping tbm ON m.block_uuid = tbm.old_uuid'
        );

        foreach ($mediaRecords as $media) {
            $pathInfo = pathinfo($media->s3_key);
            $extension = $pathInfo['extension'] ?? '';
            $dirname = $pathInfo['dirname'] !== '.' ? $pathInfo['dirname'] : 'media';
            $newS3Key = $dirname.'/'.Str::uuid().($extension !== '' ? '.'.$extension : '');

            try {
                $this->s3Client->copyObject([
                    'Bucket' => $this->bucket,
                    'Key' => $newS3Key,
                    'CopySource' => $media->s3_bucket.'/'.$media->s3_key,
                ]);

                $newUrl = $this->s3Client->getObjectUrl($this->bucket, $newS3Key);

                DB::insert(
                    'INSERT INTO media (block_uuid, media_type, s3_key, s3_bucket, url, metadata)
                     VALUES (?, ?, ?, ?, ?, ?::jsonb)',
                    [
                        $media->new_block_uuid,
                        $media->media_type,
                        $newS3Key,
                        $this->bucket,
                        $newUrl,
                        $media->metadata,
                    ]
                );
            } catch (\Aws\Exception\AwsException $e) {
                logger()->error('S3 copy failed', [
                    'old_key' => $media->s3_key,
                    'new_key' => $newS3Key,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        }

        DB::statement('DROP TABLE temp_block_mapping');
    }
}
