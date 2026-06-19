<?php
/**
 * Shared base class for plugin unit tests.
 *
 * Wires Brain\Monkey setUp/tearDown around each test so WordPress core
 * functions can be mocked safely without booting WP.
 */

declare(strict_types=1);

namespace LegalBlink\Tests;

use Brain\Monkey;
use Yoast\PHPUnitPolyfills\TestCases\TestCase as PolyfillTestCase;

abstract class TestCase extends PolyfillTestCase
{
    protected function set_up(): void
    {
        parent::set_up();
        Monkey\setUp();
    }

    protected function tear_down(): void
    {
        Monkey\tearDown();
        parent::tear_down();
    }
}
