<?php
/**
 * Tests for the real LBFA_Multisite_Helper.
 */

declare(strict_types=1);

namespace LegalBlink\Tests\UnitReal;

use Brain\Monkey;
use Brain\Monkey\Functions;
use LBFA_Multisite_Helper;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

require_once dirname(__DIR__, 2) . '/classes/helper/class-lbfa-multisite-helper.php';

class MultisiteHelperTest extends TestCase
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

    public function testIsMultisiteDelegatesToWp(): void
    {
        Functions\when('is_multisite')->justReturn(true);
        $this->assertTrue(LBFA_Multisite_Helper::is_multisite());

        Functions\when('is_multisite')->justReturn(false);
        $this->assertFalse(LBFA_Multisite_Helper::is_multisite());
    }

    public function testIsNetworkAdminDelegatesToWp(): void
    {
        Functions\when('is_network_admin')->justReturn(true);
        $this->assertTrue(LBFA_Multisite_Helper::is_network_admin());
    }

    public function testGetCurrentBlogIdDelegatesToWp(): void
    {
        Functions\when('get_current_blog_id')->justReturn(7);
        $this->assertSame(7, LBFA_Multisite_Helper::get_current_blog_id());
    }

    public function testGetSitesReturnsFalseWhenNotMultisite(): void
    {
        Functions\when('is_multisite')->justReturn(false);
        $this->assertFalse(LBFA_Multisite_Helper::get_sites());
    }

    public function testGetSitesDelegatesWhenMultisite(): void
    {
        Functions\when('is_multisite')->justReturn(true);
        Functions\when('get_sites')->justReturn(['siteA', 'siteB']);

        $this->assertSame(['siteA', 'siteB'], LBFA_Multisite_Helper::get_sites());
    }

    public function testGetContextReturnsNetworkInRestWithCapability(): void
    {
        if (!defined('REST_REQUEST')) {
            define('REST_REQUEST', true);
        }
        Functions\when('current_user_can')->alias(static fn ($cap) => $cap === 'manage_network_options');
        // The other branches must not fire.
        Functions\when('is_network_admin')->justReturn(false);
        Functions\when('is_admin')->justReturn(false);

        $this->assertSame('network', LBFA_Multisite_Helper::get_context());
    }

    public function testGetContextReturnsNetworkInNetworkAdmin(): void
    {
        // REST_REQUEST already defined as true from previous test (constant
        // is process-global). To reach the is_network_admin branch we must
        // ensure current_user_can returns false here.
        Functions\when('current_user_can')->justReturn(false);
        Functions\when('is_network_admin')->justReturn(true);
        Functions\when('is_admin')->justReturn(false);

        $this->assertSame('network', LBFA_Multisite_Helper::get_context());
    }

    public function testGetContextReturnsSiteInAdminFallback(): void
    {
        Functions\when('current_user_can')->justReturn(false);
        Functions\when('is_network_admin')->justReturn(false);
        Functions\when('is_admin')->justReturn(true);

        $this->assertSame('site', LBFA_Multisite_Helper::get_context());
    }

    public function testGetContextReturnsFrontendByDefault(): void
    {
        Functions\when('current_user_can')->justReturn(false);
        Functions\when('is_network_admin')->justReturn(false);
        Functions\when('is_admin')->justReturn(false);

        $this->assertSame('frontend', LBFA_Multisite_Helper::get_context());
    }

    public function testIsNetworkContextForcedReturnsConstantValue(): void
    {
        $this->assertFalse(LBFA_Multisite_Helper::is_network_context_forced());

        if (!defined('LBFA_FORCE_NETWORK_CONTEXT')) {
            define('LBFA_FORCE_NETWORK_CONTEXT', true);
        }
        $this->assertTrue(LBFA_Multisite_Helper::is_network_context_forced());
    }
}
