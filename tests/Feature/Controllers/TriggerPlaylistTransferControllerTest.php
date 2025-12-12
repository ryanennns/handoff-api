<?php

namespace Tests\Feature\Controllers;

use App\Jobs\PlaylistTransferJob;
use App\Models\Playlist;
use App\Models\PlaylistTransfer;
use App\Services\SpotifyService;
use App\Services\TidalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TriggerPlaylistTransferControllerTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    public function test_it_dispatches_playlist_transfer_job()
    {
        Bus::fake();

        $this->actingAs($this->user())->post('api/playlist-transfers/trigger', [
            'source'      => SpotifyService::PROVIDER,
            'destination' => TidalService::PROVIDER,
            'playlists'   => [
                [
                    'id'               => $this->faker->uuid(),
                    'name'             => $this->faker->word(),
                    'tracks'           => 'asdf',
                    'owner'            => [
                        'id'   => $this->user()->getKey(),
                        'name' => 'ronald mcdonanld',
                    ],
                    'number_of_tracks' => 5,
                    'image_uri'        => $this->faker->url(),
                ]
            ],
        ])->assertCreated()->assertJsonStructure([
            'message',
            'data' => [
                'id',
                'source',
                'destination',
            ],
        ]);

        Bus::assertDispatched(PlaylistTransferJob::class);
    }

    #[DataProvider('provideIncompletePayloads')]
    public function test_it_rejects_with_incomplete_data($payload)
    {
        $this->actingAs($this->user())
            ->postJson('api/playlist-transfers/trigger', $payload)
            ->assertUnprocessable();
    }

    public function test_it_creates_playlists_and_associates_them_with_playlist_transfer_model()
    {
        Bus::fake();

        $this->assertDatabaseCount('playlists', 0);
        $this->assertDatabaseCount('playlist_transfers', 0);

        $this->actingAs($this->user())
            ->post('api/playlist-transfers/trigger', [
                'source'      => SpotifyService::PROVIDER,
                'destination' => TidalService::PROVIDER,
                'playlists'   => [
                    [
                        'id'               => $this->faker->uuid(),
                        'name'             => $this->faker->word(),
                        'tracks'           => 'asdf',
                        'owner'            => [
                            'id'   => $this->user()->getKey(),
                            'name' => 'ronald mcdonanld',
                        ],
                        'number_of_tracks' => 5,
                        'image_uri'        => $this->faker->url(),
                    ]
                ],
            ])->assertCreated()->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'source',
                    'destination',
                ],
            ]);

        $this->assertDatabaseCount('playlists', 1);
        $this->assertDatabaseCount('playlist_transfers', 1);

        $pt = PlaylistTransfer::query()->first();
        $this->assertEquals(
            $pt->playlists()->first()->getKey(),
            Playlist::query()->first()->getKey()
        );
    }

    public function test_it_updates_existing_playlist_if_already_exists()
    {
        Bus::fake();

        $this->assertDatabaseCount('playlists', 0);
        $this->assertDatabaseCount('playlist_transfers', 0);

        $playlist = [
            'id'               => $this->faker->uuid(),
            'name'             => $this->faker->word(),
            'tracks'           => 'asdf',
            'owner'            => [
                'id'   => $this->user()->getKey(),
                'name' => 'ronald mcdonanld',
            ],
            'number_of_tracks' => 5,
            'image_uri'        => $this->faker->url(),
        ];
        $this->actingAs($this->user())
            ->post('api/playlist-transfers/trigger', [
                'source'      => SpotifyService::PROVIDER,
                'destination' => TidalService::PROVIDER,
                'playlists'   => [
                    $playlist
                ],
            ])->assertCreated()->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'source',
                    'destination',
                ],
            ]);

        $this->assertDatabaseCount('playlists', 1);
        $this->assertDatabaseCount('playlist_transfers', 1);

        $this->actingAs($this->user())
            ->post('api/playlist-transfers/trigger', [
                'source'      => SpotifyService::PROVIDER,
                'destination' => TidalService::PROVIDER,
                'playlists'   => [
                    $playlist
                ],
            ])->assertCreated()->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'source',
                    'destination',
                ],
            ]);

        $this->assertDatabaseCount('playlists', 1);
    }

    public static function provideIncompletePayloads(): array
    {
        return [
            ['payload' => ['source' => 'asdf', 'destination' => 'asdf']],
            ['payload' => ['source' => 'asdf', 'playlists' => ['asdf', 'asdf']]],
            ['payload' => ['destination' => 'asdf', 'playlists' => ['asdf', 'asdf']]],
        ];
    }
}
