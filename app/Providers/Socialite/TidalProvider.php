<?php

namespace App\Providers\Socialite;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use SocialiteProviders\Manager\Contracts\OAuth2\ProviderInterface;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User;

class TidalProvider extends AbstractProvider implements ProviderInterface
{
    protected function getAuthUrl($state): string
    {
        $clientId = Config::get('services.tidal.client_id');
        $redirectUri = Config::get('services.tidal.redirect');

        $random = bin2hex(openssl_random_pseudo_bytes(32));
        $verifier = $this->base64urlEncode(pack('H*', $random));
        $codeChallenge = $this->base64urlEncode(pack('H*', hash('sha256', $verifier)));

        Cache::put("oauth:tidal:state:$state", $verifier);
        $query = http_build_query([
            'response_type'         => 'code',
            'client_id'             => $clientId,
            'redirect_uri'          => $redirectUri,
            'code_challenge_method' => 'S256',
            'code_challenge'        => $codeChallenge,
            'state'                 => $state,
            'scope'                => 'playlists.read playlists.write',
        ]);

        return "https://login.tidal.com/authorize?{$query}";
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

    private function base64urlEncode($plainText): string
    {
        $base64 = base64_encode($plainText);
        $base64 = trim($base64, "=");
        return strtr($base64, '+/', '-_');
    }
}
