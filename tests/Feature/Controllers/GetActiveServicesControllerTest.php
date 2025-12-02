<?php

namespace Tests\Feature\Controllers;

use App\Models\OauthCredential;
use App\Models\User;
use App\Services\TidalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GetActiveServicesControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_gets_all_active_services_for_user()
    {
        $user = User::factory()->create();
        OauthCredential::query()->create([
            'user_id'     => $user->getKey(),
            'provider'    => 'tidal',
            'provider_id' => 'tidal',
        ]);
        OauthCredential::query()->create([
            'user_id'     => $user->getKey(),
            'provider'    => 'google',
            'provider_id' => 'google',
        ]);
        OauthCredential::query()->create([
            'user_id'     => $user->getKey(),
            'provider'    => 'spotify',
            'provider_id' => 'spotify',
        ]);

        $this->actingAs($user)
            ->get('api/services')
            ->assertStatus(200)
            ->assertJson([
                'services' => [
                    'tidal',
                    'google',
                    'spotify',
                ]
            ]);
    }
}
