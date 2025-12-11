<?php

namespace Database\Seeders;

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

        Playlist::factory(15)->create(['user_id' => $user->getKey()]);

        $user->playlistTransfers()
            ->create(
                PlaylistTransfer::factory()->raw([
                    'source'      => 'spotify',
                    'destination' => 'tidal',
                    'status'      => 'completed'
                ])
            );

        $user->playlistTransfers()
            ->create(
                PlaylistTransfer::factory()->raw([
                    'source'      => 'spotify',
                    'destination' => 'tidal',
                    'status'      => 'in_progress'
                ])
            );

        $user->playlistTransfers()
            ->create(
                PlaylistTransfer::factory()->raw([
                    'source'      => 'spotify',
                    'destination' => 'tidal',
                    'status'      => 'failed'
                ])
            );

        $user->playlistTransfers()
            ->create(
                PlaylistTransfer::factory()->raw([
                    'source'      => 'spotify',
                    'destination' => 'tidal',
                    'status'      => 'pending'
                ])
            );
    }
}
