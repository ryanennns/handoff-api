<?php

namespace App\Services;

use App\Helpers\TrackDto;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SpotifyService extends StreamingService
{
    public const PROVIDER = 'spotify';
    public const BASE_URL = 'https://api.spotify.com/v1';

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
        // todo i will regret this :)
        if (Config::get('app.env') === 'production') {
            $this->maybeRefreshToken();
        }


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
            ->map(fn($item) => new TrackDto([
                'source'    => self::PROVIDER,
                'remote_id' => Arr::get($item, 'track.uri'),
                'isrc'      => Arr::get($item, 'track.external_ids.isrc'),
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

    public function createPlaylist(string $name): string|false
    {
        $playlistCreationResponse = Http::withToken($this->oauthCredential->token)
            ->post(self::BASE_URL . '/me/playlists', [
                'name'        => $name,
                'description' => 'Created via Playlist Transfer',
                'public'      => false,
            ]);

        if ($playlistCreationResponse->failed()) {
            return false;
        }

        return Arr::get($playlistCreationResponse->json(), 'id');
    }

    public function searchTrack(TrackDto $track): array
    {
        $searchResponse = Http::withToken($this->oauthCredential->token)
            ->get(self::BASE_URL . '/search', [
                'type'  => 'track',
                'q'     => $track->artists[0] . ' ' . $track->name,
                'limit' => 1,
            ]);

        return collect(Arr::get($searchResponse->json(), 'tracks.items', []))
            ->map(fn($item) => new TrackDto([
                'source'    => self::PROVIDER,
                'remote_id' => Arr::get($item, 'uri'),
                'isrc'      => Arr::get($item, 'external_ids.isrc'),
                'name'      => Arr::get($item, 'name'),
                'artists'   => collect(Arr::get($item, 'artists'))
                    ->map(fn($artist) => $artist['name'])->toArray(),
                'explicit'  => Arr::get($item, 'explicit'),
                'album'     => [
                    'id'     => Arr::get($item, 'album.id'),
                    'name'   => Arr::get($item, 'album.name'),
                    'images' => Arr::get($item, 'album.images'),
                ]
            ]))->toArray();
    }

    public function addTrackToPlaylist(string $playlistId, TrackDto $track): bool
    {
        $response = Http::withToken($this->oauthCredential->token)
            ->post(self::BASE_URL . "/playlists/$playlistId/tracks", [
                'position' => 0,
                'uris'     => $track->remote_id,
            ]);

        return $response->successful();
    }

    public function addTracksToPlaylist(string $playlistId, array $tracks): void
    {
        collect($tracks)
            ->chunk(100)
            ->each(
                fn($tracksChunk) => Http::withToken($this->oauthCredential->token)
                    ->post(self::BASE_URL . "/playlists/$playlistId/tracks", [
                        'position' => 0,
                        'uris'     => collect($tracksChunk)->map(fn($track) => $track->remote_id)->toArray(),
                    ])
            );
    }

    public function fillMissingInfo(TrackDto $track): TrackDto
    {
        return $track;
    }
}
