<?php

namespace App\Jobs;

use App\Models\PlaylistTransfer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FinishPlaylistTransfer implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly PlaylistTransfer $playlistTransfer)
    {
    }

    public function handle(): void
    {
        $this->playlistTransfer->update([
            'status' => PlaylistTransfer::STATUS_COMPLETED
        ]);
    }
}
