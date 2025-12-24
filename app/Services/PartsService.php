<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PartsService
{
    /**
     * @throws \Throwable
     */
    public function cloneParts(array $partUuids, string $newEpisodeUuid): array
    {
        return DB::transaction(function () use ($partUuids, $newEpisodeUuid) {
            $rows = DB::select("
                WITH
                source_parts AS (
                    SELECT
                        p.uuid as old_part_uuid,
                        p.name,
                        p.description,
                        idx.ordinality as position
                    FROM UNNEST(?::uuid[]) WITH ORDINALITY AS idx(uuid, ordinality)
                    INNER JOIN parts p ON p.uuid = idx.uuid
                ),
                inserted_parts AS (
                    INSERT INTO parts (name, description, episode_uuid)
                    SELECT name, description, ?::uuid
                    FROM source_parts
                    ORDER BY position
                    RETURNING uuid as new_part_uuid
                )
                SELECT
                    sp.old_part_uuid,
                    (
                        SELECT new_part_uuid
                        FROM inserted_parts
                        OFFSET (sp.position - 1)
                        LIMIT 1
                    ) as new_part_uuid
                FROM source_parts sp
            ", ['{' . implode(',', $partUuids) . '}', $newEpisodeUuid]);

            if (empty($rows)) {
                throw new \RuntimeException("Could not clone parts");
            }

            $map = [];
            foreach ($rows as $row) {
                $map[$row->old_part_uuid] = $row->new_part_uuid;
            }

            return $map;
        });
    }

    /**
     * @throws \Throwable
     */
    public function deleteParts(string $episodeUuid): int
    {
        return DB::transaction(function () use ($episodeUuid) {
            return DB::affectingStatement("
                DELETE FROM parts as i
                WHERE i.episode_uuid = ?
            ", [$episodeUuid]);
        });
    }
}
