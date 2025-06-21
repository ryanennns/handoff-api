<?php

namespace App\Providers\Socialite;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use SocialiteProviders\Manager\Contracts\OAuth2\ProviderInterface;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User;

class TidalProvider extends AbstractProvider implements ProviderInterface
{
    protected function getAuthUrl($state): string
    {
        $code = Str::random(32);
        $clientId = Config::get('services.tidal.client_id');
        $clientSecret = Config::get('services.tidal.client_secret');
        $clientRedirectUri = Config::get('services.tidal.redirect');
        $query = http_build_query([
            'response_type' => 'code',
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri'  => $clientRedirectUri,
            'code_challenge_method' => hash('SHA256', $code),
        ]);

        return $this->buildAuthUrlFromBase(
            "https://login.tidal.com/authorize?{$query}",
            $state
        );
    }

    protected function getTokenUrl(): string
    {
        return "https://auth.tidal.com/v1/oauth2/token";
    }

    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->get(
            'https://openapi.tidal.com/v2/users/me',
            [
                'headers' => [
                    'Accept'        => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]
        );

        return json_decode($response->getBody()->getContents(), true);
    }

    protected function mapUserToObject(array $user)
    {
        $attributes = $user['data']['attributes'] ?? [];

        return (new User())->setRaw($user)->map([
            'id'         => $user['data']['id'] ?? null,
            'nickname'   => $attributes['username'] ?? null,
            'name'       => trim(($attributes['firstName'] ?? '') . ' ' . ($attributes['lastName'] ?? '')),
            'email'      => $attributes['email'] ?? null,
            'verified'   => $attributes['emailVerified'] ?? null,
            'country'    => $attributes['country'] ?? null,
            'public_key' => $attributes['nostrPublicKey'] ?? null,
        ]);
    }
}
