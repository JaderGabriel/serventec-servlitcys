<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\Contact\ContactChannels;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ContactChannelsTest extends TestCase
{
    #[Test]
    public function from_user_inclui_email_obrigatorio_e_links_opcionais(): void
    {
        $user = new User([
            'email' => 'user@example.com',
            'phone' => '(11) 98888-7777',
            'whatsapp' => '11977776666',
        ]);

        $c = ContactChannels::fromUser($user);

        $this->assertTrue($c['available']);
        $this->assertSame('mailto:user@example.com', $c['email_href']);
        $this->assertSame('tel:+5511988887777', $c['phone_href']);
        $this->assertSame('https://wa.me/5511977776666', $c['whatsapp_href']);
    }

    #[Test]
    public function apenas_email_ainda_disponivel(): void
    {
        $c = ContactChannels::fromUser(new User(['email' => 'a@b.com']));

        $this->assertTrue($c['available']);
        $this->assertNull($c['phone_href']);
        $this->assertNotNull($c['email_href']);
    }
}
