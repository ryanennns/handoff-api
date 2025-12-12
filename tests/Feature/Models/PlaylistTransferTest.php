<?php

namespace Feature\Models;

use App\Models\Playlist;
use App\Models\PlaylistTransfer;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaylistTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_has_many_playlists()
    {
        /** @var PlaylistTransfer $playlistTransfer */
        $playlistTransfer = PlaylistTransfer::factory()->create(['user_id' => $this->user()->getKey()]);

        $playlist = Playlist::factory()->create(['user_id' => $this->user()->getKey()]);
        $playlistTransfer->playlists()->save($playlist);

        $this->assertCount(1, $playlistTransfer->playlists()->get());
    }
}
