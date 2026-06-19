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
use LBFA_Transient_Helper;
use LegalBlink\Tests\TestCase;
use Mockery;

require_once dirname(__DIR__, 2) . '/classes/controller/api/class-lbfa-capability-api-controller.php';
require_once dirname(__DIR__, 2) . '/classes/class-lbfa-frontend-manager.php';

class CookieBannerV2DispatchTest extends TestCase
{
    protected function set_up(): void
    {
        parent::set_up();

        LBFA_Transient_Helper::reset();

        Functions\when('__')->returnArg(1);
        Functions\when('add_action')->justReturn(true);
    }

    protected function tear_down(): void
    {
        Mockery::close();
        parent::tear_down();
    }

    public function testIsFeatureEnabledReturnsFalseWhenCapabilitiesUnresolved(): void
    {
        $this->assertFalse(LBFA_Capability_API_Controller::is_feature_enabled('cookieBannerV2'));
        $this->assertFalse(LBFA_Capability_API_Controller::is_feature_enabled('gdpr'));
    }

    public function testIsFeatureEnabledReturnsFalseWhenCapabilitiesNotArray(): void
    {
        LBFA_Transient_Helper::$__cache['capabilities'] = 'not-an-array';

        $this->assertFalse(LBFA_Capability_API_Controller::is_feature_enabled('cookieBannerV2'));
    }

    public function testIsFeatureEnabledReadsCachedFeatures(): void
    {
        LBFA_Transient_Helper::$__cache['capabilities'] = [
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

        $this->assertTrue(LBFA_Capability_API_Controller::is_feature_enabled('cookieBannerV2'));
        $this->assertTrue(LBFA_Capability_API_Controller::is_feature_enabled('gdpr'));
        $this->assertFalse(LBFA_Capability_API_Controller::is_feature_enabled('accessibility'));
    }

    public function testIsFeatureEnabledReturnsFalseForUnknownFeature(): void
    {
        LBFA_Transient_Helper::$__cache['capabilities'] = [
            'features' => ['gdpr' => true],
        ];

        $this->assertFalse(LBFA_Capability_API_Controller::is_feature_enabled('nonexistent'));
    }

    public function testFrontendShouldUseBannerV2DelegatesToCapability(): void
    {
        LBFA_Transient_Helper::$__cache['capabilities'] = [
            'features' => ['cookieBannerV2' => true],
        ];

        $this->assertTrue(LBFA_Frontend_Manager::should_use_banner_v2());
    }

    public function testFrontendShouldUseBannerV2FalseWhenCapabilityFalse(): void
    {
        LBFA_Transient_Helper::$__cache['capabilities'] = [
            'features' => ['cookieBannerV2' => false],
        ];

        $this->assertFalse(LBFA_Frontend_Manager::should_use_banner_v2());
    }

    public function testFrontendShouldUseBannerV2FalseWhenCapabilitiesMissing(): void
    {
        $this->assertFalse(LBFA_Frontend_Manager::should_use_banner_v2());
    }
}
