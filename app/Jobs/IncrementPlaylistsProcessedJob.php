<?php

namespace App\Jobs;

use App\Models\PlaylistTransfer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class IncrementPlaylistsProcessedJob implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        private PlaylistTransfer $playlistTransfer
    )
    {
    }

    public function handle(): void
    {
        $this->playlistTransfer->playlists_processed += 1;
        $this->playlistTransfer->save();
    }
}
