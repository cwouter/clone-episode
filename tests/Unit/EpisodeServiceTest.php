<?php

namespace Tests\Unit;

use App\Services\EpisodeService;
use Aws\S3\S3Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EpisodeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_clone_episode_creates_a_new_episode_with_same_content(): void
    {
        $source = DB::selectOne(
            "INSERT INTO episodes (title, description) VALUES (?, ?) RETURNING uuid",
            ['My title', 'My description']
        );

        $this->assertNotNull($source);
        $sourceUuid = $source->uuid;

        $service = new EpisodeService($this->createMock(S3Client::class));

        $newUuid = $service->cloneEpisode($sourceUuid);

        $this->assertNotSame($sourceUuid, $newUuid);

        $sourceRow = DB::table('episodes')->where('uuid', $sourceUuid)->first();
        $newRow = DB::table('episodes')->where('uuid', $newUuid)->first();

        $this->assertNotNull($sourceRow);
        $this->assertNotNull($newRow);
        $this->assertSame($sourceRow->title, $newRow->title);
        $this->assertSame($sourceRow->description, $newRow->description);

        $this->assertSame(2, DB::table('episodes')->count());
    }

    public function test_delete_episode_removes_row_and_returns_affected_rows(): void
    {
        $episode = DB::selectOne(
            "INSERT INTO episodes (title, description) VALUES (?, ?) RETURNING uuid",
            ['To delete', null]
        );

        $this->assertNotNull($episode);
        $uuid = $episode->uuid;

        $service = new EpisodeService($this->createMock(S3Client::class));

        $affected = $service->deleteEpisode($uuid);

        $this->assertSame(1, $affected);
        $this->assertSame(0, DB::table('episodes')->where('uuid', $uuid)->count());
    }
}
