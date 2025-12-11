<?php

namespace Tests\Unit\Jobs;

use App\Jobs\PopulatePlaylistWithTracksJob;
use App\Models\Playlist;
use App\Models\PlaylistTransfer;
use App\Models\Track;
use App\Services\TidalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Mockery;
use Tests\TestCase;

class PopulatePlaylistsWithTracksJobTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    public function test_it_calls_add_tracks_to_playlist_with_tracks()
    {
        $playlistTransfer = PlaylistTransfer::factory()->create([
            'source'      => 'tidal',
            'destination' => 'tidal',
            'user_id'     => $this->user()->getKey()
        ]);
        $playlistId = 'abc-123';
        $playlist = Playlist::factory()->create(['user_id' => $this->user()->getKey()]);
        $tracks = Track::factory(3)->create(['remote_ids' => ['tidal' => 123]]);
        $playlist->tracks()->saveMany($tracks);

        $mock = Mockery::mock(TidalService::class);
        $mock->shouldReceive('addTracksToPlaylist')
            ->once()
            ->withArgs(function ($pid, $ts) use ($tracks, $playlistId) {
                $this->assertEquals($pid, $playlistId);
                $this->assertEquals($ts[0]->isrc_ids, $tracks[0]->isrc_ids);
                $this->assertEquals($ts[1]->isrc_ids, $tracks[1]->isrc_ids);
                $this->assertEquals($ts[2]->isrc_ids, $tracks[2]->isrc_ids);

                return true;
            });

        $this->app->bind(TidalService::class, fn() => $mock);

        new PopulatePlaylistWithTracksJob(
            $playlistTransfer,
            $playlistId,
            $playlist,
        )->handle();
    }
}
