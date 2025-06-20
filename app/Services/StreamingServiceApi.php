<?php

namespace App\Services;

use App\Helpers\Track;
use App\Models\OauthCredential;

abstract class StreamingServiceApi
{
    abstract public function getPlaylists(): array;

    /** @returns Track[] */
    abstract public function getPlaylistTracks(string $playlistId): array;

    abstract public function createPlaylist(string $name, array $tracks): string;

    public static function getServiceForProvider(
        string          $provider,
        OauthCredential $credential
    ): ?StreamingServiceApi
    {
        return match ($provider) {
            'spotify' => new SpotifyApi($credential),
            'youtube' => new YouTubeApi($credential),
            default => throw new \InvalidArgumentException("Unsupported provider: {$provider}"),
        };
    }
}
