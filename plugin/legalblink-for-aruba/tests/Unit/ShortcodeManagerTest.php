<?php
/**
 * Unit tests for LBFA_Shortcode_Manager.
 *
 * Validates the singleton + init_shortcodes that constructs the four
 * concrete shortcode classes (each register itself via add_shortcode at
 * construction time).
 */

declare(strict_types=1);

namespace LegalBlink\Tests\Unit;

use Brain\Monkey\Functions;
use LBFA_Shortcode_Manager;
use LegalBlink\Tests\TestCase;
use Mockery;
use ReflectionClass;

require_once dirname(__DIR__, 2) . '/classes/shortcode/class-lbfa-base-shortcode.php';
require_once dirname(__DIR__, 2) . '/classes/shortcode/class-lbfa-cookie-policy-shortcode.php';
require_once dirname(__DIR__, 2) . '/classes/shortcode/class-lbfa-privacy-policy-shortcode.php';
require_once dirname(__DIR__, 2) . '/classes/shortcode/class-lbfa-cgv-policy-shortcode.php';
require_once dirname(__DIR__, 2) . '/classes/shortcode/class-lbfa-shortcode-manager.php';

class ShortcodeManagerTest extends TestCase
{
    protected function set_up(): void
    {
        parent::set_up();

        Functions\when('__')->returnArg(1);
    }

    protected function tear_down(): void
    {
        // Reset the singleton via reflection so each test starts fresh.
        $reflect = new ReflectionClass(LBFA_Shortcode_Manager::class);
        $prop = $reflect->getProperty('instance');
        $prop->setAccessible(true);
        // Two-arg form (PHP 8.3+) — single-arg static setValue is deprecated.
        $prop->setValue(null, null);

        Mockery::close();
        parent::tear_down();
    }

    public function testGetInstanceReturnsSingleton(): void
    {
        Functions\when('add_shortcode')->justReturn(true);

        $a = LBFA_Shortcode_Manager::get_instance();
        $b = LBFA_Shortcode_Manager::get_instance();

        $this->assertSame($a, $b);
    }

    public function testInitShortcodesRegistersFourConcreteShortcodes(): void
    {
        // The manager calls `new <ClassName>` for each entry, and each
        // constructor calls add_shortcode($tag, [...]). The accessibility
        // declaration class is auto-registered too because it lives under
        // classes/ and is autoloaded by composer (the manager wraps the
        // require_once in a class_exists guard so missing-file paths never
        // matter).
        Functions\expect('add_shortcode')
            ->times(4)
            ->with(
                Mockery::anyOf(
                    'LBFA_COOKIE_POLICY',
                    'LBFA_PRIVACY_POLICY',
                    'LBFA_CGV_POLICY',
                    'LBFA_ACCESSIBILITY_DECLARATION'
                ),
                Mockery::type('array')
            );

        LBFA_Shortcode_Manager::get_instance();
        $this->addToAssertionCount(1);
    }
}
