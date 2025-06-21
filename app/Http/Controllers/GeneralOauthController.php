<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Socialite\Facades\Socialite;

class GeneralOauthController extends Controller
{
    public const SCOPES = [
        'spotify' => [
            'playlist-modify-private',
            'playlist-modify-public',
        ],
    ];

    public function redirect(string $provider, Request $request)
    {
        $scopes = self::SCOPES[$provider] ?? [];

        $redirectResponse = Socialite::driver($provider)
            ->scopes($scopes)
            ->redirect();

        $targetUrl = $redirectResponse->getTargetUrl();

        $parsed = parse_url($targetUrl);
        parse_str($parsed['query'] ?? '', $query);
        $state = $query['state'] ?? null;

        $token = $request->query('token');
        $userId = PersonalAccessToken::findToken($token)->tokenable->getKey();

        if (!$userId) {
            throw new \Exception('User ID not found for token');
        }

        if ($state) {
            Cache::put("oauth:state:{$state}", $userId, now()->addMinutes(1));
        }

        return redirect($targetUrl);
    }


    public function callback(string $provider, Request $request)
    {
        $state = $request->query('state');
        $userId = Cache::pull("oauth:state:$state");

        $oauthUser = Socialite::driver($provider)->user();

        $user = User::query()->firstOrCreate(['id' => $userId]);
        $user->oauthCredentials()->updateOrCreate([
            'provider' => $provider,
            'email'    => $oauthUser->getEmail(),
        ], [
            'provider'      => $provider,
            'provider_id'   => $oauthUser->getId(),
            'email'         => $oauthUser->getEmail(),
            'token'         => $oauthUser->token,
            'refresh_token' => $oauthUser->refreshToken,
        ]);

        return redirect('http://127.0.0.1:5173/dashboard');
    }
}
