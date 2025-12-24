<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ItemsService
{
    /**
     * @throws \Throwable
     */
    public function cloneItems(array $itemUuids, string $newPartUuid): array
    {
        return DB::transaction(function () use ($itemUuids, $newPartUuid) {
            $rows = DB::select("
                WITH
                source_items AS (
                    SELECT
                        i.uuid as old_item_uuid,
                        i.name,
                        i.details,
                        idx.ordinality as position
                    FROM UNNEST(?::uuid[]) WITH ORDINALITY AS idx(uuid, ordinality)
                    INNER JOIN items i ON i.uuid = idx.uuid
                ),
                inserted_items AS (
                    INSERT INTO items (name, details, part_uuid)
                    SELECT name, details, ?::uuid
                    FROM source_items
                    ORDER BY position
                    RETURNING uuid as new_item_uuid
                )
                SELECT
                    sp.old_item_uuid,
                    (
                        SELECT new_item_uuid
                        FROM inserted_items
                        OFFSET (sp.position - 1)
                        LIMIT 1
                    ) as new_item_uuid
                FROM source_items sp
            ", ['{' . implode(',', $itemUuids) . '}', $newPartUuid]);

            if (empty($rows)) {
                throw new \RuntimeException("Could not clone items");
            }

            $map = [];
            foreach ($rows as $row) {
                $map[$row->old_item_uuid] = $row->new_item_uuid;
            }

            return $map;
        });
    }

    /**
     * @throws \Throwable
     */
    public function deleteItems(string $partUuid): int
    {
        return DB::transaction(function () use ($partUuid) {
            return DB::affectingStatement("
                DELETE FROM items as i
                WHERE i.part_uuid = ?
            ", [$partUuid]);
        });
    }
}
