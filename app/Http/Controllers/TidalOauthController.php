<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TidalService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Socialite\Facades\Socialite;

class TidalOauthController extends Controller
{
    public const PROVIDER = 'tidal';

    public function redirect(Request $request)
    {
        $redirectResponse = Socialite::driver(self::PROVIDER)->redirect();

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
            Cache::put("oauth:state:$state", $userId, now()->addMinutes(1));
        }

        return redirect($targetUrl);
    }


    public function callback(Request $request)
    {
        $provider = self::PROVIDER;
        // todo handle these guys
        if ($request->query('error')) {
            Log::error('Tidal API Error', [
                'error' => $request->query('error'),
            ]);
            return redirect('/api/dumping-ground')->withErrors('Error during TIDAL OAuth process: ' . $request->query('error'));
        }

        $state = $request->query('state');
        $userId = Cache::pull("oauth:state:$state");
        $code = $request->query('code');
        $codeVerifier = Cache::pull("oauth:tidal:state:$state");

        $response = Http::asForm()->post("https://auth.tidal.com/v1/oauth2/token", [
            'grant_type'    => 'authorization_code',
            'client_id'     => Config::get("services.{$provider}.client_id"),
            'code'          => $code,
            'redirect_uri'  => Config::get("services.{$provider}.redirect"),
            'code_verifier' => $codeVerifier,
        ]);

        if ($response->failed()) {
            Log::error('Tidal Token API Error', $response->json());
            return redirect('/api/dumping-ground')->withErrors(['error' => 'Failed to retrieve access token']);
        }

        $json = $response->json();
        $accessToken = Arr::get($json, 'access_token');
        $refreshToken = Arr::get($json, 'refresh_token');
        $expiresIn = Arr::get($json, 'expires_in');

        // todo get the country code off this and store it with the credentials
        $userResponse = Http::withToken($accessToken)->get(TidalService::BASE_URL . '/users/me');

        if ($userResponse->failed()) {
            Log::error('Tidal User API Error', $response->json());
            return redirect('/api/dumping-ground')->withErrors(['error' => 'Failed to retrieve user information']);
        }

        $userData = $userResponse->json();
        $tidalUserId = Arr::get($userData, 'data.id');
        $tidalEmail = Arr::get($userData, 'data.attributes.email');

        if (!$accessToken || !$refreshToken) {
            throw new \Exception("Missing tokens from Tidal OAuth response");
        }

        $user = User::query()->firstOrCreate(['id' => $userId]);
        $user->oauthCredentials()->updateOrCreate([
            'provider' => $provider,
        ], [
            'provider'      => $provider,
            'provider_id'   => $tidalUserId,
            'email'         => $tidalEmail,
            'token'         => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at'    => now()->addSeconds($expiresIn),
        ]);

        return redirect(Config::get('app.client_url') . '/dashboard');
    }
}
