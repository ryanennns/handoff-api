<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TidalApi extends StreamingServiceApi
{
    public const PROVIDER = 'tidal';
    public const BASE_URL = 'https://openapi.tidal.com/v2';

    public function getPlaylists(): array
    {
        $response = Http::withToken($this->oauthCredential->token)
            ->get(self::BASE_URL . '/playlists', [
                'countryCode'         => 'CA',
                'filter[r.owners.id]' => $this->oauthCredential->provider_id,
            ]);

        $json = $response->json();
        Log::info('tidal api response', $json);

        return collect(Arr::get($json, 'data', []))
            ->map(fn($item) => [
                'id'               => Arr::get($item, 'id'),
                'name'             => Arr::get($item, 'attributes.name'),
                'tracks'           => null,
                'owner'            => [
                    'display_name' => Arr::get($item, 'attributes.relationships.owners.data', ''),
                ],
                'number_of_tracks' => Arr::get($item, 'attributes.numberOfItems'),
                'image_uri'        => Arr::get($item, 'attributes.relatinoships.coverArt.data', ''),
            ])->toArray();
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
