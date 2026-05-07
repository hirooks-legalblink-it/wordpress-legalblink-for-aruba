<?php
/**
 * Unit tests for the cookie banner v1/v2 dispatch (S#7701 Phase 5).
 *
 * Validates LBFA_Capability_API_Controller::is_feature_enabled() — the
 * static helper LBFA_Frontend_Manager + LBFA_Main_API_Controller use to
 * pick between /cookie-solution/embed (legacy) and /cookie-solution/embed-v2
 * — and the LBFA_Frontend_Manager::should_use_banner_v2() bridge.
 */

declare(strict_types=1);

namespace LegalBlink\Tests\Unit;

use Brain\Monkey\Functions;
use LBFA_Capability_API_Controller;
use LBFA_Frontend_Manager;
use LegalBlink\Tests\TestCase;
use Mockery;

require_once dirname(__DIR__, 2) . '/classes/controller/api/class-lbfa-capability-api-controller.php';
require_once dirname(__DIR__, 2) . '/classes/class-lbfa-frontend-manager.php';

class CookieBannerV2DispatchTest extends TestCase
{
    protected function set_up(): void
    {
        parent::set_up();

        Functions\when('__')->returnArg(1);
        Functions\when('add_action')->justReturn(true);
        Functions\when('current_time')->justReturn(0);
        Functions\when('maybe_serialize')->alias(static fn ($value) => is_scalar($value) ? (string) $value : serialize($value));
        Functions\when('is_multisite')->justReturn(false);
        Functions\when('LBFA_Logger::info')->justReturn(null);
        Functions\when('LBFA_Logger::warning')->justReturn(null);
        Functions\when('LBFA_Logger::error')->justReturn(null);
        Functions\when('LBFA_Logger::debug')->justReturn(null);
    }

    protected function tear_down(): void
    {
        Mockery::close();
        parent::tear_down();
    }

    public function testIsFeatureEnabledReturnsFalseWhenCapabilitiesUnresolved(): void
    {
        Functions\when('LBFA_Transient_Helper::get')->justReturn(false);

        $this->assertFalse(LBFA_Capability_API_Controller::is_feature_enabled('cookieBannerV2'));
        $this->assertFalse(LBFA_Capability_API_Controller::is_feature_enabled('gdpr'));
    }

    public function testIsFeatureEnabledReturnsFalseWhenCapabilitiesNotArray(): void
    {
        Functions\when('LBFA_Transient_Helper::get')->justReturn('not-an-array');

        $this->assertFalse(LBFA_Capability_API_Controller::is_feature_enabled('cookieBannerV2'));
    }

    public function testIsFeatureEnabledReadsCachedFeatures(): void
    {
        $cached = [
            'mode' => 'hybrid',
            'features' => [
                'gdpr' => true,
                'accessibility' => false,
                'cookieBannerV2' => true,
                'accessibilityDeclaration' => false,
                'accessibilityWidget' => false,
            ],
            'documents' => [],
            'resources' => ['accessibilityWidgetConfigured' => false],
            'warnings' => ['accessibilityWidget' => null],
        ];

        Functions\when('LBFA_Transient_Helper::get')->justReturn($cached);

        $this->assertTrue(LBFA_Capability_API_Controller::is_feature_enabled('cookieBannerV2'));
        $this->assertTrue(LBFA_Capability_API_Controller::is_feature_enabled('gdpr'));
        $this->assertFalse(LBFA_Capability_API_Controller::is_feature_enabled('accessibility'));
    }

    public function testIsFeatureEnabledReturnsFalseForUnknownFeature(): void
    {
        Functions\when('LBFA_Transient_Helper::get')->justReturn([
            'features' => ['gdpr' => true],
        ]);

        $this->assertFalse(LBFA_Capability_API_Controller::is_feature_enabled('nonexistent'));
    }

    public function testFrontendShouldUseBannerV2DelegatesToCapability(): void
    {
        Functions\when('LBFA_Transient_Helper::get')->justReturn([
            'features' => ['cookieBannerV2' => true],
        ]);

        $this->assertTrue(LBFA_Frontend_Manager::should_use_banner_v2());
    }

    public function testFrontendShouldUseBannerV2FalseWhenCapabilityFalse(): void
    {
        Functions\when('LBFA_Transient_Helper::get')->justReturn([
            'features' => ['cookieBannerV2' => false],
        ]);

        $this->assertFalse(LBFA_Frontend_Manager::should_use_banner_v2());
    }

    public function testFrontendShouldUseBannerV2FalseWhenCapabilitiesMissing(): void
    {
        Functions\when('LBFA_Transient_Helper::get')->justReturn(false);

        $this->assertFalse(LBFA_Frontend_Manager::should_use_banner_v2());
    }
}
