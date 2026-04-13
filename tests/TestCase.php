<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Evita exigir `npm run build` em cada execução de testes (CI/local sem manifest).
        $this->withoutVite();
    }
}
