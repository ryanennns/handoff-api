<?php

namespace Tests\Feature\Controllers;

use App\Models\Playlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

class GetPlaylistsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_requires_auth()
    {
        $this->getJson(route('playlists.index'))
            ->assertUnauthorized();
    }

    public function test_it_returns_playlists()
    {
        $playlists = Playlist::factory(3)
            ->create(['user_id' => $this->user()->getKey()]);

        $response = $this->actingAs($this->user())
            ->getJson(route('playlists.index'));

        $response->assertOk();
        $response->assertJsonStructure([
            'data',
            'links',
            'meta',
        ]);

        collect($response->json('data'))
            ->each(
                fn($playlist, $index) => $this->assertEquals(
                    Arr::get($playlist, 'id'),
                    Arr::get($playlists, "$index.id")
                )
            );
    }
}
