<?php

namespace Tests\Unit;

use App\Models\User;
use App\Policies\PlatformFeaturePolicy;
use Tests\TestCase;

class PlatformFeaturePolicyTest extends TestCase
{
    public function test_admin_has_platform_capabilities(): void
    {
        $admin = User::factory()->admin()->make();
        $policy = new PlatformFeaturePolicy;

        $this->assertTrue($policy->importOrConfigure($admin));
        $this->assertTrue($policy->viewHorizonte($admin));
        $this->assertTrue($policy->exportAnalyticsPdf($admin));
    }

    public function test_municipal_has_limited_capabilities(): void
    {
        $user = User::factory()->municipal()->make();
        $policy = new PlatformFeaturePolicy;

        $this->assertFalse($policy->importOrConfigure($user));
        $this->assertFalse($policy->viewHorizonte($user));
        $this->assertTrue($policy->exportInclusionNee($user));
        $this->assertTrue($policy->viewSyncQueue($user));
    }

    public function test_user_model_delegates_to_policy(): void
    {
        $admin = User::factory()->admin()->make();

        $this->assertTrue($admin->canViewHorizonte());
        $this->assertTrue($admin->canImportOrConfigure());
    }
}
