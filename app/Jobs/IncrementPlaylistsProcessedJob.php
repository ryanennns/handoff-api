<?php

namespace App\Jobs;

use App\Models\PlaylistTransfer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class IncrementPlaylistsProcessedJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private PlaylistTransfer $playlistTransfer
    )
    {
    }

    public function handle(): void
    {
        Log::info("CreateAndSearchForTracksJob finished");

        $this->playlistTransfer->playlists_processed += 1;
        $this->playlistTransfer->save();
    }
}
