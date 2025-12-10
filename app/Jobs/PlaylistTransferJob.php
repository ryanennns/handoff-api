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

        Bus::chain(
            [
                ...collect($this->playlistTransfer->playlists)
                    ->map(fn($pt) => new CreatePlaylistAndDispatchTracksJob($this->playlistTransfer, $pt))
                    ->toArray(),
                new FinishPlaylistTransferJob($this->playlistTransfer),
            ]
        )->catch(function (Throwable $throwable) {
            $this->playlistTransfer->update(['status' => PlaylistTransfer::STATUS_FAILED]);

            Log::error(
                "A failure occurred with a playlist transfer ",
                [
                    'exception' => [
                        'message' => $throwable->getMessage(),
                        'trace'   => $throwable->getTraceAsString(),
                    ]
                ]
            );
        })->dispatch();
    }
}
