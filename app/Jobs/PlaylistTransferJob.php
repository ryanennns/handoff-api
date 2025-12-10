<?php

namespace App\Jobs;

use App\Models\PlaylistTransfer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

class PlaylistTransferJob implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly PlaylistTransfer $playlistTransfer,
    )
    {
    }

    public function handle(): void
    {
        try {
            Log::info("PlaylistTransferJob started");

            $this->playlistTransfer->update(['status' => PlaylistTransfer::STATUS_IN_PROGRESS]);

            Bus::chain(
                collect($this->playlistTransfer->playlists)
                    ->map(fn($pt) => new CreatePlaylistAndDispatchTracksJob($this->playlistTransfer, $pt))
                    ->toArray(),
            )->catch(function (Throwable $throwable) {
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
        } catch (Throwable $e) {
            $this->playlistTransfer->update(['status' => PlaylistTransfer::STATUS_FAILED]);

            $this->fail($e);
        }
    }
}
