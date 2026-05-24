<?php

namespace Tests\Unit;

use App\Support\Pulse\PulseDatabaseFingerprint;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PulseDatabaseFingerprintTest extends TestCase
{
    #[Test]
    public function normaliza_sql_com_literais(): void
    {
        $a = PulseDatabaseFingerprint::fromSql('SELECT * FROM aluno WHERE id = 123');
        $b = PulseDatabaseFingerprint::fromSql('SELECT * FROM aluno WHERE id = 999');

        $this->assertSame($a['fingerprint'], $b['fingerprint']);
        $this->assertStringContainsString('?', $a['label']);
    }
}
