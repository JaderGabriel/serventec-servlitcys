<?php

namespace Tests\Unit;

use App\Support\Filesystem\ContainedPathResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ContainedPathResolverTest extends TestCase
{
    #[Test]
    public function rejeita_path_traversal_fora_do_root(): void
    {
        $root = storage_path('app/cadunico-test-'.uniqid('', true));
        mkdir($root, 0755, true);
        file_put_contents($root.'/ok.csv', "ibge;ano\n");

        $resolved = ContainedPathResolver::resolveReadableFile('../composer.json', [$root]);

        $this->assertNull($resolved);
        @unlink($root.'/ok.csv');
        @rmdir($root);
    }

    #[Test]
    public function aceita_ficheiro_dentro_do_root(): void
    {
        $root = storage_path('app/cadunico-test-'.uniqid('', true));
        mkdir($root, 0755, true);
        file_put_contents($root.'/ok.csv', "ibge;ano\n");

        $resolved = ContainedPathResolver::resolveReadableFile('ok.csv', [$root]);

        $this->assertNotNull($resolved);
        $this->assertStringEndsWith('ok.csv', $resolved ?? '');
        @unlink($root.'/ok.csv');
        @rmdir($root);
    }
}
