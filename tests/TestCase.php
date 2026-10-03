<?php

namespace Tests;

use App\Modules\DeveloperPlatform\Support\WebhookUrlGuard;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // No real DNS in tests: every hostname resolves to a public address unless a test overrides it.
        WebhookUrlGuard::$resolver = fn (string $host) => ['93.184.216.34'];
    }
}
