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
            throw new \InvalidArgumentException(
                "Invalid provider. Expected: " . static::PROVIDER . ", received: " . $this->oauthCredential->provider
            );
        }
    }

    abstract public function maybeRefreshToken(): void;

    abstract public function getPlaylists(): array;

    /** @returns Track[] */
    abstract public function getPlaylistTracks(string $playlistId): array;

    abstract public function createPlaylist(string $name, array $tracks): string;

    /** @returns Track[] */
    abstract public function searchTrack(Track $track): array;

    abstract public function addTrackToPlaylist(string $playlistId, Track $track): void;

    public static function getServiceForProvider(
        string          $provider,
        OauthCredential $credential
    ): ?StreamingServiceApi
    {
        return match ($provider) {
            'spotify' => app(SpotifyApi::class, ['oauthCredential' => $credential]),
            'youtube' => app(YouTubeApi::class, ['oauthCredential' => $credential]),
            'tidal' => app(TidalApi::class, ['oauthCredential' => $credential]),
            default => throw new \InvalidArgumentException("Unsupported provider: {$provider}"),
        };
    }
}
