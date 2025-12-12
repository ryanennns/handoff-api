<?php

namespace App\Http\Controllers;

use App\Jobs\PlaylistTransferJob;
use App\Models\PlaylistTransfer;
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
            'status'      => PlaylistTransfer::STATUS_PENDING,
        ]);

        collect($playlists)
            ->each(function ($playlist) use ($playlistTransfer, $source, $user) {
                $playlist = $user->playlists()->create([
                    'service'   => $source,
                    'name'      => $playlist['name'],
                    'remote_id' => $playlist['id'],
                ]);

                $playlistTransfer->playlists()->save($playlist);
            });

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
