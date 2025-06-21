<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class TidalApi extends StreamingServiceApi
{
    public function getPlaylists(): array
    {
        throw new \RuntimeException("Not implemented");
    }

    public function getPlaylistTracks(string $playlistId): array
    {
        throw new \RuntimeException("Not implemented");
    }

    public function createPlaylist(string $name, array $tracks): string
    {
        throw new \RuntimeException("Not implemented");
    }

    public function refreshToken(): void
    {
        $response = Http::asForm()->post('https://auth.tidal.com/v1/oauth2/token', [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $this->oauthCredential->refresh_token,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Failed to refresh Tidal access token: ' . $response->body());
        }

        $data = $response->json();
        $accessToken = Arr::get($data, 'access_token');
        $refreshToken = Arr::get($data, 'refresh_token');
        $expiresIn = Arr::get($data, 'expires_in');

        $this->oauthCredential->update([
            'token'         => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at'    => now()->addSeconds($expiresIn),
        ]);
    }
}
