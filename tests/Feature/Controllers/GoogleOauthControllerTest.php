<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class GoogleOauthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_user_if_none_exists()
    {
        $this->markTestSkipped('Test code or tested code did not remove its own exception handlers');

        $mockGoogleProvider = Mockery::mock(GoogleProvider::class);
        $mockGoogleProvider->shouldReceive('user')->andReturn(new class {
            public string $token = 'mock_token';
            public string $refreshToken = 'mock_refresh_token';
            public int $expiresIn = 3600;

            public function getEmail(): string
            {
                return 'snickers@gmail.com';
            }

            public function getId(): string
            {
                return '1234567890';
            }
        });

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($mockGoogleProvider);

        $response = $this->get('/api/auth/callback/google');
        $response->assertStatus(302);

        $this->assertDatabaseHas('users', ['email' => 'snickers@gmail.com']);
        $user = User::query()
            ->where('email', 'snickers@gmail.com')
            ->firstOrFail();
        $this->assertDatabaseHas('oauth_credentials', [
            'provider'      => 'google',
            'email'         => 'snickers@gmail.com',
            'provider_id'   => '1234567890',
            'token'         => 'mock_token',
            'refresh_token' => 'mock_refresh_token',
            'user_id'       => $user->getKey(),
        ]);
    }
}
