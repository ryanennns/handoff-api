<?php

namespace App\Http\Controllers;

use App\Jobs\PlaylistTransferJob;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TriggerPlaylistTransferController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'source'      => 'required|string',
            'destination' => 'required|string',
            'playlists'   => 'required|array',
        ]);

        $source = $request->input('source');
        $destination = $request->input('destination');
        $playlists = $request->input('playlists');

        /** @var User $user */
        $user = auth()->user();
        $playlistTransfer = $user->playlistTransfers()->create([
            'source'      => $source,
            'destination' => $destination,
            'playlists'   => $playlists,
            'status'      => 'pending',
        ]);

        PlaylistTransferJob::dispatch($playlistTransfer);

        return response()->json([
            'message' => 'Playlist transfer triggered successfully.',
            'data'    => [
                'id'          => $playlistTransfer->getKey(),
                'source'      => $source,
                'destination' => $destination,
                'playlists'   => $playlists,
            ],
        ], 201);
    }
}
