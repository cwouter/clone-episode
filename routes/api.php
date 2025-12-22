<?php

use App\Http\Controllers\Api\RecursiveCloneController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'services' => [
            'database' => DB::connection()->getPdo() ? 'up' : 'down',
            'redis' => Redis::connection()->ping() ? 'up' : 'down',
            'queue' => Queue::connection('rabbitmq')->size() >= 0 ? 'up' : 'down',
        ],
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::post('/recursive-clone-episode', [RecursiveCloneController::class, 'store']);
