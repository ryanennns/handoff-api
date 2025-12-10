<?php

namespace App\Jobs;

use App\Models\Playlist;
use App\Models\PlaylistTransfer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreatePlaylistAndDispatchTracksJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly PlaylistTransfer $playlistTransfer,
        private readonly array            $playlist
    )
    {
    }

    public function handle(): void
    {
        $source = $this->playlistTransfer->sourceApi();
        $destination = $this->playlistTransfer->destinationApi();

        $playlistModel = Playlist::query()->firstOrCreate([
            'service'   => $source::PROVIDER,
            'remote_id' => $this->playlist['id'],
        ], [
            'user_id' => $this->playlistTransfer->user_id,
            'name'    => $this->playlist['name'],
        ]);

        $tracks = $source->getPlaylistTracks($this->playlist['id']);
        $destinationPlaylistId = $destination->createPlaylist($this->playlist['name']);

        if (!$destinationPlaylistId) {
            Log::error("Failed to create playlist $destinationPlaylistId", [
                'source'      => $source::PROVIDER,
                'destination' => $destination::PROVIDER,
                'playlist_id' => $destinationPlaylistId,
                'tracks'      => json_encode($tracks)
            ]);

            return;
        }

        Bus::chain(
            [
                ...collect($tracks)
                    ->map(fn($t) => new CreateAndSearchForTracksJob(
                        $this->playlistTransfer,
                        $playlistModel,
                        $t,
                    ))
                    ->toArray(),
                new PopulatePlaylistWithTracksJob(
                    $this->playlistTransfer,
                    $destinationPlaylistId,
                    $playlistModel
                ),
                new IncrementPlaylistsProcessedJob($this->playlistTransfer),
            ]
        )->catch(function (Throwable $e) {
            Log::error("A failure occurred with a playlist transfer", [
                'exception' => [
                    'message' => $e->getMessage(),
                    'trace'   => $e->getTraceAsString(),
                ],
            ]);
        })->dispatch();
    }
}
