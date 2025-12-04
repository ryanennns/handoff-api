<?php

namespace App\Observers;

use App\Events\PlaylistTransferStatusUpdated;
use App\Models\PlaylistTransfer;
use Illuminate\Support\Facades\Broadcast;

class PlaylistTransferObserver
{
    public function updated(PlaylistTransfer $playlistTransfer): void
    {
        if (
            $playlistTransfer->isDirty('status')
            || $playlistTransfer->isDirty('playlists_processed')
        ) {
            PlaylistTransferStatusUpdated::dispatch($playlistTransfer);
        }
    }
}
