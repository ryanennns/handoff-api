<?php

namespace App\Services;

use App\Helpers\Track;
use Carbon\Carbon;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class YouTubeService extends StreamingService
{
    public const PROVIDER = 'youtube';
    private const BASE_URL = 'https://www.googleapis.com/youtube/v3';

    public function maybeRefreshToken(): void
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

    private function ensureFreshToken(): void
    {
        if (abs(Carbon::now()->diffInMinutes(Carbon::parse($this->oauthCredential->updated_at))) > 60) {
            $this->maybeRefreshToken();
        }
    }

    private function httpClient(): PendingRequest
    {
        $this->ensureFreshToken();
        return Http::withToken($this->oauthCredential->token)->baseUrl(self::BASE_URL);
    }

    private function makeRequest(string $method, string $endpoint, array $params = []): PromiseInterface|Response
    {
        $client = $this->httpClient();

        $response = match (strtolower($method)) {
            'get' => $client->get($endpoint, $params),
            'post' => $client->post($endpoint, $params),
            default => throw new RuntimeException('Unsupported HTTP method'),
        };

        if ($response->failed()) {
            throw new RuntimeException('Failed to make request to YouTube API: ' . $response->body());
        }

        return $response;
    }

    public function getPlaylists(): array
    {
        $response = $this->makeRequest('get', '/playlists', [
            'mine'       => 'true',
            'part'       => 'id,snippet,contentDetails',
            'maxResults' => 50,
        ]);

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
        $response = $this->makeRequest('get', '/playlistItems', [
            'playlistId' => $playlistId,
            'part'       => 'snippet,contentDetails',
            'maxResults' => 50,
        ]);

        return collect(Arr::get($response->json(), 'items'))
            ->map(fn($item) => new Track([
                'source'    => self::PROVIDER,
                'remote_id' => Arr::get($item, 'contentDetails.videoId'),
                'name'      => Arr::get($item, 'snippet.title'),
                'artists'   => [str_replace(' - Topic', '', Arr::get($item, 'snippet.videoOwnerChannelTitle', ''))],
                'explicit'  => null,
                'album'     => [
                    'id'     => null,
                    'name'   => null,
                    'images' => [Arr::get($item, 'snippet.thumbnails.medium')],
                ],
            ]))->toArray();
    }

    public function createPlaylist(string $name, array $tracks): string
    {
        $createResponse = $this->makeRequest('post', '/playlists?part=snippet,status', [
            'snippet' => [
                'title'       => $name,
                'description' => 'Created via API',
            ],
            'status'  => [
                'privacyStatus' => 'private',
            ],
        ]);

        $youtubePlaylistId = Arr::get($createResponse->json(), 'id');
        $client = $this->httpClient();

        $youtubeTrackIds = [];
        $failedTracks = [];

        collect($tracks)->unique(fn($track) => $track->toSearchString())->each(function (Track $track) use ($client, &$youtubeTrackIds, &$failedTracks) {
            try {
                $searchResponse = $client->get('/search', [
                    'q'               => $track->toSearchString(),
                    'order'           => 'relevance',
                    'videoCategoryId' => 10,
                    'type'            => 'video',
                    'maxResults'      => 1,
                    'part'            => 'snippet',
                ]);

                $videoId = Arr::get($searchResponse->json(), 'items.0.id.videoId');

                if ($videoId) {
                    $youtubeTrackIds[] = $videoId;
                } else {
                    $failedTracks[] = $track;
                }
            } catch (\Throwable $e) {
                Log::warning('YouTube search failed', ['track' => $track->toSearchString(), 'error' => $e->getMessage()]);
                $failedTracks[] = $track;
            }

            usleep(200000); // 200ms delay
        });

        Log::info('Writing video IDs into playlist', [
            'playlistId'   => $youtubePlaylistId,
            'videoIds'     => $youtubeTrackIds,
            'failedTracks' => $failedTracks,
        ]);

        collect($youtubeTrackIds)
            ->filter()
            ->each(function ($videoId) use ($client, $youtubePlaylistId) {
                try {
                    $client->post('/playlistItems?part=snippet', [
                        'snippet' => [
                            'playlistId' => $youtubePlaylistId,
                            'resourceId' => [
                                'kind'    => 'youtube#video',
                                'videoId' => $videoId,
                            ],
                        ],
                    ]);
                    usleep(200000); // 200ms delay
                } catch (\Throwable $e) {
                    Log::warning('Failed to insert video into playlist', ['videoId' => $videoId, 'error' => $e->getMessage()]);
                }
            });

        return $youtubePlaylistId;
    }

    public function addTrackToPlaylist(string $playlistId, Track $track): void
    {
        // TODO: Implement addTrackToPlaylist() method.
    }

    public function searchTrack(Track $track): array
    {
        // TODO: Implement searchTrack() method.
        return [];
    }
}
