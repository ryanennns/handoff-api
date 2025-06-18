<?php

namespace Tests\Feature\Controllers;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class RegisterControllerTest extends TestCase
{
    use DatabaseMigrations;

    public function test_it_registers_user()
    {
        $response = $this->postJson(route('auth.register'), [
            'name'                  => 'John Doe',
            'email'                 => 'asdf@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
            'device_name'           => 'test-device',
            'latitude'              => 52.5200,
            'longitude'             => 13.4050,
        ]);

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonStructure(['token']);
    }
}
