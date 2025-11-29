<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    private User $user;

    public function user()
    {
        if (!isset($this->user)) {
            $this->user = User::factory()->create();
        }

        return $this->user;
    }
}
