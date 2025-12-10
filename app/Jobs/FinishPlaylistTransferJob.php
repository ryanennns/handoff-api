<?php

namespace App\Jobs;

use App\Models\PlaylistTransfer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class FinishPlaylistTransferJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly PlaylistTransfer $playlistTransfer)
    {
    }

    public function handle(): void
    {
        Log::info("FinishPlaylistTransferJob started");

        $this->playlistTransfer->update([
            'status' => PlaylistTransfer::STATUS_COMPLETED
        ]);
    }
}
