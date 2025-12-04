<?php

namespace App\ApiClients;

use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class TidalApi extends Http
{
    public static int $tokens = 4;
    public static Carbon $lastRequestTime;

    public static function __callStatic($method, $args)
    {
        if (Config::get('app.env') === 'testing') {
            return parent::__callStatic($method, $args);
        }

        if (!isset(self::$lastRequestTime)) {
            self::$lastRequestTime = Carbon::now();
        }

        $tokensToAdd = floor(abs(Carbon::now()->diffInSeconds(self::$lastRequestTime)));
        self::$tokens += $tokensToAdd;

        if (self::$tokens < 0) {
            sleep(1);

            return parent::__callStatic($method, $args);
        }

        self::$tokens -= 1;

        self::$lastRequestTime = Carbon::now();

        return parent::__callStatic($method, $args);
    }
}
