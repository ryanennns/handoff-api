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
use Tests\TestCase;

class PlaylistTransferJobTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    public function test_it_snickers()
    {
        $tidalMock = Mockery::mock(TidalApi::class);
        $spotifyMock = Mockery::mock(SpotifyApi::class);

        $spotifyMock->shouldReceive('getPlaylistTracks')
            ->andReturn([
                new Track([
                    'source'    => 'tidal',
                    'remote_id' => $this->faker->uuid,
                    'name'      => 'oh wow nice collab',
                    'artists'   => ['2hollis', 'brakence']
                ])
            ]);
        $tidalMock->shouldReceive('createPlaylist');
        $tidalMock->shouldReceive('searchTrack')
            ->andReturn([
                new Track([
                    'source'    => 'tidal',
                    'remote_id' => $this->faker->uuid,
                    'name'      => 'oh wow nice collab',
                ])
            ]);

        $spotifyMock->shouldReceive('fillArtistInfo')
            ->andReturn([
                new Track([
                    'source'    => 'tidal',
                    'remote_id' => $this->faker->uuid,
                    'name'      => 'oh wow nice collab',
                    'artists'   => ['2hollis', 'brakence']
                ])
            ]);

        $tidalMock->shouldReceive('addTrackToPlaylist');

        $this->app->bind(TidalApi::class, fn() => $tidalMock);
        $this->app->bind(SpotifyApi::class, fn() => $spotifyMock);

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

        (new PlaylistTransferJob(
            PlaylistTransfer::factory()->create([
                'source'      => SpotifyApi::PROVIDER,
                'destination' => TidalApi::PROVIDER,
                'user_id'     => $this->user()->getKey()
            ])
        ))->handle();

        $this->assertTrue(true);
    }
}
