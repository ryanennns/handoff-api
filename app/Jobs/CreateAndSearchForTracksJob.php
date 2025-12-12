<?php

namespace App\Jobs;

use App\Helpers\TrackDto;
use App\Models\Playlist;
use App\Models\PlaylistTransfer;
use App\Models\Track;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Throwable;

class CreateAndSearchForTracksJob implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public function __construct(
        private readonly PlaylistTransfer $playlistTransfer,
        private readonly Playlist         $playlist,
        private TrackDto                  $track,
    )
    {
    }

    public function handle(): void
    {
        try {
            $destination = $this->playlistTransfer->destinationApi();

            $candidates = $destination->searchTrack($this->track);

            $finalCandidate = collect($candidates)->first(
                fn(TrackDto $dto) => collect($this->track->isrc_ids)->hasAny($dto->isrc_ids)
            );

            if (!$finalCandidate) {
                $candidates = collect($candidates)
                    ->reject(fn($c) => $c->name !== $this->track->name && $c->name !== $this->track->trimmedName())
                    ->map(fn($c) => is_null($c->artists) ? $destination->fillMissingInfo($c) : $c)
                    ->reject(fn($c) => empty($c->artists))
                    ->reject(fn($c) => $c->version !== $this->track->version);

                $finalCandidate = collect($candidates)->first(
                    fn($candidate) => collect($this->track->artists)->contains(
                        fn($a) => levenshtein($a, $candidate->artists[0]) < 2
                            || levenshtein(strtolower($a), $candidate->artists[0]) < 2
                    )
                );
            }

            $remoteIds = [$this->playlistTransfer->source => $this->track->remote_id];
            if ($finalCandidate) {
                $remoteIds[$destination::PROVIDER] = $finalCandidate->remote_id;
            }

            $model = $this->updateOrCreateTrack(
                $this->track,
                $remoteIds,
                $finalCandidate->isrc_ids ?? null,
            );
            if (
                $model &&
                $this->playlist->tracks()->where('tracks.id', $model->getKey())->doesntExist()
            ) {
                $this->playlist->tracks()->save($model);
            }
        } catch (Throwable $e) {
        }
    }

    public function updateOrCreateTrack(TrackDto $track, array $remoteIds, array|null $isrc): ?Track
    {
        $trackModel = Track::query()
            ->where(function ($query) use ($track) {
                collect($track->isrc_ids)->each(
                    fn(string $isrc) => $query->orWhereJsonContains('isrc_ids', $isrc)
                );
            })
            ->first();

        if (!$trackModel) {
            $trackModel = Track::query()
                ->where('name', $track->name)
                ->where(function ($query) use ($track) {
                    collect($track->artists)->each(
                        fn(string $artist) => $query->orWhereJsonContains('artists', $artist)
                    );
                })
                ->first();
        }

        if ($trackModel) {
            $trackModel->remote_ids = array_merge(
                $trackModel->remote_ids,
                $remoteIds,
            );
            $trackModel->isrc_ids = array_merge(
                $trackModel->isrc_ids,
                $isrc
            );
            $trackModel->save();

            return $trackModel;
        }

        return Track::query()->create([
            'isrc_ids'   => $track->isrc_ids ?? [],
            'name'       => $track->name,
            'artists'    => $track->artists,
            'album'      => Arr::get($track->album, 'name'),
            'explicit'   => $track->explicit,
            'remote_ids' => $remoteIds,
        ]);
    }
}
