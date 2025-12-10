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
use Illuminate\Support\Facades\Log;
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

            $finalCandidate = collect($candidates)->first(fn(TrackDto $dto) => $dto->isrc === $this->track->isrc);

            if ($finalCandidate) {
                Log::info("Found ISRC match for {$this->track->name}/{$this->track->isrc}");
            }

            if (!$finalCandidate) {
                Log::info("Failed to find ISRC match for {$this->track->name}/{$this->track->isrc}");

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
            }

            $remoteIds = [$this->playlistTransfer->source => $this->track->remote_id];
            if ($finalCandidate) {
                Log::info("Found next best match for {$this->track->name}/{$this->track->isrc}");
                $remoteIds[$destination::PROVIDER] = $finalCandidate->remote_id;
            }

            $model = $this->updateOrCreateTrack($this->track, $remoteIds);
            if (
                $model &&
                $this->playlist->tracks()->where('tracks.id', $model->getKey())->doesntExist()
            ) {
                $this->playlist->tracks()->save($model);
            }
        } catch (Throwable $e) {
            $this->fail($e);
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
            'album'      => Arr::get($track->album, 'name'),
            'explicit'   => $track->explicit,
            'remote_ids' => $remoteIds,
        ]);
    }
}
