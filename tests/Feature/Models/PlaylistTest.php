<?php

namespace Tests\Feature\Models;

use App\Models\Playlist;
use App\Models\Track;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaylistTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_has_many_tracks()
    {
        $playlist = Playlist::factory()->create([
            'user_id' => $this->user()->getKey()
        ]);

        $playlist->tracks()->create(
            Track::factory()->raw()
        );
        $playlist->tracks()->create(
            Track::factory()->raw()
        );

        $this->assertCount(2, $playlist->tracks);
    }
}
