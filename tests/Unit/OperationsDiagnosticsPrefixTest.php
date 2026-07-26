<?php

namespace Tests\Unit;

use App\Livewire\Pulse\OperationsDiagnosticsCard;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OperationsDiagnosticsPrefixTest extends TestCase
{
    #[Test]
    public function prefix_for_key_maps_product_areas(): void
    {
        $this->assertSame('analytics', OperationsDiagnosticsCard::prefixForKey('analytics:tab:inclusion|cid:1'));
        $this->assertSame('rx', OperationsDiagnosticsCard::prefixForKey('rx:overview'));
        $this->assertSame('map', OperationsDiagnosticsCard::prefixForKey('map:rx_snapshot|cache:miss'));
        $this->assertSame('clio', OperationsDiagnosticsCard::prefixForKey('clio:campaign:analyze'));
        $this->assertSame('clio', OperationsDiagnosticsCard::prefixForKey('clio:export:pdf'));
        $this->assertSame('cadunico', OperationsDiagnosticsCard::prefixForKey('cadunico:beneficios-portal'));
        $this->assertSame('horizonte', OperationsDiagnosticsCard::prefixForKey('horizonte:map:build|cache:miss'));
        $this->assertSame('http', OperationsDiagnosticsCard::prefixForKey('http:route:clio.campaigns.show|cid:1'));
    }
}
