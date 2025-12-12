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

            if (
                $playlistTransfer->playlists_processed === $playlistTransfer->playlists()->count()
            ) {
                // oh god no
                $playlistTransfer->status = PlaylistTransfer::STATUS_COMPLETED;
                $playlistTransfer->saveQuietly();
            }

            PlaylistTransferStatusUpdated::dispatch($playlistTransfer);
        }
    }
}
