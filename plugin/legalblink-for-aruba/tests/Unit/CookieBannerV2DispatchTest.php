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
use LBFA_Option_Helper;
use LBFA_Transient_Helper;
use LegalBlink\Tests\TestCase;
use Mockery;
use WP_Error;

require_once dirname(__DIR__, 2) . '/classes/controller/api/class-lbfa-capability-api-controller.php';
require_once dirname(__DIR__, 2) . '/classes/class-lbfa-frontend-manager.php';

class CookieBannerV2DispatchTest extends TestCase
{
    protected function set_up(): void
    {
        parent::set_up();

        LBFA_Option_Helper::reset();
        LBFA_Transient_Helper::reset();

        Functions\when('__')->returnArg(1);
        Functions\when('add_action')->justReturn(true);
        Functions\when('is_wp_error')->alias(static fn ($v) => $v instanceof WP_Error);
        Functions\when('wp_remote_retrieve_response_code')->alias(static fn ($r) => $r['response']['code'] ?? 0);
        Functions\when('wp_remote_retrieve_body')->alias(static fn ($r) => $r['body'] ?? '');
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

    /**
     * S#7920 (T12): the capabilities cache expires after an hour and is
     * cleared on plugin update. The public site must resolve it again instead
     * of falling back to the legacy banner until an admin reopens the panel.
     */
    public function testFrontendShouldUseBannerV2FetchesCapabilitiesOnCacheMiss(): void
    {
        LBFA_Option_Helper::$__options['jwt_token'] = 'jwt';

        Functions\expect('wp_remote_get')
            ->once()
            ->with(
                'https://backend.example.test/integrations/wordpress/capabilities',
                Mockery::on(static fn ($args) => ($args['headers']['Authorization'] ?? '') === 'Bearer jwt'
                    && ($args['timeout'] ?? null) === 5)
            )
            ->andReturn([
                'response' => ['code' => 200],
                'body' => json_encode(['mode' => 'gdpr-only', 'features' => ['gdpr' => true, 'cookieBannerV2' => true]]),
            ]);

        $this->assertTrue(LBFA_Frontend_Manager::should_use_banner_v2());
        $this->assertTrue(LBFA_Transient_Helper::$__cache['capabilities_public']['features']['cookieBannerV2']);
        // The panel's cache is left to the panel: an answer obtained without
        // an admin session (e.g. mode "none" for a revoked token) must not
        // hide the panel tabs after the next login.
        $this->assertArrayNotHasKey('capabilities', LBFA_Transient_Helper::$__cache);
        $this->assertArrayNotHasKey('capabilities_fetch_backoff', LBFA_Transient_Helper::$__cache);
        $this->assertSame(3600, $this->lastExpiration('capabilities_public'));
    }

    public function testFrontendShouldUseBannerV2ReadsPublicCacheWithoutFetching(): void
    {
        LBFA_Option_Helper::$__options['jwt_token'] = 'jwt';
        LBFA_Transient_Helper::$__cache['capabilities_public'] = [
            'features' => ['cookieBannerV2' => true],
        ];

        Functions\expect('wp_remote_get')->never();

        $this->assertTrue(LBFA_Frontend_Manager::should_use_banner_v2());
    }

    public function testFrontendShouldUseBannerV2PrefersPanelCache(): void
    {
        LBFA_Option_Helper::$__options['jwt_token'] = 'jwt';
        LBFA_Transient_Helper::$__cache['capabilities'] = ['features' => ['cookieBannerV2' => true]];
        LBFA_Transient_Helper::$__cache['capabilities_public'] = ['features' => ['cookieBannerV2' => false]];

        Functions\expect('wp_remote_get')->never();

        $this->assertTrue(LBFA_Frontend_Manager::should_use_banner_v2());
    }

    public function testFrontendShouldUseBannerV2DoesNotFetchWithoutSession(): void
    {
        Functions\expect('wp_remote_get')->never();

        $this->assertFalse(LBFA_Frontend_Manager::should_use_banner_v2());
    }

    public function testFrontendShouldUseBannerV2BacksOffAfterFailedFetch(): void
    {
        LBFA_Option_Helper::$__options['jwt_token'] = 'jwt';

        Functions\expect('wp_remote_get')
            ->once()
            ->andReturn(['response' => ['code' => 500], 'body' => '{}']);

        $this->assertFalse(LBFA_Frontend_Manager::should_use_banner_v2());
        // Second page view inside the backoff window: no new backend call.
        $this->assertFalse(LBFA_Frontend_Manager::should_use_banner_v2());
        $this->assertArrayNotHasKey('capabilities', LBFA_Transient_Helper::$__cache);
        $this->assertArrayNotHasKey('capabilities_public', LBFA_Transient_Helper::$__cache);
    }

    /**
     * Plan unknown (backoff after a failure, or a fetch already in progress):
     * keep serving the cached v2 banner and do not call the backend.
     */
    public function testFrontendShouldUseBannerV2KeepsCachedV2WhileCapabilitiesUnknown(): void
    {
        LBFA_Option_Helper::$__options['jwt_token'] = 'jwt';
        LBFA_Transient_Helper::$__cache['capabilities_fetch_backoff'] = 1;
        LBFA_Transient_Helper::$__cache['cookie_banner_snippet_v2'] = '<script>cached-v2</script>';

        Functions\expect('wp_remote_get')->never();

        $this->assertTrue(LBFA_Frontend_Manager::should_use_banner_v2());
    }

    public function testFrontendShouldUseBannerV2FallsBackToLegacyWhenUnknownAndNothingCached(): void
    {
        LBFA_Option_Helper::$__options['jwt_token'] = 'jwt';
        LBFA_Transient_Helper::$__cache['capabilities_fetch_backoff'] = 1;

        Functions\expect('wp_remote_get')->never();

        $this->assertFalse(LBFA_Frontend_Manager::should_use_banner_v2());
    }

    public function testCapabilitiesFetchMarksRequestInProgress(): void
    {
        LBFA_Option_Helper::$__options['jwt_token'] = 'jwt';
        $markerDuringRequest = null;

        Functions\expect('wp_remote_get')
            ->once()
            ->andReturnUsing(static function () use (&$markerDuringRequest) {
                $markerDuringRequest = LBFA_Transient_Helper::$__cache['capabilities_fetch_backoff'] ?? null;
                return ['response' => ['code' => 200], 'body' => json_encode(['features' => ['cookieBannerV2' => true]])];
            });

        $this->assertIsArray(LBFA_Capability_API_Controller::get_cached_or_fetch());
        // Concurrent page views saw the marker and kept the cached banner.
        $this->assertNotNull($markerDuringRequest);
        $this->assertArrayNotHasKey('capabilities_fetch_backoff', LBFA_Transient_Helper::$__cache);
    }

    public function testCapabilitiesFetchBacksOffOnWpError(): void
    {
        LBFA_Option_Helper::$__options['jwt_token'] = 'jwt';

        Functions\expect('wp_remote_get')->once()->andReturn(new WP_Error('http_request_failed', 'timeout'));

        $this->assertNull(LBFA_Capability_API_Controller::get_cached_or_fetch());
        $this->assertSame(300, $this->lastExpiration('capabilities_fetch_backoff'));
        $this->assertArrayNotHasKey('capabilities_public', LBFA_Transient_Helper::$__cache);
    }

    public function testCapabilitiesFetchBacksOffOnNonJsonBody(): void
    {
        LBFA_Option_Helper::$__options['jwt_token'] = 'jwt';

        Functions\expect('wp_remote_get')->once()->andReturn(['response' => ['code' => 200], 'body' => '<html>bad gateway</html>']);

        $this->assertNull(LBFA_Capability_API_Controller::get_cached_or_fetch());
        $this->assertSame(300, $this->lastExpiration('capabilities_fetch_backoff'));
    }

    /**
     * The backend answers 200 with mode "none" for a revoked token: that is a
     * valid plan without v2, cached like any answer, not a failure.
     */
    public function testCapabilitiesModeNoneIsCachedAsPlanWithoutV2(): void
    {
        LBFA_Option_Helper::$__options['jwt_token'] = 'jwt';

        Functions\expect('wp_remote_get')->once()->andReturn([
            'response' => ['code' => 200],
            'body' => json_encode(['mode' => 'none', 'features' => ['gdpr' => false, 'cookieBannerV2' => false]]),
        ]);

        $this->assertFalse(LBFA_Frontend_Manager::should_use_banner_v2());
        $this->assertSame('none', LBFA_Transient_Helper::$__cache['capabilities_public']['mode']);
        $this->assertArrayNotHasKey('capabilities_fetch_backoff', LBFA_Transient_Helper::$__cache);
    }

    private function lastExpiration(string $key): ?int
    {
        $expiration = null;
        foreach (LBFA_Transient_Helper::$__sets as $set) {
            if ($set['key'] === $key) {
                $expiration = $set['expiration'];
            }
        }
        return $expiration;
    }

    public function testIsFeatureEnabledReadsCacheOnlyByDefault(): void
    {
        LBFA_Option_Helper::$__options['jwt_token'] = 'jwt';

        Functions\expect('wp_remote_get')->never();

        $this->assertFalse(LBFA_Capability_API_Controller::is_feature_enabled('cookieBannerV2'));
    }
}
