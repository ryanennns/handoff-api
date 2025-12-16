<?php

namespace Feature\Controllers;

use App\Models\OauthCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DeleteOauthCredentialControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_requires_auth()
    {
        $this->deleteJson(route('oauth-credentials.delete'))
            ->assertUnauthorized();
    }

    public function test_it_rejects_invalid_service()
    {
        $this->actingAs($this->user())
            ->deleteJson(
                route(
                    'oauth-credentials.delete',
                    ['service' => 'invalid-service',]
                )
            )
            ->assertUnprocessable();
    }

    #[DataProvider('provideOauthService')]
    public function test_it_deletes_oauth_credential($provider)
    {
        $credential = $this->user()
            ->oauthCredentials()
            ->create(
                OauthCredential::factory()->raw(['provider' => $provider])
            );

        $this->actingAs($this->user())
            ->deleteJson(
                route(
                    'oauth-credentials.delete',
                    ['service' => $provider,]
                )
            )
            ->assertNoContent();

        $this->assertDatabaseMissing(OauthCredential::class, ['id' => $credential->getKey()]);
    }

    public static function provideOauthService(): array
    {
        return [
            'tidal'   => ['provider' => 'tidal'],
            'spotify' => ['provider' => 'spotify'],
        ];
    }
}
