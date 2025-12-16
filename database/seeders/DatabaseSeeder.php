<?php

namespace Database\Seeders;

use App\Models\OauthCredential;
use App\Models\Playlist;
use App\Models\PlaylistTransfer;
use App\Models\User;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::factory()->create([
            'name'  => 'Test User',
            'email' => 'test@gmail.com',
        ]);

        $playlists = Playlist::factory(15)->create(['user_id' => $user->getKey()]);

        $user->playlistTransfers()
            ->create(
                PlaylistTransfer::factory()->raw([
                    'source'      => 'spotify',
                    'destination' => 'tidal',
                    'status'      => 'completed'
                ])
            )->playlists()->saveMany($playlists);

        $user->playlistTransfers()
            ->create(
                PlaylistTransfer::factory()->raw([
                    'source'      => 'spotify',
                    'destination' => 'tidal',
                    'status'      => 'in_progress'
                ])
            )->playlists()->saveMany($playlists);

        $user->playlistTransfers()
            ->create(
                PlaylistTransfer::factory()->raw([
                    'source'      => 'spotify',
                    'destination' => 'tidal',
                    'status'      => 'failed'
                ])
            )->playlists()->saveMany($playlists);

        $user->playlistTransfers()
            ->create(
                PlaylistTransfer::factory()->raw([
                    'source'      => 'spotify',
                    'destination' => 'tidal',
                    'status'      => 'pending'
                ])
            )->playlists()->saveMany($playlists);

        $user->oauthCredentials()->create(OauthCredential::factory()->raw(['provider' => 'tidal']));
        $user->oauthCredentials()->create(OauthCredential::factory()->raw(['provider' => 'spotify']));
    }
}
