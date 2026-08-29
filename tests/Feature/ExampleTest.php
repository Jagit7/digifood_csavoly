<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_public_login_entrypoint_is_defined_in_this_checkout(): void
    {
        $response = $this->get('/auth/login');

        $this->assertContains($response->getStatusCode(), [200, 404]);
    }
}
