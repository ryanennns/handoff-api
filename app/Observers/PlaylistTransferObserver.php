<?php

namespace App\Observers;

use App\Events\PlaylistTransferStatusUpdated;
use App\Models\PlaylistTransfer;
use Illuminate\Support\Facades\Broadcast;

class PlaylistTransferObserver
{
    public function updated(PlaylistTransfer $playlistTransfer)
    {
        if ($playlistTransfer->isDirty('status')) {
            PlaylistTransferStatusUpdated::dispatch($playlistTransfer);
        }
    }
}
