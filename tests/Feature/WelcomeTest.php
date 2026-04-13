<?php

namespace Tests\Feature;

use Tests\TestCase;

class WelcomeTest extends TestCase
{
    public function test_welcome_page_loads(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(config('app.name'), false);
    }
}
