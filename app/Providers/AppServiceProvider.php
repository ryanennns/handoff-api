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
        //
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
