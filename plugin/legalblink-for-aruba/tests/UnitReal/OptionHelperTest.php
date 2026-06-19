<?php
/**
 * Tests for the real LBFA_Option_Helper.
 */

declare(strict_types=1);

namespace LegalBlink\Tests\UnitReal;

use Brain\Monkey;
use Brain\Monkey\Functions;
use LBFA_Option_Helper;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

require_once dirname(__DIR__, 2) . '/classes/helper/class-lbfa-multisite-helper.php';
require_once dirname(__DIR__, 2) . '/classes/helper/class-lbfa-option-helper.php';

class OptionHelperTest extends TestCase
{
    protected function set_up(): void
    {
        parent::set_up();
        Monkey\setUp();

        // Default to single-site context — tests can override.
        Functions\when('is_multisite')->justReturn(false);
        Functions\when('is_network_admin')->justReturn(false);
        Functions\when('is_admin')->justReturn(false);
        Functions\when('current_user_can')->justReturn(false);
    }

    protected function tear_down(): void
    {
        Monkey\tearDown();
        parent::tear_down();
    }

    public function testGetOptionUsesGetOptionInSiteContext(): void
    {
        Functions\expect('get_option')
            ->once()
            ->with('lbfa_some_key', 'fallback')
            ->andReturn('value');

        $this->assertSame('value', LBFA_Option_Helper::getOption('some_key', 'fallback'));
    }

    public function testGetOptionUsesGetSiteOptionInNetworkContext(): void
    {
        Functions\when('is_multisite')->justReturn(true);
        Functions\when('is_network_admin')->justReturn(true);

        Functions\expect('get_site_option')
            ->once()
            ->with('lbfa_net_key', null)
            ->andReturn('netvalue');
        Functions\expect('get_option')->never();

        $this->assertSame('netvalue', LBFA_Option_Helper::getOption('net_key'));
    }

    public function testSetOptionDelegatesToUpdateOption(): void
    {
        Functions\expect('update_option')
            ->once()
            ->with('lbfa_a', 'b')
            ->andReturn(true);

        $this->assertTrue(LBFA_Option_Helper::setOption('a', 'b'));
    }

    public function testSetOptionDelegatesToUpdateSiteOptionInNetwork(): void
    {
        Functions\when('is_multisite')->justReturn(true);
        Functions\when('is_network_admin')->justReturn(true);

        Functions\expect('update_site_option')
            ->once()
            ->with('lbfa_a', 'b')
            ->andReturn(true);
        Functions\expect('update_option')->never();

        LBFA_Option_Helper::setOption('a', 'b');
        $this->addToAssertionCount(1);
    }

    public function testDeleteOptionDelegates(): void
    {
        Functions\expect('delete_option')
            ->once()
            ->with('lbfa_x')
            ->andReturn(true);

        $this->assertTrue(LBFA_Option_Helper::deleteOption('x'));
    }

    public function testGetLanguageOptionAppendsLanguageSuffix(): void
    {
        Functions\expect('get_option')
            ->once()
            ->with('lbfa_documents_html_url_it', '')
            ->andReturn('https://x/it.html');

        $this->assertSame('https://x/it.html', LBFA_Option_Helper::getLanguageOption('documents_html_url', 'it'));
    }

    public function testSetLanguageOptionAppendsLanguageSuffix(): void
    {
        Functions\expect('update_option')
            ->once()
            ->with('lbfa_documents_html_url_en', 'https://x/en.html')
            ->andReturn(true);

        LBFA_Option_Helper::setLanguageOption('documents_html_url', 'https://x/en.html', 'en');
        $this->addToAssertionCount(1);
    }

    public function testLanguageTimestampHelpers(): void
    {
        Functions\expect('get_option')
            ->once()
            ->with('lbfa_cookie_codes_updated_it', '')
            ->andReturn('2026-01-01');
        Functions\expect('update_option')
            ->once()
            ->with('lbfa_privacy_codes_updated_en', '2026-04-01');

        $this->assertSame('2026-01-01', LBFA_Option_Helper::getLanguageTimestamp('cookie', 'it'));
        LBFA_Option_Helper::setLanguageTimestamp('privacy', 'en', '2026-04-01');
    }
}
