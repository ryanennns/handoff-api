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

            collect($this->playlistTransfer->playlists)
                ->each(function ($playlist) use ($sourceApi, $destinationApi) {
                    $tracks = $sourceApi->getPlaylistTracks($playlist['id']);
                    $playlistId = $destinationApi->createPlaylist($playlist['name'], $tracks);

                    collect($tracks)->each(function ($track) use ($destinationApi, $sourceApi, $playlistId) {
                        $candidates = $destinationApi->searchTrack($track);
                        $candidates = collect($candidates)
                            ->reject(
                                fn($c) => $c->name !== $track->name && $c->name !== $track->trimmedName()
                            );

                        // if any are missing artist info, fetch artist names for better matching
                        if (
                            $candidates->contains(fn($c) => empty($c->artists))
                        ) {
                            $candidates = $sourceApi->fillArtistInfo($track);
                        }

                        $finalCandidate = collect($candidates)->first(
                            fn($candidate) => collect($track->artists)
                                ->contains(
                                    fn($a) => levenshtein($a, $candidate->artists[0]) < 2
                                        || levenshtein(strtolower($a), $candidate->artists[0]) < 2
                                ));

                        if ($finalCandidate) {
                            $destinationApi->addTrackToPlaylist($playlistId, $track);
                        }
                    });
                });
        } catch (\Throwable $exception) {
            Log::error($exception->getMessage(), $exception->getTrace());
            $this->playlistTransfer->update(['status' => 'failed']);
            return;
        }

        $this->playlistTransfer->update(['status' => 'completed']);
    }
}
