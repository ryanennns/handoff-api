<?php

namespace Unit\Jobs;

use App\Helpers\TrackDto;
use App\Jobs\CreatePlaylistAndDispatchTracksJob;
use App\Models\PlaylistTransfer;
use App\Services\SpotifyService;
use App\Services\TidalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CreatePlaylistAndDispatchTracksJobTest extends TestCase
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
    }

    public function test_it_marks_playlist_as_processed_on_search_track_failure()
    {
        $playlistTransfer = PlaylistTransfer::factory()->create([
            'user_id'     => $this->user()->getKey(),
            'source'      => 'spotify',
            'destination' => 'tidal',
        ]);

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

        $this->destinationMock
            ->shouldReceive('searchTrack')
            ->andThrow(new Mockery\Exception());

        $this->destinationMock->shouldReceive('addTracksToPlaylist')
            ->once();

        new CreatePlaylistAndDispatchTracksJob(
            $playlistTransfer,
            $this->newPlaylist(),
        )->handle();

        $playlistTransfer->refresh();
        $this->assertEquals(1, $playlistTransfer->playlists_processed);
    }

    public function test_it_marks_playlist_as_processed_on_populate_playlist_failure()
    {
        $playlistTransfer = PlaylistTransfer::factory()->create([
            'user_id'     => $this->user()->getKey(),
            'source'      => 'spotify',
            'destination' => 'tidal',
        ]);

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
            ->andThrow(new Mockery\Exception());

        new CreatePlaylistAndDispatchTracksJob(
            $playlistTransfer,
            $this->newPlaylist(),
        )->handle();

        $playlistTransfer->refresh();
        $this->assertEquals(1, $playlistTransfer->playlists_processed);
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
