<?php

namespace App\Observers;

use App\Events\PlaylistTransferStatusUpdated;
use App\Models\PlaylistTransfer;

class PlaylistTransferObserver
{
    public function updated(PlaylistTransfer $playlistTransfer)
    {
        if ($playlistTransfer->isDirty('status')) {
            PlaylistTransferStatusUpdated::dispatch($playlistTransfer);
        }
    }
}
