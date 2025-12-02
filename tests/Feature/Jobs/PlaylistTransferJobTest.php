<?php

namespace Feature\Jobs;

use App\Helpers\Track;
use App\Jobs\PlaylistTransferJob;
use App\Models\OauthCredential;
use App\Models\PlaylistTransfer;
use App\Services\SpotifyApi;
use App\Services\TidalApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class PlaylistTransferJobTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    public MockInterface $tidalMock;
    public MockInterface $spotifyMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tidalMock = Mockery::mock(TidalApi::class);
        $this->spotifyMock = Mockery::mock(SpotifyApi::class);

        $this->app->bind(TidalApi::class, fn() => $this->tidalMock);
        $this->app->bind(SpotifyApi::class, fn() => $this->spotifyMock);

        OauthCredential::query()->create([
            'id'            => (string)Str::uuid(),
            'provider'      => TidalApi::PROVIDER,
            'provider_id'   => '1234567890',
            'email'         => 'example@gmail.com',
            'token'         => '$token',
            'refresh_token' => '$refreshToken',
            'expires_at'    => now()->addHour(),
            'user_id'       => $this->user()->getKey(),
        ]);
        OauthCredential::query()->create([
            'id'            => (string)Str::uuid(),
            'provider'      => SpotifyApi::PROVIDER,
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
        $this->spotifyMock->shouldReceive('getPlaylistTracks')
            ->andThrow(new \Exception());

        $this->app->bind(SpotifyApi::class, fn() => $this->spotifyMock);

        $job = PlaylistTransfer::factory()->create([
            'source'      => SpotifyApi::PROVIDER,
            'destination' => TidalApi::PROVIDER,
            'user_id'     => $this->user()->getKey(),
        ]);
        (new PlaylistTransferJob($job))->handle();
        $job->refresh();
        $this->assertEquals(PlaylistTransfer::STATUS_FAILED, $job->status);
    }

    public function test_it_updates_status_to_complete_on_completion()
    {
        $this->happyPathApiMocks();

        $job = PlaylistTransfer::factory()->create([
            'source'      => SpotifyApi::PROVIDER,
            'destination' => TidalApi::PROVIDER,
            'user_id'     => $this->user()->getKey(),
        ]);
        (new PlaylistTransferJob($job))->handle();
        $job->refresh();
        $this->assertEquals(PlaylistTransfer::STATUS_COMPLETED, $job->status);
    }

    public function happyPathApiMocks(): void
    {
        $this->spotifyMock->shouldReceive('getPlaylistTracks')
            ->andReturn([
                new Track([
                    'source'    => 'tidal',
                    'remote_id' => $this->faker->uuid,
                    'name'      => 'oh wow nice collab',
                    'artists'   => ['2hollis', 'brakence']
                ])
            ]);
        $this->tidalMock->shouldReceive('createPlaylist');
        $this->tidalMock->shouldReceive('searchTrack')
            ->andReturn([
                new Track([
                    'source'    => 'tidal',
                    'remote_id' => $this->faker->uuid,
                    'name'      => 'oh wow nice collab',
                ])
            ]);

        $this->spotifyMock->shouldReceive('fillArtistInfo')
            ->andReturn([
                new Track([
                    'source'    => 'tidal',
                    'remote_id' => $this->faker->uuid,
                    'name'      => 'oh wow nice collab',
                    'artists'   => ['2hollis', 'brakence']
                ])
            ]);

        $this->tidalMock->shouldReceive('addTrackToPlaylist');
    }
}
