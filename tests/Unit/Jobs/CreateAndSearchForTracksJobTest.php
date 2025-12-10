<?php

namespace Tests\Unit\Jobs;

use App\Helpers\TrackDto;
use App\Jobs\CreateAndSearchForTracksJob;
use App\Models\OauthCredential;
use App\Models\Playlist;
use App\Models\PlaylistTransfer;
use App\Models\Track;
use App\Services\SpotifyService;
use App\Services\TidalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CreateAndSearchForTracksJobTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    public MockInterface $destinationMock;
    public MockInterface $sourceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sourceMock = Mockery::mock(SpotifyService::class);
        $this->destinationMock = Mockery::mock(TidalService::class);

        $this->app->bind(SpotifyService::class, fn() => $this->sourceMock);
        $this->app->bind(TidalService::class, fn() => $this->destinationMock);

        OauthCredential::query()->create([
            'id'            => (string)Str::uuid(),
            'provider'      => TidalService::PROVIDER,
            'provider_id'   => '1234567890',
            'email'         => 'example@gmail.com',
            'token'         => '$token',
            'refresh_token' => '$refreshToken',
            'expires_at'    => now()->addHour(),
            'user_id'       => $this->user()->getKey(),
        ]);
        OauthCredential::query()->create([
            'id'            => (string)Str::uuid(),
            'provider'      => SpotifyService::PROVIDER,
            'provider_id'   => '1234567890',
            'email'         => 'example@gmail.com',
            'token'         => '$token',
            'refresh_token' => '$refreshToken',
            'expires_at'    => now()->addHour(),
            'user_id'       => $this->user()->getKey(),
        ]);
    }

    public function test_it_updates_remote_ids_if_new_track_already_exists()
    {
        $isrc = 'asdf';
        $remoteIds = [
            'spotify'  => 'asdf',
            'snickers' => 'awooga',
        ];
        /** @var Track $track */
        $track = Track::factory()->create([
            'remote_ids' => $remoteIds,
            'isrc'       => $isrc,
        ]);

        $playlistTransfer = PlaylistTransfer::factory()->create([
            'source'      => 'spotify',
            'destination' => 'tidal',
            'user_id'     => $this->user()->getKey()
        ]);
        $playlist = Playlist::factory()->create(['user_id' => $this->user()->getKey()]);

        $tidalUuid = $this->faker->uuid;
        $this->destinationMock->shouldReceive('searchTrack')
            ->andReturn([
                new TrackDto([
                    'source'    => 'tidal',
                    'remote_id' => $tidalUuid,
                    'name'      => $track->name,
                    'artists'   => $track->artists,
                    'album'     => $track->albums,
                ])
            ]);

        new CreateAndSearchForTracksJob(
            $playlistTransfer,
            $playlist,
            $track->toDto($playlistTransfer->source),
        )->handle();

        $this->assertEquals(
            $track->fresh()->remote_ids,
            [...$remoteIds, 'tidal' => $tidalUuid,]
        );
    }

    public function happyPathApiMocks(): void
    {
        $this->sourceMock->shouldReceive('getPlaylistTracks')
            ->andReturn([
                new TrackDto([
                    'source'    => 'tidal',
                    'remote_id' => $this->faker->uuid,
                    'isrc'      => 'USUM72005901',
                    'name'      => 'oh wow nice collab',
                    'artists'   => ['2hollis', 'brakence'],
                    'album'     => ['name' => 'album name'],
                ])
            ]);
        $this->destinationMock->shouldReceive('createPlaylist')
            ->andReturn('fake-playlist-id');
        $this->destinationMock->shouldReceive('searchTrack')
            ->andReturn([
                new TrackDto([
                    'source'    => 'tidal',
                    'remote_id' => $this->faker->uuid,
                    'name'      => 'oh wow nice collab',
                ])
            ]);

        $this->destinationMock->shouldReceive('fillMissingInfo')
            ->andReturn(new TrackDto([
                'source'    => 'tidal',
                'remote_id' => $this->faker->uuid,
                'name'      => 'oh wow nice collab',
                'artists'   => ['2hollis', 'brakence']
            ]));

        $this->destinationMock->shouldReceive('addTracksToPlaylist')
            ->once();
    }
}
