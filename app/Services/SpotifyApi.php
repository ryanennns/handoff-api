<?php

namespace App\Services;

use App\Helpers\Track;
use App\Models\OauthCredential;
use Carbon\Carbon;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class SpotifyApi extends StreamingServiceApi
{
    public const PROVIDER = 'spotify';
    private const BASE_URL = 'https://api.spotify.com/v1';

    private OauthCredential $oauthCredential;

    public function __construct(OauthCredential $oauthCredential)
    {
        if ($oauthCredential->provider !== self::PROVIDER) {
            throw new InvalidArgumentException('Invalid provider. Expected "spotify".');
        }

        $this->oauthCredential = $oauthCredential;

        $response = Http::withToken($oauthCredential->token)->get(self::BASE_URL . '/me');
        if ($response->status() === 401) {
            $this->refreshToken();
        }
    }

    private function refreshToken(): void
    {
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

    private function makeRequest(string $endpoint, array $params = []): PromiseInterface|Response
    {
        if (Carbon::now()->diffInMinutes(Carbon::parse($this->oauthCredential->update_at)) > 60) {
            $this->refreshToken();
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
        $response = $this->makeRequest("/playlists/{$playlistId}/tracks");

        return collect(Arr::get($response->json(), 'items'))
            ->map(fn($item) => new Track([
                'source'    => self::PROVIDER,
                'remote_id' => Arr::get($item, 'track.id'),
                'name'      => Arr::get($item, 'track.name'),
                'artists'   => Arr::get($item, 'track.artists'),
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
        throw new RuntimeException('Not implemented');
    }
}
