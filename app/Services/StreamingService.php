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

    abstract public function createPlaylist(string $name): string | false;

    abstract public function addTrackToPlaylist(string $playlistId, Track $track): bool;

    public function addTracksToPlaylist(string $playlistId, array $tracks): void
    {
        foreach ($tracks as $track) {
            $this->addTrackToPlaylist($playlistId, $track);
        }
    }

    /** @returns Track[] */
    abstract public function searchTrack(Track $track): array;

    abstract public function fillMissingInfo(Track $track): Track;

    public static function getServiceForProvider(
        string          $provider,
        OauthCredential $credential
    ): ?StreamingService
    {
        return match ($provider) {
            'spotify' => app(SpotifyService::class, ['oauthCredential' => $credential]),
            'youtube' => app(YouTubeService::class, ['oauthCredential' => $credential]),
            'tidal' => app(TidalService::class, ['oauthCredential' => $credential]),
            default => throw new \InvalidArgumentException("Unsupported provider: {$provider}"),
        };
    }
}
