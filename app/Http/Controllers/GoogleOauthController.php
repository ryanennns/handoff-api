<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Facades\Socialite;

class GoogleOauthController extends Controller
{
    public function redirect(Request $request)
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request)
    {
        $socialiteUser = Socialite::driver('google')->user();

        $user = User::query()->updateOrCreate(
            ['email' => $socialiteUser->getEmail()],
            ['email' => $socialiteUser->getEmail()]
        );

        $user->oauthCredentials()->updateOrCreate([
            'provider' => 'google',
            'email'    => $socialiteUser->getEmail(),
        ], [
            'provider'      => 'google',
            'provider_id'   => $socialiteUser->getId(),
            'email'         => $socialiteUser->getEmail(),
            'token'         => $socialiteUser->token,
            'refresh_token' => $socialiteUser->refreshToken,
        ]);

        $user->tokens()->delete();
        return redirect(Config::get('app.client_url') . '/dashboard?token=' . $user->createToken('auth_token')->plainTextToken);
    }
}
