<?php

namespace App\Services;

use App\Helpers\Track;
use App\Models\OauthCredential;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TidalApi extends StreamingServiceApi
{
    public const PROVIDER = 'tidal';
    public const BASE_URL = 'https://openapi.tidal.com/v2';

    public function __construct(OauthCredential $oauthCredential)
    {
        parent::__construct($oauthCredential);

        $this->maybeRefreshToken();

        Log::info('oauth cred', ['token' => $oauthCredential->token]);
    }

    public function getPlaylists(): array
    {
        $playlistResponse = Http::withToken($this->oauthCredential->token)
            ->get(self::BASE_URL . '/playlists', [
                'countryCode'         => 'CA', // todo whatado about this :/
                'filter[r.owners.id]' => $this->oauthCredential->provider_id,
            ]);

        return collect(Arr::get($playlistResponse->json(), 'data', []))
            ->map(function ($item) {
                $tracksUrl = Arr::get($item, 'relationships.items.links.self');
                $tracksResponse = Http::withToken($this->oauthCredential->token)
                    ->get(self::BASE_URL . '/' . $tracksUrl);

                $tracksJson = $tracksResponse->json();
                $tracks = collect(Arr::get($tracksJson, 'data', []))
                    ->map(fn($track) => ['id' => Arr::get($track, 'id')]);

                return [
                    'id'               => Arr::get($item, 'id'),
                    'name'             => Arr::get($item, 'attributes.name'),
                    'tracks'           => $tracks,
                    'owner'            => [
                        'display_name' => Arr::get($item, 'attributes.relationships.owners.data', ''),
                    ],
                    'number_of_tracks' => Arr::get($item, 'attributes.numberOfItems'),
                    'image_uri'        => null,
                ];
            })->toArray();
    }

    public function getPlaylistTracks(string $playlistId): array
    {
        throw new \RuntimeException("Not implemented");
    }

    public function createPlaylist(string $name, array $tracks): string
    {
        $createPlaylistResponse = Http::withToken($this->oauthCredential->token)
            ->post(self::BASE_URL . '/playlists', [
                'data' => [
                    'type'       => 'playlists',
                    'attributes' => [
                        'name'        => $name,
                        'description' => '',
                        'privacy'     => 'public',
                    ]
                ]
            ]);

        if ($createPlaylistResponse->failed()) {
            Log::error('Tidal Playlist API Error', [
                'response' => $createPlaylistResponse->json(),
                'payload'  => [
                    'data' => [
                        'type'       => 'playlists',
                        'attributes' => [
                            'name'        => $name,
                            'description' => '',
                            'privacy'     => 'public',
                        ],
                    ],
                ],
            ]);
        }

        $createPlaylistJson = $createPlaylistResponse->json();

        return Arr::get($createPlaylistJson, 'data.id');
    }

    public function maybeRefreshToken(): void
    {
        if (now() < Carbon::parse($this->oauthCredential->expires_at)->subMinutes(2)) {
            return;
        }

        $response = Http::asForm()->post('https://auth.tidal.com/v1/oauth2/token', [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $this->oauthCredential->refresh_token,
            'client_id'     => Config::get("services.tidal.client_id"),
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

    public function searchTrack(Track $track): array
    {
        sleep(2);
        $response = Http::withToken($this->oauthCredential->token)
            ->get(
                self::BASE_URL . '/searchResults/' . $track->toSearchString(),
                ['countryCode' => 'US', 'include' => 'tracks']
            );

        $json = $response->json();
        $results = Arr::get($json, 'included');
        dump('collecting');
        return collect($results)->map(function ($result) use(&$i, $track) {
            return new Track([
                'source'    => self::PROVIDER,
                'remote_id' => Arr::get($result, 'id'),
                'name'      => Arr::get($result, 'attributes.title'),
            ]);
        })->reject(fn($a) => $a === null)->toArray();
    }

    public function addTrackToPlaylist(string $playlistId, Track $track): void
    {
        $payload = [[
            'id'   => $track->remote_id,
            'type' => 'tracks',
            'meta' => $track->meta,
        ]];
        $addToPlaylistResponse = Http::withToken($this->oauthCredential->token)
            ->post(self::BASE_URL . "/playlists/$playlistId/relationships/items", [
                'data' => $payload
            ]);

        if ($addToPlaylistResponse->failed()) {
            Log::error('Tidal Track API Error', [
                'response' => $addToPlaylistResponse->json(),
                'payload'  => [
                    'data' => $payload
                ],
            ]);
        }
    }
}
