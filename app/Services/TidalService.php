<?php

namespace App\Services;

use App\ApiClients\TidalApi;
use App\Helpers\Track;
use App\Models\OauthCredential;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class TidalService extends StreamingService
{
    public const string PROVIDER = 'tidal';
    public const string BASE_URL = 'https://openapi.tidal.com/v2';

    public function __construct(OauthCredential $oauthCredential)
    {
        parent::__construct($oauthCredential);

        $this->maybeRefreshToken();
    }

    public function maybeRefreshToken(): void
    {
        if (now() < Carbon::parse($this->oauthCredential->expires_at)->subMinutes(2)) {
            return;
        }

        $response = TidalApi::asForm()->post('https://auth.tidal.com/v1/oauth2/token', [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $this->oauthCredential->refresh_token,
            'client_id'     => Config::get("services.tidal.client_id"),
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Failed to refresh Tidal access token: ' . $response->body());
        }

        $data = $response->json();
        $accessToken = Arr::get($data, 'access_token');
        $expiresIn = Arr::get($data, 'expires_in');

        $this->oauthCredential->update([
            'token'      => $accessToken,
            'expires_at' => now()->addSeconds($expiresIn),
        ]);
    }

    public function getPlaylists(): array
    {
        $playlistResponse = TidalApi::withToken($this->oauthCredential->token)
            ->get(self::BASE_URL . '/playlists', [
                'countryCode'         => 'CA', // todo whatado about this :/
                'filter[r.owners.id]' => $this->oauthCredential->provider_id,
            ]);

        return collect(Arr::get($playlistResponse->json(), 'data', []))
            ->map(function ($item) {
                return [
                    'id'               => Arr::get($item, 'id'),
                    'name'             => Arr::get($item, 'attributes.name'),
                    'tracks'           => [],
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
        $tracksResponse = TidalApi::withToken($this->oauthCredential->token)
            ->get(self::BASE_URL . '/playlists/' . $playlistId . '/relationships/items', []);

        $tracksJson = $tracksResponse->json();
        return collect(Arr::get($tracksJson, 'data', []))
            ->map(function ($item) {
                $trackResponse = TidalApi::withToken($this->oauthCredential->token)
                    ->get(self::BASE_URL . "/tracks/{$item['id']}");
                $trackJson = $trackResponse->json();

                $artistResponse = TidalApi::withToken($this->oauthCredential->token)
                    ->get(self::BASE_URL . "/tracks/{$item['id']}/relationships/artists");

                $artistId = Arr::get($artistResponse->json(), 'data.0.id');
                $artistResponse = TidalApi::withToken($this->oauthCredential->token)
                    ->get(self::BASE_URL . "/artists/$artistId");

                $primaryArtistName = Arr::get($artistResponse->json(), 'data.attributes.name');

                return new Track([
                    'source'    => self::PROVIDER,
                    'remote_id' => Arr::get($trackJson, 'data.id'),
                    'name'      => Arr::get($trackJson, 'data.attributes.title'),
                    'artists'   => [$primaryArtistName]
                ]);
            })->toArray();
    }

    public function createPlaylist(string $name): string|false
    {
        $createPlaylistResponse = TidalApi::withToken($this->oauthCredential->token)
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
                'status'   => $createPlaylistResponse->status(),
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

            return false;
        }

        return Arr::get($createPlaylistResponse->json(), 'data.id');
    }

    public function addTrackToPlaylist(string $playlistId, Track $track): bool
    {

        $payload = [[
            'id'   => $track->remote_id,
            'type' => 'tracks',
            'meta' => $track->meta,
        ]];
        $addToPlaylistResponse = TidalApi::withToken($this->oauthCredential->token)
            ->post(self::BASE_URL . "/playlists/$playlistId/relationships/items", [
                'data' => $payload
            ]);

        if ($addToPlaylistResponse->failed()) {
            Log::error('Tidal Track API Error', [
                'status'   => $addToPlaylistResponse->status(),
                'response' => $addToPlaylistResponse->json(),
                'payload'  => [
                    'data' => $payload
                ],
            ]);
            return false;
        }

        return true;
    }

    public function searchTrack(Track $track): array
    {
        $response = TidalApi::withToken($this->oauthCredential->token)
            ->get(
                self::BASE_URL . '/searchResults/' . $track->toSearchString(),
                ['countryCode' => 'US', 'include' => 'tracks']
            );

        $results = Arr::get($response->json(), 'included');
        return collect($results)->map(fn($candidate) => new Track([
            'source'    => self::PROVIDER,
            'remote_id' => Arr::get($candidate, 'id'),
            'name'      => Arr::get($candidate, 'attributes.title'),
            'version'   => Arr::get($candidate, 'attributes.version'),
            'meta'      => [
                'primaryArtistLink' => Arr::get($candidate, 'relationships.artists.links.self'),
            ],
        ]))->reject(fn($a) => $a === null)->toArray();
    }

    public function fillMissingInfo(Track $track): Track
    {
        $primaryArtistLink = $track->meta['primaryArtistLink'];

        $response = TidalApi::withToken($this->oauthCredential->token)
            ->get(self::BASE_URL . $primaryArtistLink);

        $artistId = Arr::get($response->json(), 'data.0.id');
        $response = TidalApi::withToken($this->oauthCredential->token)
            ->get(self::BASE_URL . "/artists/$artistId");

        $track->artists = [Arr::get($response->json(), 'data.attributes.name')];

        return $track;
    }
}
