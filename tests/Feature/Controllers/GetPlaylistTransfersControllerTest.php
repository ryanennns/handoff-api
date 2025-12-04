<?php

namespace Tests\Feature\Controllers;

use App\Models\PlaylistTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetPlaylistTransfersControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_playlist_transfers()
    {
        $user = User::factory()->create();
        $playlistTransfers = PlaylistTransfer::factory(3)->create(['user_id' => $user->getKey()]);

        $response = $this->actingAs($user)->get(route('playlist-transfers.index'));
        $response->assertOk();
        $response->assertJsonFragment([
            'data' => $playlistTransfers
                ->map(fn($pt) => $pt->fresh())
                ->map(fn(PlaylistTransfer $playlistTransfer) => [
                    'id'                  => $playlistTransfer->getKey(),
                    'source'              => $playlistTransfer->source,
                    'destination'         => $playlistTransfer->destination,
                    'playlists'           => $playlistTransfer->playlists,
                    'playlists_processed' => $playlistTransfer->playlists_processed,
                    'status'              => $playlistTransfer->status,
                    'created_at'          => $playlistTransfer->created_at->toIso8601String(),
                ])->toArray()
        ]);
    }
}
