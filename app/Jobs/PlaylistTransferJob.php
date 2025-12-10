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
            Log::error(
                "A failure occurred with playlist transfer ID {$this->playlistTransfer->getKey()}",
                [
                    'class'                   => self::class,
                    'playlist_transfer_model' => $this->playlistTransfer->toArray(),
                    'exception'               => [
                        'message' => $throwable->getMessage(),
                        'trace'   => $throwable->getTraceAsString(),
                    ]
                ]
            );
        })->dispatch();
    }
}
