<?php

namespace App\Services;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BlockService
{
    public string $folder = 'media';

    protected string $bucket;

    public function __construct(protected S3Client $s3Client)
    {
        $this->bucket = config('filesystems.disks.s3.bucket');
    }

    /**
     * @throws Throwable
     */
    public function cloneBlocks(array $blockUuids, string $newItemUuid): array
    {
        return DB::transaction(function () use ($blockUuids, $newItemUuid) {
            $rows = DB::select("
                WITH
                source_blocks AS (
                    SELECT
                        b.uuid as old_block_uuid,
                        b.type,
                        b.description,
                        idx.ordinality as position
                    FROM UNNEST(?::uuid[]) WITH ORDINALITY AS idx(uuid, ordinality)
                    INNER JOIN blocks b ON b.uuid = idx.uuid
                ),
                inserted_blocks AS (
                    INSERT INTO blocks (type, description, item_uuid)
                    SELECT type, description, ?::uuid
                    FROM source_blocks
                    ORDER BY position
                    RETURNING uuid as new_block_uuid
                )
                SELECT
                    sb.old_block_uuid,
                    (
                        SELECT new_block_uuid
                        FROM inserted_blocks
                        OFFSET (sb.position - 1)
                        LIMIT 1
                    ) as new_block_uuid
                FROM source_blocks sb
            ", ['{' . implode(',', $blockUuids) . '}', $newItemUuid]);

            if (empty($rows)) {
                throw new RuntimeException("Could not clone items");
            }

            $map = [];
            foreach ($rows as $row) {
                $map[$row->old_block_uuid] = $row->new_block_uuid;
            }

            return $map;
        });
    }

    /**
     * @throws Throwable
     */
    public function cloneBlockFields(array $blockMapping): int
    {
        if (empty($blockMapping)) {
            return 0;
        }

        return DB::transaction(function () use ($blockMapping) {
            $sourceUuids = array_keys($blockMapping);
            $targetUuids = array_values($blockMapping);

            return DB::affectingStatement("
                INSERT INTO block_fields (block_uuid, field_name, field_value, field_type)
                SELECT
                    u.target_uuid as block_uuid,
                    bf.field_name,
                    bf.field_value,
                    bf.field_type
                FROM UNNEST(?::uuid[], ?::uuid[]) AS u(source_uuid, target_uuid)
                INNER JOIN block_fields bf ON bf.block_uuid = u.source_uuid
            ", [
                '{' . implode(',', $sourceUuids) . '}',
                '{' . implode(',', $targetUuids) . '}'
            ]);
        });
    }

    /**
     * @throws Throwable
     */
    public function cloneMedia(array $blockMapping, string $itemUuid): void
    {
        if ($blockMapping === []) {
            return;
        }

        $rows = $this->selectBlocks(array_keys($blockMapping));
        foreach ($rows as $media) {
            $pathInfo = pathinfo($media->s3_key);
            $extension = $pathInfo['extension'] ?? '';
            $newS3Key = $this->folder . '/' . $itemUuid . '/' . Str::uuid() . ($extension !== '' ? '.' . $extension : '');
            
            try {
                $newBlockUuid = $blockMapping[$media->block_uuid];
                $this->s3Client->copyObject([
                    'Bucket' => $this->bucket,
                    'Key' => $newS3Key,
                    'CopySource' => $media->s3_bucket . '/' . $media->s3_key,
                ]);

                DB::insert("
                    INSERT INTO media (block_uuid, media_type, s3_key, s3_bucket, url, metadata)
                    VALUES (?, ?, ?, ?, ?, ?::jsonb)",
                    [
                        $newBlockUuid,
                        $media->media_type,
                        $newS3Key,
                        $this->bucket,
                        $this->s3Client->getObjectUrl($this->bucket, $newS3Key),
                        $media->metadata,
                    ]
                );
            } catch (AwsException $e) {
                logger()->error('S3 copy failed', [
                    'old_key' => $media->s3_key,
                    'new_key' => $newS3Key,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        }
    }

    private function selectBlocks(array $blockUuids): array
    {
        return DB::select("
            SELECT m.uuid, m.block_uuid, m.media_type, m.s3_key, m.s3_bucket, m.url, m.metadata
            FROM UNNEST(?::uuid[]) AS u(block_uuid)
            INNER JOIN media m ON m.block_uuid = u.block_uuid
        ", ['{' . implode(',', $blockUuids) . '}']);
    }

    /**
     * @throws Throwable
     */
    public function deleteBlockFieldsByItem(string $itemUuid): int
    {
        return DB::transaction(function () use ($itemUuid) {
            return DB::affectingStatement("
                DELETE FROM block_fields
                WHERE block_uuid IN (
                    SELECT uuid
                    FROM blocks
                    WHERE item_uuid = ?
                )
            ", [$itemUuid]);
        });
    }

    /**
     * @throws Throwable
     */
    public function deleteMediaByItem(string $itemUuid): int
    {
        $result = DB::transaction(function () use ($itemUuid) {
            return DB::affectingStatement("
                DELETE FROM media
                WHERE block_uuid IN (
                    SELECT uuid
                    FROM blocks
                    WHERE item_uuid = ?
                )
            ", [$itemUuid]);
        });

        $this->deleteFolder($this->folder . '/' . $itemUuid);

        return $result;
    }

    /**
     * @throws \Exception
     */
    public function deleteFolder(string $folderPath): void
    {
        $folderPath = rtrim($folderPath, '/') . '/';

        try {
            $objects = $this->s3Client->listObjectsV2([
                'Bucket' => $this->bucket,
                'Prefix' => $folderPath,
            ]);

            if (empty($objects['Contents'])) {
                return;
            }

            $toDelete = array_map(function ($object) {
                return ['Key' => $object['Key']];
            }, $objects['Contents']);

            $this->s3Client->deleteObjects([
                'Bucket' => $this->bucket,
                'Delete' => [
                    'Objects' => $toDelete,
                ],
            ]);

            // Repeat if too many objects to be deleted
            if ($objects['IsTruncated']) {
                $this->deleteFolder($folderPath);
            }

        } catch (AwsException $e) {
            throw new \Exception("S3 folder deletion failed: " . $e->getMessage());
        }
    }

    /**
     * @throws Throwable
     */
    public function deleteBlocks(string $itemUuid): int
    {
        return DB::transaction(function () use ($itemUuid) {
            return DB::affectingStatement("
                DELETE FROM blocks
                WHERE item_uuid = ?
            ", [$itemUuid]);
        });
    }
}
