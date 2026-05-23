<?php

namespace Tests\Unit;

use App\Models\City;
use App\Support\City\CityReferenceContact;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CityReferenceContactTest extends TestCase
{
    #[Test]
    public function monta_links_de_telefone_whatsapp_e_email(): void
    {
        $city = new City([
            'contact_name' => 'Ana Gestora',
            'contact_phone' => '(11) 98888-7777',
            'contact_whatsapp' => '11977776666',
            'contact_email' => 'ANA@MUNICIPIO.GOV.BR',
        ]);

        $c = CityReferenceContact::from($city);

        $this->assertTrue($c['available']);
        $this->assertSame('Ana Gestora', $c['name']);
        $this->assertSame('tel:+5511988887777', $c['phone_href']);
        $this->assertSame('https://wa.me/5511977776666', $c['whatsapp_href']);
        $this->assertSame('mailto:ana@municipio.gov.br', $c['email_href']);
    }

    #[Test]
    public function vazio_quando_sem_dados(): void
    {
        $c = CityReferenceContact::from(new City());

        $this->assertFalse($c['available']);
        $this->assertNull($c['phone_href']);
    }
}
