<?php
/**
 * Unit tests for the four concrete shortcode classes.
 *
 * Verifies tag and policy_type wiring, plus that generate_output delegates
 * to generate_common_output (asserted indirectly: when no URL is configured
 * the output contains the shortcode-specific not-configured notice).
 */

declare(strict_types=1);

namespace LegalBlink\Tests\Unit;

use Brain\Monkey\Functions;
use LBFA_Accessibility_Declaration_Shortcode;
use LBFA_CGV_Policy_Shortcode;
use LBFA_Cookie_Policy_Shortcode;
use LBFA_Option_Helper;
use LBFA_Privacy_Policy_Shortcode;
use LBFA_Transient_Helper;
use LegalBlink\Tests\TestCase;
use Mockery;
use ReflectionObject;

require_once dirname(__DIR__, 2) . '/classes/shortcode/class-lbfa-base-shortcode.php';
require_once dirname(__DIR__, 2) . '/classes/shortcode/class-lbfa-cookie-policy-shortcode.php';
require_once dirname(__DIR__, 2) . '/classes/shortcode/class-lbfa-privacy-policy-shortcode.php';
require_once dirname(__DIR__, 2) . '/classes/shortcode/class-lbfa-cgv-policy-shortcode.php';
require_once dirname(__DIR__, 2) . '/classes/shortcode/class-lbfa-accessibility-declaration-shortcode.php';

class ConcreteShortcodesTest extends TestCase
{
    protected function set_up(): void
    {
        parent::set_up();

        LBFA_Option_Helper::reset();
        LBFA_Transient_Helper::reset();

        Functions\when('__')->returnArg(1);
        // add_shortcode is intentionally not stubbed here: the test
        // `testEachShortcodeRegistersWithAddShortcodeOnConstruction` sets up
        // an `expect()` on it. Other tests stub it inline with
        // `Functions\when('add_shortcode')->justReturn(true)`.
        Functions\when('shortcode_atts')->alias(static fn ($defaults, $attrs) => array_merge($defaults, (array) $attrs));
        Functions\when('sanitize_text_field')->returnArg(1);
        Functions\when('esc_attr')->returnArg(1);
        Functions\when('esc_html')->returnArg(1);
        Functions\when('esc_url')->returnArg(1);
    }

    protected function tear_down(): void
    {
        Mockery::close();
        parent::tear_down();
    }

    private function readProtected(object $obj, string $name)
    {
        $rp = new ReflectionObject($obj);
        $prop = $rp->getProperty($name);
        $prop->setAccessible(true);
        return $prop->getValue($obj);
    }

    public function testCookiePolicyShortcodeWiresTagAndPolicyType(): void
    {
        Functions\when('add_shortcode')->justReturn(true);
        $sc = new LBFA_Cookie_Policy_Shortcode();
        $this->assertSame('LBFA_COOKIE_POLICY', $this->readProtected($sc, 'tag'));
        $this->assertSame('cookie_policy', $this->readProtected($sc, 'policy_type'));
    }

    public function testPrivacyPolicyShortcodeWiresTagAndPolicyType(): void
    {
        Functions\when('add_shortcode')->justReturn(true);
        $sc = new LBFA_Privacy_Policy_Shortcode();
        $this->assertSame('LBFA_PRIVACY_POLICY', $this->readProtected($sc, 'tag'));
        $this->assertSame('privacy_policy', $this->readProtected($sc, 'policy_type'));
    }

    public function testCgvPolicyShortcodeWiresTagAndPolicyType(): void
    {
        Functions\when('add_shortcode')->justReturn(true);
        $sc = new LBFA_CGV_Policy_Shortcode();
        $this->assertSame('LBFA_CGV_POLICY', $this->readProtected($sc, 'tag'));
        $this->assertSame('terms_of_service', $this->readProtected($sc, 'policy_type'));
    }

    public function testAccessibilityDeclarationShortcodeWiresTagAndPolicyType(): void
    {
        Functions\when('add_shortcode')->justReturn(true);
        $sc = new LBFA_Accessibility_Declaration_Shortcode();
        $this->assertSame('LBFA_ACCESSIBILITY_DECLARATION', $this->readProtected($sc, 'tag'));
        $this->assertSame('accessibility_declaration', $this->readProtected($sc, 'policy_type'));
    }

    public function testEachShortcodeRegistersWithAddShortcodeOnConstruction(): void
    {
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

        new LBFA_Cookie_Policy_Shortcode();
        new LBFA_Privacy_Policy_Shortcode();
        new LBFA_CGV_Policy_Shortcode();
        new LBFA_Accessibility_Declaration_Shortcode();
        $this->addToAssertionCount(1);
    }

    public function testCookiePolicyShortcodeRendersErrorNoticeWhenUrlMissing(): void
    {
        Functions\when('add_shortcode')->justReturn(true);
        $output = (new LBFA_Cookie_Policy_Shortcode())->handle(['lang' => 'it']);
        $this->assertStringContainsString('lbp-cookie_policy-policy-notice', $output);
        $this->assertStringContainsString('Cookie Policy', $output);
    }

    public function testPrivacyPolicyShortcodeRendersErrorNoticeWhenUrlMissing(): void
    {
        Functions\when('add_shortcode')->justReturn(true);
        $output = (new LBFA_Privacy_Policy_Shortcode())->handle(['lang' => 'it']);
        $this->assertStringContainsString('lbp-privacy_policy-policy-notice', $output);
        $this->assertStringContainsString('Privacy Policy', $output);
    }

    public function testCgvPolicyShortcodeRendersErrorNoticeWhenUrlMissing(): void
    {
        Functions\when('add_shortcode')->justReturn(true);
        $output = (new LBFA_CGV_Policy_Shortcode())->handle(['lang' => 'it']);
        $this->assertStringContainsString('lbp-terms_of_service-policy-notice', $output);
    }

    public function testAccessibilityDeclarationShortcodeRendersErrorNoticeWhenUrlMissing(): void
    {
        Functions\when('add_shortcode')->justReturn(true);
        $output = (new LBFA_Accessibility_Declaration_Shortcode())->handle(['lang' => 'it']);
        $this->assertStringContainsString('lbp-accessibility_declaration-policy-notice', $output);
        $this->assertStringContainsString('Dichiarazione di accessibilità', $output);
    }

    public function testCookiePolicyShortcodeRendersIframeWhenUrlConfigured(): void
    {
        Functions\when('add_shortcode')->justReturn(true);
        LBFA_Option_Helper::$__options['documents_cookie_policy_html_url_it'] = 'https://x/cp.html';

        $output = (new LBFA_Cookie_Policy_Shortcode())->handle(['lang' => 'it', 'html' => 'false']);
        $this->assertStringContainsString('<iframe', $output);
        $this->assertStringContainsString('https://x/cp.html', $output);
    }
}
