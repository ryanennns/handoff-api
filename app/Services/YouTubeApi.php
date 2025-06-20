<?php

namespace App\Services;

use App\Models\OauthCredential;
use Carbon\Carbon;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class YouTubeApi extends StreamingServiceApi
{
    public const PROVIDER = 'youtube';
    private const BASE_URL = 'https://www.googleapis.com/youtube/v3';

    private OauthCredential $oauthCredential;

    public function __construct(OauthCredential $oauthCredential)
    {
        if ($oauthCredential->provider !== self::PROVIDER) {
            throw new InvalidArgumentException('Invalid provider. Expected "youtube".');
        }

        $this->oauthCredential = $oauthCredential;

        $response = Http::withToken($oauthCredential->token)->get(self::BASE_URL . '/channels', [
            'mine' => 'true',
            'part' => 'id',
        ]);

        if ($response->status() === 401) {
            $this->refreshToken();
        }
    }

    private function refreshToken(): void
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $this->oauthCredential->refresh_token,
            'client_id'     => Config::get('services.google.client_id'),
            'client_secret' => Config::get('services.google.client_secret'),
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Failed to refresh YouTube access token: ' . $response->body());
        }

        $accessToken = Arr::get($response->json(), 'access_token');
        $this->oauthCredential->update(['token' => $accessToken]);
    }

    private function makeRequest(string $endpoint, array $params = []): PromiseInterface|Response
    {
        if (Carbon::now()->diffInMinutes(Carbon::parse($this->oauthCredential->updated_at)) > 60) {
            $this->refreshToken();
        }

        $response = Http::withToken($this->oauthCredential->token)
            ->get(self::BASE_URL . $endpoint, $params);

        if ($response->failed()) {
            throw new RuntimeException('Failed to make request to YouTube API: ' . $response->body());
        }

        return $response;
    }

    public function getPlaylists(): array
    {
        $response = $this->makeRequest('/playlists', [
            'mine' => 'true',
            'part' => 'id,snippet,contentDetails',
            'maxResults' => 50,
        ]);

        dd($response);

        return collect(Arr::get($response->json(), 'items'))->map(fn($item) => [
            'id'               => Arr::get($item, 'id'),
            'name'             => Arr::get($item, 'snippet.title'),
            'tracks'           => null,
            'owner'            => [
                'display_name' => Arr::get($item, 'snippet.channelTitle'),
                'id'           => Arr::get($item, 'snippet.channelId'),
            ],
            'number_of_tracks' => Arr::get($item, 'contentDetails.itemCount'),
            'image_uri'        => Arr::get($item, 'snippet.thumbnails.medium.url'),
        ])->toArray();
    }

    public function getPlaylistTracks(string $playlistId): array
    {
        $response = $this->makeRequest('/playlistItems', [
            'playlistId' => $playlistId,
            'part'       => 'snippet,contentDetails',
            'maxResults' => 50,
        ]);

        return collect(Arr::get($response->json(), 'items'))->map(fn($item) => [
            'id'       => Arr::get($item, 'contentDetails.videoId'),
            'name'     => Arr::get($item, 'snippet.title'),
            'artists'  => null,
            'explicit' => null,
            'album'    => [
                'id'     => null,
                'name'   => null,
                'images' => [Arr::get($item, 'snippet.thumbnails.medium')],
            ],
        ])->toArray();
    }
}
