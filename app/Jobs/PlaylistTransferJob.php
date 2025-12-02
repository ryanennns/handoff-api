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
        $this->playlistTransfer->update(['status' => PlaylistTransfer::STATUS_IN_PROGRESS]);

        try {
            $sourceApi = $this->playlistTransfer->sourceApi();
            $destinationApi = $this->playlistTransfer->destinationApi();

            collect($this->playlistTransfer->playlists)
                ->each(function ($playlist) use ($sourceApi, $destinationApi) {
                    $tracks = $sourceApi->getPlaylistTracks($playlist['id']);
                    $playlistId = $destinationApi->createPlaylist($playlist['name'], $tracks);
                    $failedTracks = [];

                    collect($tracks)->each(
                        function ($track) use ($destinationApi, $sourceApi, $playlistId, &$failedTracks) {
                            $candidates = $destinationApi->searchTrack($track);
                            $candidates = collect($candidates)
                                ->reject(
                                    fn($c) => $c->name !== $track->name && $c->name !== $track->trimmedName()
                                )->map(
                                    fn($c) => empty($c->artists) ? $sourceApi->fillMissingInfo($c) : $c
                                );


                            $finalCandidate = collect($candidates)->first(
                                fn($candidate) => collect($track->artists)->contains(
                                    fn($a) => levenshtein($a, $candidate->artists[0]) < 2
                                        || levenshtein(strtolower($a), $candidate->artists[0]) < 2
                                )
                            );

                            if ($finalCandidate) {
                                $destinationApi->addTrackToPlaylist($playlistId, $track);
                            }

                            if (!$finalCandidate) {
                                $failedTracks[] = $track;
                            }
                        }
                    );

                    // todo -- do something with failed tracks
                });
        } catch (\Throwable $exception) {
            Log::error($exception->getMessage(), $exception->getTrace());
            $this->playlistTransfer->update(['status' => PlaylistTransfer::STATUS_FAILED]);
            return;
        }

        $this->playlistTransfer->update(['status' => PlaylistTransfer::STATUS_COMPLETED]);
    }
}
