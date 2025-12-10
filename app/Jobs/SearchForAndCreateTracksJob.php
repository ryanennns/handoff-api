<?php

namespace App\Jobs;

use App\Helpers\TrackDto;
use App\Models\Playlist;
use App\Models\PlaylistTransfer;
use App\Models\Track;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SearchForAndCreateTracksJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly PlaylistTransfer $playlistTransfer,
        private readonly Playlist         $playlist,
        private TrackDto                  $track,
    )
    {
    }

    public function handle(): void
    {
        $source = $this->playlistTransfer->sourceApi();
        $destination = $this->playlistTransfer->destinationApi();

        $candidates = $destination->searchTrack($this->track);
        $candidates = collect($candidates)
            ->reject(fn($c) => $c->name !== $this->track->name && $c->name !== $this->track->trimmedName())
            ->map(fn($c) => is_null($c->artists) ? $destination->fillMissingInfo($c) : $c)
            ->reject(fn($c) => empty($c->artists));

        $finalCandidate = collect($candidates)->first(
            fn($candidate) => collect($this->track->artists)->contains(
                fn($a) => levenshtein($a, $candidate->artists[0]) < 2
                    || levenshtein(strtolower($a), $candidate->artists[0]) < 2
            )
        );

        $remoteIds = [$source::PROVIDER => $this->track->remote_id];
        if ($finalCandidate) {
            $remoteIds[$destination::PROVIDER] = $finalCandidate->remote_id;
        }

        if ($model = $this->updateOrCreateTrack($this->track, $remoteIds)) {
            $this->playlist->tracks()->save($model);
        }
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
            $trackModel->remote_ids = array_merge(
                $trackModel->remote_ids,
                $remoteIds,
            );
            $trackModel->save();

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
