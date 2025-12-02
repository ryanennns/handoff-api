<?php

namespace App\Services;

use App\Helpers\Track;
use App\Models\OauthCredential;

abstract class StreamingService
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

    abstract public function addTrackToPlaylist(string $playlistId, Track $track): void;

    /** @returns Track[] */
    abstract public function searchTrack(Track $track): array;

    public function fillMissingInfo(Track $track): Track
    {
        return $track;
    }

    public static function getServiceForProvider(
        string          $provider,
        OauthCredential $credential
    ): ?StreamingService
    {
        return match ($provider) {
            'spotify' => app(SpotifyApi::class, ['oauthCredential' => $credential]),
            'youtube' => app(YouTubeApi::class, ['oauthCredential' => $credential]),
            'tidal' => app(TidalApi::class, ['oauthCredential' => $credential]),
            default => throw new \InvalidArgumentException("Unsupported provider: {$provider}"),
        };
    }
}
