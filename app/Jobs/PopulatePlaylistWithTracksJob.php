<?php

namespace App\Jobs;

use App\Helpers\TrackDto;
use App\Models\Playlist;
use App\Models\PlaylistTransfer;
use App\Models\Track;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PopulatePlaylistWithTracksJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly PlaylistTransfer $playlistTransfer,
        private readonly string           $playlistId,
        private readonly Playlist         $playlistModel,
    )
    {
        //
    }

    public function handle(): void
    {
        $tracksToAdd = $this->playlistModel
            ->tracks()
            ->get()
            ->filter(fn(Track $t) => array_key_exists(
                $this->playlistTransfer->destination,
                $t->remote_ids,
            ));

        $this->playlistTransfer
            ->destinationApi()
            ->addTracksToPlaylist(
                $this->playlistId,
                $tracksToAdd->map(fn(Track $track) => new TrackDto([
                    'source'    => $this->playlistTransfer->source,
                    'remote_id' => $track->remote_ids[$this->playlistTransfer->source],
                    'isrc'      => $track->isrc,
                    'name'      => $track->name,
                    'artists'   => $track->artists,
                    'album'     => ['name' => $track->album],
                    'explicit'  => $track->explicit,
                ]))->toArray(),
            );
    }
}
