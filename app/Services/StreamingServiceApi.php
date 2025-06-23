<?php

namespace App\Services;

use App\Helpers\Track;
use App\Models\OauthCredential;

abstract class StreamingServiceApi
{
    public const PROVIDER = 'generic';

    public function __construct(protected readonly OauthCredential $oauthCredential)
    {
        if ($oauthCredential->provider !== static::PROVIDER) {
            throw new \InvalidArgumentException("Invalid provider. Expected: " . static::PROVIDER);
        }
    }

    abstract public function maybeRefreshToken(): void;

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
            'tidal'   => new TidalApi($credential),
            default => throw new \InvalidArgumentException("Unsupported provider: {$provider}"),
        };
    }
}
