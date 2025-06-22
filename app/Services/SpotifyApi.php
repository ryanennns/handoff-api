<?php

namespace App\Services;

use App\Helpers\Track;
use Carbon\Carbon;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Broadcasting\Broadcasters\LogBroadcaster;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SpotifyApi extends StreamingServiceApi
{
    public const PROVIDER = 'spotify';
    private const BASE_URL = 'https://api.spotify.com/v1';

    public function maybeRefreshToken(): void
    {
        $meResponse = Http::withToken($this->oauthCredential->token)
            ->get(self::BASE_URL . '/me');

        if ($meResponse->getStatusCode() !== 200) {
            $response = Http::asForm()->post('https://accounts.spotify.com/api/token', [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $this->oauthCredential->refresh_token,
                'client_id'     => Config::get('services.spotify.client_id'),
                'client_secret' => COnfig::get('services.spotify.client_secret'),
            ]);

            if ($response->failed()) {
                throw new RuntimeException('Failed to refresh Spotify access token: ' . $response->body());
            }

            $accessToken = Arr::get($response->json(), 'access_token');
            $this->oauthCredential->update(['token' => $accessToken]);
        }
    }

    private function makeRequest(string $endpoint, array $params = []): PromiseInterface|Response
    {
        $this->maybeRefreshToken();

        $response = Http::withToken($this->oauthCredential->token)
            ->get(self::BASE_URL . $endpoint, $params);

        if ($response->failed()) {
            throw new RuntimeException('Failed to make request to Spotify API: ' . $response->body());
        }

        return $response;
    }

    public function getPlaylists(): array
    {
        $response = $this->makeRequest('/me/playlists');

        return collect(Arr::get($response->json(), 'items'))
            ->map(fn($item) => [
                'id'               => Arr::get($item, 'id'),
                'name'             => Arr::get($item, 'name'),
                'tracks'           => Arr::get($item, 'tracks.href'),
                'owner'            => [
                    'display_name' => Arr::get($item, 'owner.display_name'),
                    'id'           => Arr::get($item, 'owner.id'),
                ],
                'number_of_tracks' => Arr::get($item, 'tracks.total'),
                'image_uri'        => Arr::get($item, 'images.0.url'),
            ])->toArray();
    }

    public function getPlaylistTracks(string $playlistId): array
    {
        $response = $this->makeRequest("/playlists/$playlistId/tracks");

        return collect(Arr::get($response->json(), 'items'))
            ->map(fn($item) => new Track([
                'source'    => self::PROVIDER,
                'remote_id' => Arr::get($item, 'track.uri'),
                'name'      => Arr::get($item, 'track.name'),
                'artists'   => collect(Arr::get($item, 'track.artists'))
                    ->map(fn($artist) => $artist['name'])->toArray(),
                'explicit'  => Arr::get($item, 'track.explicit'),
                'album'     => [
                    'id'     => Arr::get($item, 'track.album.id'),
                    'name'   => Arr::get($item, 'track.album.name'),
                    'images' => Arr::get($item, 'track.album.images'),
                ]
            ]))->toArray();
    }

    public function createPlaylist(string $name, array $tracks): string
    {
        $playlistCreationResponse = Http::withToken($this->oauthCredential->token)
            ->post(self::BASE_URL . '/me/playlists', [
                'name'        => $name,
                'description' => 'Created via Playlist Transfer',
                'public'      => false,
            ]);

        $playlistCreationJson = $playlistCreationResponse->json();
        $playlistId = Arr::get($playlistCreationJson, 'id');
        $playlistUrl = Arr::get($playlistCreationJson, 'external_urls.spotify');

        $tracksToAddToPlaylist = [];
        $errorTracks = [];

        // collecting the best match for the given songs
        collect($tracks)->each(function ($track) use ($playlistId, &$tracksToAddToPlaylist, &$errorTracks) {
            $searchResponse = Http::withToken($this->oauthCredential->token)
                ->get(self::BASE_URL . '/search', [
                    'type'  => 'track',
                    'q'     => $track->artists[0] . ' ' . $track->name,
                    'limit' => 1,
                ]);

            $searchResponseJson = $searchResponse->json();
            $trackUri = Arr::get($searchResponseJson, 'tracks.items.0.uri');

            if ($trackUri === null) {
                $errorTracks[] = $track;
                return;
            }

            $tracksToAddToPlaylist[] = $trackUri;
        });

        // taking the found song IDs and returning
        collect($tracksToAddToPlaylist)->chunk(100)->each(function ($tracksChunk) use ($playlistId) {
            Http::withToken($this->oauthCredential->token)
                ->post(self::BASE_URL . "/playlists/$playlistId/tracks", [
                    'position' => 0,
                    'uris'     => $tracksChunk,
                ]);
        });

        return $playlistUrl;
    }
}
