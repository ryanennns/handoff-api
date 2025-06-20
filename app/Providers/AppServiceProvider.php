<?php

namespace App\Providers;

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
        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('spotify', SpotifyProvider::class);
            $event->extendSocialite('youtube', YouTubeProvider::class);
        });
    }
}
