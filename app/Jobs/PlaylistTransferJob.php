<?php

namespace App\Jobs;

use App\Models\PlaylistTransfer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

class PlaylistTransferJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly PlaylistTransfer $playlistTransfer,
    )
    {
    }

    public function handle(): void
    {
        $this->playlistTransfer->update(['status' => PlaylistTransfer::STATUS_IN_PROGRESS]);

        try {
            Bus::chain(
                [
                    ...collect($this->playlistTransfer->playlists)
                        ->map(fn($pt) => new PlaylistJob($this->playlistTransfer, $pt))
                        ->toArray(),
                    new FinishPlaylistTransfer($this->playlistTransfer),
                ]
            )->dispatch();
        } catch (Throwable $exception) {
            Log::error($exception->getMessage(), $exception->getTrace());
            $this->playlistTransfer->update(['status' => PlaylistTransfer::STATUS_FAILED]);
            return;
        }
    }
}
