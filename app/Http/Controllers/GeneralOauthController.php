<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class GeneralOauthController extends Controller
{
    public const SCOPES = [
        'spotify' => [
            'playlist-modify-private',
            'playlist-modify-public',
            'playlist-read-private',
            'playlist-read-public',
        ],
    ];

    public function redirect(string $provider, Request $request)
    {
        $state = Crypt::encryptString(json_encode([
            'user_id' => $request->user()?->getKey(),
        ]));

        Log::info('state', ['state' => [
            'user_id' => $request->user()?->getKey(),
        ]]);

        return Socialite::driver($provider)
            ->scopes($scopes[$provider] ?? [])
            ->redirect();
    }

    public function callback(string $provider, Request $request)
    {
        $state = $request->input('state');
        $userId = json_decode(Crypt::decryptString($state))->user_id;

        $oauthUser = Socialite::driver($provider)
            ->stateless() // need to reconcile state to use more api scopes for spotify
            ->user();

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

        Auth::login($user);
        $user->tokens()->delete();
        return redirect('http://127.0.0.1:5173/dashboard?token=' . $user->createToken('auth_token')->plainTextToken);
    }
}
