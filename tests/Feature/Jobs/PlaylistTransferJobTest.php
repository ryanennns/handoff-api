<?php

namespace Tests\Feature\Jobs;

use App\Helpers\Track;
use App\Jobs\PlaylistTransferJob;
use App\Models\OauthCredential;
use App\Models\PlaylistTransfer;
use App\Services\SpotifyService;
use App\Services\TidalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class PlaylistTransferJobTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        OauthCredential::query()->create([
            'id'            => (string)Str::uuid(),
            'provider'      => TidalService::PROVIDER,
            'provider_id'   => '1234567890',
            'email'         => 'example@gmail.com',
            'token'         => '$token',
            'refresh_token' => '$refreshToken',
            'expires_at'    => now()->addHour(),
            'user_id'       => $this->user()->getKey(),
        ]);
        OauthCredential::query()->create([
            'id'            => (string)Str::uuid(),
            'provider'      => SpotifyService::PROVIDER,
            'provider_id'   => '1234567890',
            'email'         => 'example@gmail.com',
            'token'         => '$token',
            'refresh_token' => '$refreshToken',
            'expires_at'    => now()->addHour(),
            'user_id'       => $this->user()->getKey(),
        ]);
    }

    public function test_it_snickers()
    {
        Http::fake([

        ]);
    }
}
