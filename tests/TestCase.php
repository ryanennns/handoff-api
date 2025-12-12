<?php

namespace Tests;

use App\Models\OauthCredential;
use App\Models\Playlist;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    private User $user;
    private OauthCredential $oauthCredential;

    public function user(): User
    {
        if (!isset($this->user)) {
            $this->user = User::factory()->create();
        }

        $this->oauthCredential = OauthCredential::factory()->create([
            'provider' => 'tidal',
            'user_id'  => $this->user->getKey(),
        ]);

        OauthCredential::factory()->create([
            'provider' => 'spotify',
            'user_id'  => $this->user->getKey(),
        ]);

        return $this->user;
    }

    public function newPlaylist(array $props = []): Playlist
    {
        return Playlist::factory()->create([
            'user_id' => $this->user()->getKey(),
            ...$props,
        ]);
    }
}
