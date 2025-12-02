<?php

namespace Feature\Jobs;

use App\Helpers\Track;
use App\Jobs\PlaylistTransferJob;
use App\Models\OauthCredential;
use App\Models\PlaylistTransfer;
use App\Services\SpotifyService;
use App\Services\TidalService;
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

        $this->tidalMock = Mockery::mock(TidalService::class);
        $this->spotifyMock = Mockery::mock(SpotifyService::class);

        $this->app->bind(TidalService::class, fn() => $this->tidalMock);
        $this->app->bind(SpotifyService::class, fn() => $this->spotifyMock);

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
        $this->spotifyMock->shouldReceive('getPlaylistTracks')
            ->andThrow(new \Exception());

        $this->app->bind(SpotifyService::class, fn() => $this->spotifyMock);

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

        $job = PlaylistTransfer::factory()->create([
            'source'      => SpotifyService::PROVIDER,
            'destination' => TidalService::PROVIDER,
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

        $this->spotifyMock->shouldReceive('fillMissingInfo')
            ->andReturn(new Track([
                'source'    => 'tidal',
                'remote_id' => $this->faker->uuid,
                'name'      => 'oh wow nice collab',
                'artists'   => ['2hollis', 'brakence']
            ]));

        $this->tidalMock->shouldReceive('addTrackToPlaylist');
    }
}
