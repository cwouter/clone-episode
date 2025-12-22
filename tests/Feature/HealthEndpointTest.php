<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_ok_payload(): void
    {
        $response = $this->getJson('/api/health');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'status',
                'services' => [
                    'database',
                    'redis',
                    'queue',
                    's3',
                ],
                'timestamp',
            ]);
    }
}
