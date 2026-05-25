<?php

namespace Tests\Feature;

use Tests\TestCase;

class PrivacyPolicyTest extends TestCase
{
    public function test_privacy_policy_page_is_public_and_loads(): void
    {
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee(__('Política de privacidade'), false)
            ->assertSee(__('LGPD'), false);
    }
}
