<?php

namespace App\Jobs;

use App\Models\Playlist;
use App\Models\PlaylistTransfer;
use App\Models\Track;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PopulatePlaylistWithTracksJob implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly PlaylistTransfer $playlistTransfer,
        private readonly string           $playlistId,
        private readonly Playlist         $playlistModel,
    )
    {
    }

    public function handle(): void
    {
        $tracksToAdd = $this->playlistModel
            ->tracks()
            ->get()
            ->filter(fn(Track $t) => array_key_exists(
                $this->playlistTransfer->destination,
                $t->remote_ids,
            ))
            ->map(fn(Track $track) => $track->toDto($this->playlistTransfer->destination))
            ->toArray();

        $this->playlistTransfer
            ->destinationApi()
            ->addTracksToPlaylist(
                $this->playlistId,
                $tracksToAdd,
            );
    }
}
