<?php

namespace App\Providers\Socialite;

use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User;

class TidalProvider extends AbstractProvider
{
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase(
            "https://auth.tidal.com/v1/oauth2/token",
            $state
        );
    }

    protected function getTokenUrl()
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
