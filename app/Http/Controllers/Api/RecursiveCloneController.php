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
        $episodeUuid = $request->validated('episode_uuid');

        $episode = DB::selectOne('SELECT uuid FROM episodes WHERE uuid = ?', [$episodeUuid]);

        if ($episode === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Episode not found',
            ], 404);
        }

        $traceId = (string) Str::uuid();
        $transactionId = (string) Str::uuid();

        $command = new RecursiveCloneEpisodeCommand();
        $command->traceId = $traceId;
        $command->transactionId = $transactionId;
        $command->episodeUuid = $episodeUuid;

        RecursiveCloneEpisodeCommandHandler::dispatch($command);

        return response()->json([
            'status' => 'accepted',
            'trace_id' => $traceId,
            'transaction_id' => $transactionId,
            'message' => 'Recursive clone process initiated',
        ]);
    }
}
