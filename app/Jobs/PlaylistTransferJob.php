<?php

namespace App\Jobs;

use App\Models\PlaylistTransfer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

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
        $this->playlistTransfer->update(['status' => 'in_progress']);

        try {
            $sourceApi = $this->playlistTransfer->sourceApi();
            $destinationApi = $this->playlistTransfer->destinationApi();

            collect($this->playlistTransfer->playlists, true)
                ->each(function ($playlist) use ($sourceApi, $destinationApi) {
                    $tracks = $sourceApi->getPlaylistTracks($playlist['id']);
                    $destinationApi->createPlaylist($playlist['name'], $tracks);
                });
        } catch (\Throwable $exception) {
            Log::error($exception->getMessage(), $exception->getTrace());
            $this->playlistTransfer->update(['status' => 'failed']);
            return;
        }

        $this->playlistTransfer->update(['status' => 'completed']);
    }
}
