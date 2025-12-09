<?php

namespace App\Jobs;

use App\Helpers\TrackDto;
use App\Models\Playlist;
use App\Models\PlaylistTransfer;
use App\Models\Track;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PlaylistJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly PlaylistTransfer $playlistTransfer,
        private readonly array            $playlist
    )
    {
        //
    }

    public function handle(): void
    {
        $source = $this->playlistTransfer->sourceApi();
        $destination = $this->playlistTransfer->destinationApi();

        $playlistModel = Playlist::query()->firstOrCreate([
            'service'   => $source::PROVIDER,
            'remote_id' => $this->playlist['id'],
        ], [
            'user_id' => $this->playlistTransfer->user_id,
            'name'    => $this->playlist['name'],
        ]);

        $tracks = $source->getPlaylistTracks($this->playlist['id']);
        $playlistId = $destination->createPlaylist($this->playlist['name']);

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
            function (TrackDto $track) use ($playlistModel, $destination, $source, &$failedTracks, &$tracksToAdd) {
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

                $model = $this->updateOrCreateTrack($track, $remoteIds);
                if ($model) {
                    $playlistModel->tracks()->save($model);
                }
            }
        );

        $destination->addTracksToPlaylist($playlistId, $tracksToAdd);

        $this->playlistTransfer->playlists_processed += 1;
        $this->playlistTransfer->save();

        Log::info("Playlist created and populated w/ ID $playlistId", [
            'failed_tracks' => $failedTracks,
        ]);

    }

    public function updateOrCreateTrack(TrackDto $track, array $remoteIds): ?Track
    {
        if (!$track->isrc) {
            return null;
        }

        $trackModel = Track::query()
            ->where(['isrc' => $track->isrc])
            ->first();

        if ($trackModel) {
            $trackModel->update([
                'remote_ids' => array_merge(
                    $trackModel->remote_ids,
                    $remoteIds,
                )
            ]);

            return $trackModel;
        }

        return Track::query()->create([
            'isrc'       => $track->isrc,
            'name'       => $track->name,
            'artists'    => $track->artists,
            'album'      => $track->album['name'],
            'explicit'   => $track->explicit,
            'remote_ids' => $remoteIds,
        ]);
    }
}
