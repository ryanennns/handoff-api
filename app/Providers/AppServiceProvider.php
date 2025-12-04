<?php

namespace App\Providers;

use App\Models\OauthCredential;
use App\Models\PlaylistTransfer;
use App\Observers\OauthCredentialObserver;
use App\Observers\PlaylistTransferObserver;
use App\Providers\Socialite\TidalProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Spotify\Provider as SpotifyProvider;
use SocialiteProviders\YouTube\Provider as YouTubeProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->afterResolving('socialite_local.subject_repository', function ($r) {
            $r->setUserCallback(function ($data) {
                return [
                    'id'       => $data['id'] ?? random_int(1000, 10000),
                    'sub'      => $data['id'],
                    'uuid'     => $data['uuid'] ?? vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4)),
                    'email'    => $email = $data['email'],
                    'username' => $data['username'] ?? $email,
                    'name'     => $data['name'] ?? mb_substr($email, 0, strpos($email, '@')) . '_name',
                ];
            });
        });
    }

    public function boot(): void
    {
        OauthCredential::observe(OauthCredentialObserver::class);
        PlaylistTransfer::observe(PlaylistTransferObserver::class);

        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('spotify', SpotifyProvider::class);
            $event->extendSocialite('youtube', YouTubeProvider::class);
            $event->extendSocialite('tidal', TidalProvider::class);
        });
    }
}
