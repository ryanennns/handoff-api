<?php

namespace App\Jobs;

use App\Helpers\TrackDto;
use App\Models\Playlist;
use App\Models\Track;
use App\Services\StreamingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SearchForAndCreateTracksJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly StreamingService $source,
        private readonly StreamingService $destination,
        private readonly string           $playlistId,
        private readonly Playlist         $playlist,
        private TrackDto                  $track,
    )
    {
    }

    public function handle(): void
    {
        $candidates = $this->destination->searchTrack($this->track);
        $candidates = collect($candidates)
            ->reject(fn($c) => $c->name !== $this->track->name && $c->name !== $this->track->trimmedName())
            ->map(fn($c) => is_null($c->artists) ? $this->destination->fillMissingInfo($c) : $c)
            ->reject(fn($c) => empty($c->artists));

        $finalCandidate = collect($candidates)->first(
            fn($candidate) => collect($this->track->artists)->contains(
                fn($a) => levenshtein($a, $candidate->artists[0]) < 2
                    || levenshtein(strtolower($a), $candidate->artists[0]) < 2
            )
        );

        $remoteIds = [$this->source::PROVIDER => $this->track->remote_id];
        if ($finalCandidate) {
            $remoteIds[$this->destination::PROVIDER] = $finalCandidate->remote_id;
            $this->destination->addTracksToPlaylist($this->playlistId, $finalCandidate);
        }

        $model = $this->updateOrCreateTrack($this->track, $remoteIds);
        if ($model) {
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
