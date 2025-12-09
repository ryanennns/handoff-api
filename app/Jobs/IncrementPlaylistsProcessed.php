<?php

namespace App\Jobs;

use App\Models\PlaylistTransfer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IncrementPlaylistsProcessed implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private PlaylistTransfer $playlistTransfer
    )
    {
        $this->playlistTransfer->playlists_processed += 1;
        $this->playlistTransfer->save();
    }

    public function handle(): void
    {
        //
    }
}
