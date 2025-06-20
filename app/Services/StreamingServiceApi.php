<?php

namespace App\Services;

use App\Models\OauthCredential;

abstract class StreamingServiceApi
{
    abstract public function getPlaylists(): array;

    abstract public function getPlaylistTracks(string $playlistId): array;

    public static function getServiceForProvider(
        string          $provider,
        OauthCredential $credential
    ): ?StreamingServiceApi
    {
        switch ($provider) {
            case 'spotify':
                return new SpotifyApi($credential);
            default:
                return null;
        }
    }
}
