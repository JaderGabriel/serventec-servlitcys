<?php

namespace Tests\Unit;

use App\Support\Horizonte\HorizonteLayout;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HorizonteLayoutTest extends TestCase
{
    #[Test]
    public function normalizes_preference_values(): void
    {
        $this->assertSame(HorizonteLayout::PREFERENCE_MOBILE, HorizonteLayout::normalizePreference('mobile'));
        $this->assertSame(HorizonteLayout::PREFERENCE_DESKTOP, HorizonteLayout::normalizePreference(' DESKTOP '));
        $this->assertSame(HorizonteLayout::PREFERENCE_AUTO, HorizonteLayout::normalizePreference('auto'));
        $this->assertSame(HorizonteLayout::PREFERENCE_AUTO, HorizonteLayout::normalizePreference('invalid'));
        $this->assertSame(HorizonteLayout::PREFERENCE_AUTO, HorizonteLayout::normalizePreference(null));
    }

    #[Test]
    public function detects_device_hint_from_user_agent(): void
    {
        $iphone = Request::create('/', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)',
        ]);
        $ipad = Request::create('/', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X)',
        ]);
        $androidPhone = Request::create('/', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Mobile Safari/537.36',
        ]);
        $desktop = Request::create('/', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
        ]);

        $this->assertSame('mobile', HorizonteLayout::deviceHint($iphone));
        $this->assertSame('tablet', HorizonteLayout::deviceHint($ipad));
        $this->assertSame('mobile', HorizonteLayout::deviceHint($androidPhone));
        $this->assertSame('desktop', HorizonteLayout::deviceHint($desktop));
    }

    #[Test]
    public function initial_preference_prefers_query_over_cookie(): void
    {
        $request = Request::create('/?layout=desktop', 'GET', ['layout' => 'desktop']);
        $request->cookies->set(HorizonteLayout::COOKIE_NAME, HorizonteLayout::PREFERENCE_MOBILE);

        $this->assertSame(HorizonteLayout::PREFERENCE_DESKTOP, HorizonteLayout::initialPreference($request));
    }

    #[Test]
    public function initial_preference_falls_back_to_cookie(): void
    {
        $request = Request::create('/', 'GET');
        $request->cookies->set(HorizonteLayout::COOKIE_NAME, HorizonteLayout::PREFERENCE_MOBILE);

        $this->assertSame(HorizonteLayout::PREFERENCE_MOBILE, HorizonteLayout::initialPreference($request));
    }

    #[Test]
    public function suggests_mobile_layout_for_phone_and_tablet(): void
    {
        $phone = Request::create('/', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)',
        ]);
        $tablet = Request::create('/', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X)',
        ]);
        $desktop = Request::create('/', 'GET', [], [], [], [
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);

        $this->assertTrue(HorizonteLayout::suggestsMobileLayout($phone));
        $this->assertTrue(HorizonteLayout::suggestsMobileLayout($tablet));
        $this->assertFalse(HorizonteLayout::suggestsMobileLayout($desktop));
    }
}
