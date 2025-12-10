<?php

namespace App\Observers;

use App\Events\PlaylistTransferStatusUpdated;
use App\Models\PlaylistTransfer;

class PlaylistTransferObserver
{
    public function updated(PlaylistTransfer $playlistTransfer): void
    {
        if (
            $playlistTransfer->isDirty('status')
            || $playlistTransfer->isDirty('playlists_processed')
        ) {
            PlaylistTransferStatusUpdated::dispatch($playlistTransfer);

            return;
        }

        if (
            $playlistTransfer->playlists_processed === count($playlistTransfer->playlists)
        ) {
            // oh god no
            $playlistTransfer->status = PlaylistTransfer::STATUS_COMPLETED;
            $playlistTransfer->saveQuietly();
            PlaylistTransferStatusUpdated::dispatch($playlistTransfer);
        }
    }
}
