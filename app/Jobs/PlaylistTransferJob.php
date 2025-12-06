<?php

namespace App\Jobs;

use App\Helpers\TrackDto;
use App\Models\PlaylistTransfer;
use App\Models\Track;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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
            $source = $this->playlistTransfer->sourceApi();
            $destination = $this->playlistTransfer->destinationApi();

            collect($this->playlistTransfer->playlists)
                ->each(function ($playlist) use ($source, $destination) {
                    $tracks = $source->getPlaylistTracks($playlist['id']);
                    $playlistId = $destination->createPlaylist($playlist['name']);

                    if (!$playlistId) {
                        Log::error("Failed to create playlist $playlistId", [
                            'source'      => $source::PROVIDER,
                            'destination' => $destination::PROVIDER,
                            'playlist_id' => $playlistId,
                            'tracks'      => json_encode($tracks)
                        ]);

                        return;
                    }

                    $tracksToAdd = [];
                    $failedTracks = [];

                    collect($tracks)->each(
                        function (TrackDto $track) use ($destination, $source, &$failedTracks, &$tracksToAdd) {
                            $candidates = $destination->searchTrack($track);
                            $candidates = collect($candidates)
                                ->reject(fn($c) => $c->name !== $track->name && $c->name !== $track->trimmedName())
                                ->map(fn($c) => is_null($c->artists) ? $destination->fillMissingInfo($c) : $c)
                                ->reject(fn($c) => empty($c->artists));

                            $finalCandidate = collect($candidates)->first(
                                fn($candidate) => collect($track->artists)->contains(
                                    fn($a) => levenshtein($a, $candidate->artists[0]) < 2
                                        || levenshtein(strtolower($a), $candidate->artists[0]) < 2
                                )
                            );

                            $finalCandidate
                                ? $tracksToAdd[] = $finalCandidate
                                : $failedTracks[] = $track;

                            $remoteIds = [$source::PROVIDER => $track->remote_id];
                            if ($finalCandidate) {
                                $remoteIds[$destination::PROVIDER] = $finalCandidate->remote_id;
                            }
                            Track::query()->firstOrCreate([
                                'isrc' => $track->isrc,
                            ], [
                                'name'       => $track->name,
                                'artists'    => $track->artists,
                                'album'      => $track->album['name'],
                                'explicit'   => $track->explicit,
                                'remote_ids' => $remoteIds,
                            ]);
                        }
                    );

                    $destination->addTracksToPlaylist($playlistId, $tracksToAdd);

                    $this->playlistTransfer->playlists_processed += 1;
                    $this->playlistTransfer->save();

                    Log::info("Playlist created and populated w/ ID $playlistId", [
                        'failed_tracks' => $failedTracks,
                    ]);
                });
        } catch (Throwable $exception) {
            Log::error($exception->getMessage(), $exception->getTrace());
            $this->playlistTransfer->update(['status' => PlaylistTransfer::STATUS_FAILED]);
            return;
        }

        $this->playlistTransfer->update(['status' => PlaylistTransfer::STATUS_COMPLETED]);
    }
}
