<?php

namespace Tests\Feature\Models;

use App\Models\Playlist;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_has_many_playlists()
    {
        $track = Track::factory()->create();

        $track->playlists()->create(
            Playlist::factory()->raw([
                'user_id' => $this->user()->getKey(),
            ])
        );
        $track->playlists()->create(
            Playlist::factory()->raw([
                'user_id' => $this->user()->getKey(),
            ])
        );

        $this->assertCount(2, $track->playlists);
    }
}
