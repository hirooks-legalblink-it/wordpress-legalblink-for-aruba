<?php
/**
 * Placeholder test to make the UnitReal testsuite executable while the real
 * helper tests (Config/Option/Transient/Multisite/Language/Logger) are
 * implemented in a follow-up commit. Will be removed in commit #5.
 */

declare(strict_types=1);

namespace LegalBlink\Tests\UnitReal;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class PlaceholderTest extends TestCase
{
    public function testRealTestsuiteIsBootstrappedWithoutStubs(): void
    {
        // The Stubs.php file is only included when LBFA_USE_STUBS=1.
        // In UnitReal mode (LBFA_USE_STUBS=0), the LBFA helper classes
        // must NOT be present yet — tests will require them explicitly.
        $this->assertFalse(getenv('LBFA_USE_STUBS') === '1' || getenv('LBFA_USE_STUBS') === false && false);
        $this->assertSame('0', (string) getenv('LBFA_USE_STUBS'));
    }
}
