<?php

namespace Tests\Feature\Controllers;

use App\Models\OauthCredential;
use App\Models\User;
use App\Services\TidalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GetPlaylistsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_gets_playlists_from_service()
    {
        $user = User::factory()->create();
        OauthCredential::query()->create([
            'provider'    => 'tidal',
            'provider_id' => 'test-provider-id',
            'user_id'     => $user->getKey(),
        ]);

        $mockService = Mockery::mock(TidalService::class);
        $this->app->bind(TidalService::class, fn() => $mockService);

        $getPlaylistsResponse = [
            [
                'id'               => 'playlist-1',
                'name'             => 'My Playlist 1',
                'tracks'           => [
                    ['id' => 'track-1'],
                    ['id' => 'track-2'],
                ],
                'owner'            => [
                    'display_name' => 'User1',
                ],
                'number_of_tracks' => 2,
                'image_uri'        => null,
            ],
            [
                'id'               => 'playlist-2',
                'name'             => 'My Playlist 2',
                'tracks'           => [
                    ['id' => 'track-3'],
                    ['id' => 'track-4'],
                ],
                'owner'            => [
                    'display_name' => 'User2',
                ],
                'number_of_tracks' => 2,
                'image_uri'        => null,
            ],
        ];
        $mockService->shouldReceive('getPlaylists')
            ->andReturn($getPlaylistsResponse);

        $this->actingAs($user)
            ->get('api/streaming-service-playlists?service=tidal')
            ->assertSuccessful()
            ->assertJson([
                'playlists' => $getPlaylistsResponse,
            ]);

        $this->assertTrue(true);
    }

    public function test_it_404s_on_no_oauth_credential()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('api/streaming-service-playlists?service=tidal')
            ->assertStatus(404)
            ->assertJson([
                'message' => 'No OAuth credentials found',
            ]);
    }

    public function test_it_302s_on_no_service_provided()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('api/streaming-service-playlists')
            ->assertUnprocessable();
    }
}
