<?php

namespace Tests\Feature\Controllers;

use App\Jobs\PlaylistTransferJob;
use App\Models\OauthCredential;
use App\Services\SpotifyApi;
use App\Services\TidalApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TriggerPlaylistTransferControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dispatches_playlist_transfer_job()
    {
        Bus::fake();

        OauthCredential::query()->create([
            'user_id'     => $this->user()->getKey(),
            'provider'    => TidalApi::PROVIDER,
            'provider_id' => TidalApi::PROVIDER,
        ]);
        OauthCredential::query()->create([
            'user_id'     => $this->user()->getKey(),
            'provider'    => SpotifyApi::PROVIDER,
            'provider_id' => SpotifyApi::PROVIDER,
        ]);

        $this->actingAs($this->user())->post('api/playlist-transfers/trigger', [
            'source'      => SpotifyApi::PROVIDER,
            'destination' => TidalApi::PROVIDER,
            'playlists'   => ['playlist1', 'playlist2'],
        ])->assertCreated()->assertJsonStructure([
            'message',
            'data' => [
                'id',
                'source',
                'destination',
                'playlists',
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

    public static function provideIncompletePayloads(): array
    {
        return [
            ['payload' => ['source' => 'asdf', 'destination' => 'asdf']],
            ['payload' => ['source' => 'asdf', 'playlists' => ['asdf', 'asdf']]],
            ['payload' => ['destination' => 'asdf', 'playlists' => ['asdf', 'asdf']]],
        ];
    }
}
