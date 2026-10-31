<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

abstract class IsolatedTestCase extends TestCase
{
    protected TestEnvironment $env;

    protected function setUp(): void
    {
        parent::setUp();
        $this->env = new TestEnvironment();
    }

    protected function tearDown(): void
    {
        try {
            $this->env->close();
        } finally {
            parent::tearDown();
        }
    }
}
