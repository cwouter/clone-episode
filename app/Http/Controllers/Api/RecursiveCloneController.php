<?php

namespace App\Http\Controllers\Api;

use App\Events\RecursiveCloneEpisodeCommand;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecursiveCloneEpisodeRequest;
use App\Jobs\RecursiveCloneEpisodeCommandHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecursiveCloneController extends Controller
{
    public function store(RecursiveCloneEpisodeRequest $request): JsonResponse
    {
        $traceId = (string)Str::uuid();
        $episodeUuid = $request->validated('episode_uuid');
        $episode = DB::selectOne('SELECT uuid FROM episodes WHERE uuid = ?', [$episodeUuid]);

        if ($episode === null) {
            return response()->json([
                'status' => 'error',
                'trace_id' => $traceId,
                'message' => 'Episode not found',
            ], 404);
        }

        $command = new RecursiveCloneEpisodeCommand($traceId);
        $command->episodeUuid = $episodeUuid;

        RecursiveCloneEpisodeCommandHandler::dispatch($command);

        return response()->json([
            'status' => 'accepted',
            'trace_id' => $traceId,
            'transaction_id' => $command->transactionId,
            'message' => 'Recursive clone process initiated',
        ]);
    }
}
