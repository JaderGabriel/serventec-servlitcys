<?php

namespace Tests\Unit;

use App\Services\Educacenso\EducacensoFileReader;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EducacensoFileReaderTest extends TestCase
{
    #[Test]
    public function reads_minimal_stage1_fixture_with_statistics(): void
    {
        $path = base_path('tests/fixtures/educacenso/stage1_2026_minimal.txt');
        $reader = new EducacensoFileReader;

        $result = $reader->read($path, 'stage1_2026_minimal.txt');

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['statistics']['schools']);
        $this->assertSame(1, $result['statistics']['matriculas']);
        $this->assertSame(1, $result['statistics']['turmas']);
        $this->assertSame(1, $result['statistics']['pessoas']);
        $this->assertSame(['00' => 1, '20' => 1, '30' => 1, '60' => 1], $result['statistics']['by_type']);
    }

    #[Test]
    public function fails_on_empty_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'edu_cen_');
        $this->assertNotFalse($path);
        file_put_contents($path, '');

        $reader = new EducacensoFileReader;
        $result = $reader->read($path, 'empty.txt');

        @unlink($path);

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['findings']);
    }

    #[Test]
    public function reads_load_test_fixture_within_time_budget(): void
    {
        $path = base_path('tests/fixtures/educacenso/stage1_2026_load_test.txt');
        if (! is_readable($path)) {
            $this->markTestSkipped('Fixture de carga ausente — execute tests/fixtures/educacenso/generate_load_test.php');
        }

        $reader = new EducacensoFileReader;
        $started = microtime(true);
        $result = $reader->read($path, 'stage1_2026_load_test.txt');
        $elapsedMs = (microtime(true) - $started) * 1000;

        $this->assertTrue($result['ok']);
        $this->assertSame(200, $result['statistics']['schools']);
        $this->assertSame(240_000, $result['statistics']['matriculas']);
        $this->assertLessThan(15_000, $elapsedMs, 'Parser excedeu 15s no fixture de carga');
    }
}
