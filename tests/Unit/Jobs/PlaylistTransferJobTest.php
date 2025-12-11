<?php

namespace Tests\Unit\Jobs;

use App\Helpers\TrackDto;
use App\Jobs\PlaylistTransferJob;
use App\Models\OauthCredential;
use App\Models\Playlist;
use App\Models\PlaylistTransfer;
use App\Services\SpotifyService;
use App\Services\TidalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;
use function GuzzleHttp\json_encode;

class PlaylistTransferJobTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    public MockInterface $destinationMock;
    public MockInterface $sourceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->destinationMock = Mockery::mock(TidalService::class);
        $this->sourceMock = Mockery::mock(SpotifyService::class);

        $this->app->bind(TidalService::class, fn() => $this->destinationMock);
        $this->app->bind(SpotifyService::class, fn() => $this->sourceMock);

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

    public function test_it_updates_status_to_in_progress_when_running()
    {
        $this->markTestIncomplete();
    }

    public function test_it_updates_status_to_failed_on_failure()
    {
        $this->sourceMock->shouldReceive('getPlaylistTracks')
            ->andThrow(new \Exception());

        $this->app->bind(SpotifyService::class, fn() => $this->sourceMock);

        $job = PlaylistTransfer::factory()->create([
            'source'      => SpotifyService::PROVIDER,
            'destination' => TidalService::PROVIDER,
            'user_id'     => $this->user()->getKey(),
        ]);
        (new PlaylistTransferJob($job))->handle();
        $job->refresh();
        $this->assertEquals(PlaylistTransfer::STATUS_FAILED, $job->status);
    }

    public function test_it_updates_status_to_complete_on_completion()
    {
        $this->happyPathApiMocks();

        $pt = PlaylistTransfer::factory()->create([
            'source'      => SpotifyService::PROVIDER,
            'destination' => TidalService::PROVIDER,
            'user_id'     => $this->user()->getKey(),
        ]);
        (new PlaylistTransferJob($pt))->handle();
        $pt->refresh();
        $this->assertEquals(PlaylistTransfer::STATUS_COMPLETED, $pt->status);
    }

    public function test_it_updates_processed_playlists()
    {
        $this->happyPathApiMocks();

        $job = PlaylistTransfer::factory()->create([
            'source'      => SpotifyService::PROVIDER,
            'destination' => TidalService::PROVIDER,
            'user_id'     => $this->user()->getKey(),
            'playlists'   => [
                ['id' => 'asdf', 'name' => 'snickers']
            ]
        ]);
        (new PlaylistTransferJob($job))->handle();
        $job->refresh();
        $this->assertEquals(1, $job->playlists_processed);
    }

    public function test_it_creates_track_models()
    {
        $this->happyPathApiMocks();

        $job = PlaylistTransfer::factory()->create([
            'source'      => SpotifyService::PROVIDER,
            'destination' => TidalService::PROVIDER,
            'user_id'     => $this->user()->getKey(),
            'playlists'   => [
                ['id' => 'asdf', 'name' => 'snickers']
            ]
        ]);
        (new PlaylistTransferJob($job))->handle();
        $this->assertDatabaseHas('tracks', [
            'isrc_ids' => json_encode(['USUM72005901']),
            'name'     => 'oh wow nice collab',
            'artists'  => json_encode(['2hollis', 'brakence']),
            'album'    => 'album name',
        ]);
    }

    public function test_it_creates_track_with_one_remote_id_if_no_final_candidate()
    {
        $uuid = Str::uuid();
        $this->sourceMock->shouldReceive('getPlaylistTracks')
            ->andReturn([
                new TrackDto([
                    'source'    => 'spotify',
                    'remote_id' => $uuid,
                    'isrc_ids'  => ['USUM72005901'],
                    'name'      => 'oh wow nice collab',
                    'artists'   => ['2hollis', 'brakence'],
                    'album'     => ['name' => 'album name'],
                ])
            ]);
        $this->destinationMock->shouldReceive('createPlaylist')
            ->andReturn('fake-playlist-id');
        $this->destinationMock->shouldReceive('searchTrack')
            ->andReturn([]);
        $this->destinationMock->shouldReceive('addTracksToPlaylist')
            ->once();

        $pt = PlaylistTransfer::factory()->create([
            'source'      => SpotifyService::PROVIDER,
            'destination' => TidalService::PROVIDER,
            'user_id'     => $this->user()->getKey(),
            'playlists'   => [
                ['id' => 'asdf', 'name' => 'snickers']
            ]
        ]);
        (new PlaylistTransferJob($pt))->handle();

        $this->assertDatabaseHas('tracks', [
            'isrc_ids'   => json_encode(['USUM72005901']),
            'remote_ids' => json_encode(['spotify' => $uuid]),
        ]);
    }

    public function test_it_creates_track_with_two_remote_ids_if_final_canddidate_found()
    {
        $spotifyUuid = Str::uuid();
        $tidalUuid = Str::uuid();
        $this->sourceMock->shouldReceive('getPlaylistTracks')
            ->andReturn([
                new TrackDto([
                    'source'    => 'spotify',
                    'remote_id' => $spotifyUuid,
                    'isrc_ids'  => ['USUM72005901'],
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
                    'remote_id' => $tidalUuid,
                    'name'      => 'oh wow nice collab',
                ])
            ]);

        $this->destinationMock->shouldReceive('fillMissingInfo')
            ->andReturn(new TrackDto([
                'source'    => 'tidal',
                'remote_id' => $tidalUuid,
                'name'      => 'oh wow nice collab',
                'artists'   => ['2hollis', 'brakence']
            ]));

        $this->destinationMock->shouldReceive('addTracksToPlaylist');

        $job = PlaylistTransfer::factory()->create([
            'source'      => SpotifyService::PROVIDER,
            'destination' => TidalService::PROVIDER,
            'user_id'     => $this->user()->getKey(),
            'playlists'   => [
                ['id' => 'asdf', 'name' => 'snickers']
            ]
        ]);
        (new PlaylistTransferJob($job))->handle();

        $this->assertDatabaseHas('tracks', [
            'isrc_ids'   => json_encode(['USUM72005901']),
            'remote_ids' => json_encode([
                'spotify' => $spotifyUuid,
                'tidal'   => $tidalUuid,
            ]),
        ]);
    }

    public function test_it_creates_playlist_models()
    {
        $this->happyPathApiMocks();

        $job = PlaylistTransfer::factory()->create([
            'source'      => SpotifyService::PROVIDER,
            'destination' => TidalService::PROVIDER,
            'user_id'     => $this->user()->getKey(),
            'playlists'   => [
                ['id' => 1, 'name' => 'snickers1'],
            ],
        ]);
        (new PlaylistTransferJob($job))->handle();

        $this->assertDatabaseHas('playlists', [
            'name'      => 'snickers1',
            'service'   => SpotifyService::PROVIDER,
            'remote_id' => "1",
            'user_id'   => $this->user()->getKey(),
        ]);
    }

    public function test_it_associates_tracks_with_playlist()
    {
        $trackOne = new TrackDto([
            'source'    => 'spotify',
            'remote_id' => Str::uuid(),
            'isrc_ids'  => ['USUM72005901'],
            'name'      => 'oh wow nice collab',
            'artists'   => ['2hollis', 'brakence'],
            'album'     => ['name' => 'album name'],
        ]);
        $trackTwo = new TrackDto([
            'source'    => 'spotify',
            'remote_id' => Str::uuid(),
            'isrc_ids'  => ['USUM72005902'],
            'name'      => 'another fire track',
            'artists'   => ['artist1', 'artist2'],
            'album'     => ['name' => 'another album'],
        ]);

        $this->sourceMock->shouldReceive('getPlaylistTracks')
            ->andReturn([$trackOne, $trackTwo]);
        $this->destinationMock->shouldReceive('createPlaylist')
            ->andReturn('fake-playlist-id');
        $this->destinationMock->shouldReceive('searchTrack')
            ->andReturn([]);
        $this->destinationMock->shouldReceive('addTracksToPlaylist')
            ->once();

        $job = PlaylistTransfer::factory()->create([
            'source'      => SpotifyService::PROVIDER,
            'destination' => TidalService::PROVIDER,
            'user_id'     => $this->user()->getKey(),
            'playlists'   => [
                ['id' => 1, 'name' => 'snickers1'],
            ],
        ]);
        (new PlaylistTransferJob($job))->handle();

        $playlist = Playlist::query()
            ->where(['name' => 'snickers1'])
            ->firstOrFail();

        $this->assertNotEmpty($playlist->tracks()->get());
    }

    public function test_it_creates_one_playlist_if_transferred_twice()
    {
        $this->happyPathApiMocks();
        $job = PlaylistTransfer::factory()->create([
            'source'      => SpotifyService::PROVIDER,
            'destination' => TidalService::PROVIDER,
            'user_id'     => $this->user()->getKey(),
            'playlists'   => [
                ['id' => 1, 'name' => 'snickers1'],
            ],
        ]);
        (new PlaylistTransferJob($job))->handle();

        $this->assertDatabaseCount('playlists', 1);

        $this->happyPathApiMocks();
        $job = PlaylistTransfer::factory()->create([
            'source'      => SpotifyService::PROVIDER,
            'destination' => TidalService::PROVIDER,
            'user_id'     => $this->user()->getKey(),
            'playlists'   => [
                ['id' => 1, 'name' => 'snickers1'],
            ],
        ]);
        (new PlaylistTransferJob($job))->handle();

        $this->assertDatabaseCount('playlists', 1);
    }

    public function happyPathApiMocks(): void
    {
        $this->sourceMock->shouldReceive('getPlaylistTracks')
            ->andReturn([
                new TrackDto([
                    'source'    => 'tidal',
                    'remote_id' => $this->faker->uuid,
                    'isrc_ids'  => ['USUM72005901'],
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
                    'isrc_ids'  => ['USUM72005901'],
                    'name'      => 'oh wow nice collab',
                    'artists'   => ['2hollis', 'brakence'],
                    'album'     => ['name' => 'album name'],
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
