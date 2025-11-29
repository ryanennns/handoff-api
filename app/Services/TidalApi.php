<?php

namespace App\Services;

use App\Helpers\Track;
use App\Models\OauthCredential;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TidalApi extends StreamingServiceApi
{
    public const PROVIDER = 'tidal';
    public const BASE_URL = 'https://openapi.tidal.com/v2';

    public function __construct(OauthCredential $oauthCredential)
    {
        parent::__construct($oauthCredential);

        Log::info('oauth cred', ['token' => $oauthCredential->token]);
    }

    public function getPlaylists(): array
    {
        $playlistResponse = Http::withToken($this->oauthCredential->token)
            ->get(self::BASE_URL . '/playlists', [
                'countryCode'         => 'CA',
                'filter[r.owners.id]' => $this->oauthCredential->provider_id,
            ]);

        $json = $playlistResponse->json();

        return collect(Arr::get($json, 'data', []))
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
        $remotePlaylistId = Arr::get($createPlaylistJson, 'data.id');

        collect($tracks)->each(function (Track $track) use ($remotePlaylistId) {
            sleep(2);
            $response = Http::withToken($this->oauthCredential->token)
                ->get(
                    self::BASE_URL . '/searchResults/' . $track->toSearchString(),
                    ['countryCode' => 'US', 'include' => 'tracks']
                );
            $json = $response->json();

            Log::info('track search info ' . $track->toSearchString(), [
                'response' => $json,
            ]);

            $remoteTrackId = Arr::get($json, 'included.0.id');
            $trackingUuid = Arr::get($json, 'data.attributes.trackingId');

            if (!$remoteTrackId) {
                return;
            }

            sleep(2);
            $addToPlaylistResponse = Http::withToken($this->oauthCredential->token)
                ->post(self::BASE_URL . "/playlists/$remotePlaylistId/relationships/items", [
                    'data' => [[
                        'id'   => $remoteTrackId,
                        'type' => 'tracks',
                        'meta' => [
                            'itemId' => $trackingUuid
                        ]
                    ]]
                ]);

            if ($addToPlaylistResponse->failed()) {
                Log::error('Tidal Track API Error', [
                    'response' => $response->json(),
                    'payload'  => [
                        'data' => [[
                            'id'   => $remoteTrackId,
                            'type' => 'tracks',
                            'meta' => [
                                'itemId' => $trackingUuid
                            ]
                        ]]
                    ],
                ]);
            }
        });

        return $remotePlaylistId;
    }

    public function maybeRefreshToken(): void
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
