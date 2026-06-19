<?php
/**
 * Unit tests for LBFA_Capability_API_Controller.
 *
 * Validates the contract of the capability proxy: cache hit short-circuit,
 * authentication failure, backend error mapping, payload normalization and
 * route registration on `lbfa/v1/capabilities`.
 */

declare(strict_types=1);

namespace LegalBlink\Tests\Unit;

use Brain\Monkey\Functions;
use LBFA_Capability_API_Controller;
use LBFA_Config_Helper;
use LBFA_Option_Helper;
use LBFA_Transient_Helper;
use LegalBlink\Tests\TestCase;
use Mockery;
use WP_Error;

require_once dirname(__DIR__, 2) . '/classes/controller/api/class-lbfa-capability-api-controller.php';

class CapabilityApiControllerTest extends TestCase
{
    protected function set_up(): void
    {
        parent::set_up();

        LBFA_Option_Helper::reset();
        LBFA_Transient_Helper::reset();
        LBFA_Config_Helper::reset();

        Functions\when('__')->returnArg(1);
        Functions\when('is_wp_error')->alias(static fn ($value) => $value instanceof WP_Error);
        Functions\when('wp_remote_retrieve_response_code')->alias(static fn ($response) => $response['response']['code'] ?? 0);
        Functions\when('wp_remote_retrieve_body')->alias(static fn ($response) => $response['body'] ?? '');
        Functions\when('wp_json_encode')->alias(static fn ($value) => json_encode($value));
    }

    protected function tear_down(): void
    {
        Mockery::close();
        parent::tear_down();
    }

    public function testRegisterRoutesRegistersCapabilitiesEndpoint(): void
    {
        Functions\expect('register_rest_route')
            ->once()
            ->with(
                'lbfa/v1',
                '/capabilities',
                Mockery::on(static function ($args) {
                    return is_array($args)
                        && ($args['methods'] ?? null) === 'GET'
                        && is_callable($args['callback'] ?? null)
                        && is_callable($args['permission_callback'] ?? null);
                })
            );

        (new LBFA_Capability_API_Controller())->register_routes();
        $this->addToAssertionCount(1);
    }

    public function testGetCapabilitiesReturnsErrorWhenJwtMissing(): void
    {
        Functions\expect('wp_remote_get')->never();

        $response = (new LBFA_Capability_API_Controller())->get_capabilities();
        $payload = $response->get_data();

        $this->assertFalse($payload['success']);
        $this->assertNotEmpty($payload['errors']);
    }

    public function testGetCapabilitiesShortCircuitsOnCacheHit(): void
    {
        $cached = $this->normalizedFixture();
        LBFA_Option_Helper::$__options['jwt_token'] = 'jwt-token';
        LBFA_Transient_Helper::$__cache['capabilities'] = $cached;
        Functions\expect('wp_remote_get')->never();

        $payload = (new LBFA_Capability_API_Controller())->get_capabilities()->get_data();

        $this->assertTrue($payload['success']);
        $this->assertSame($cached, $payload['data']);
    }

    public function testGetCapabilitiesHitsBackendOnCacheMissAndCachesResult(): void
    {
        LBFA_Option_Helper::$__options['jwt_token'] = 'jwt-token';

        $remoteBody = json_encode($this->normalizedFixture());
        Functions\expect('wp_remote_get')
            ->once()
            ->with(
                'https://backend.example.test/integrations/wordpress/capabilities',
                Mockery::on(static function ($args) {
                    return ($args['headers']['Authorization'] ?? '') === 'Bearer jwt-token';
                })
            )
            ->andReturn(['response' => ['code' => 200], 'body' => $remoteBody]);

        $payload = (new LBFA_Capability_API_Controller())->get_capabilities()->get_data();

        $this->assertTrue($payload['success']);
        $this->assertSame('hybrid', $payload['data']['mode']);
        $this->assertTrue($payload['data']['features']['gdpr']);
        $this->assertTrue($payload['data']['features']['accessibilityWidget']);

        // Result must have been written to the transient stub.
        $this->assertSame($payload['data'], LBFA_Transient_Helper::$__cache['capabilities']);
        $this->assertNotEmpty(LBFA_Transient_Helper::$__sets);
    }

    public function testGetCapabilitiesMapsBackendErrorToErrorResponse(): void
    {
        LBFA_Option_Helper::$__options['jwt_token'] = 'jwt-token';

        Functions\expect('wp_remote_get')
            ->once()
            ->andReturn(['response' => ['code' => 401], 'body' => json_encode(['error' => 'unauthorized'])]);

        $payload = (new LBFA_Capability_API_Controller())->get_capabilities()->get_data();
        $this->assertFalse($payload['success']);
        $this->assertNotEmpty($payload['errors']);
        $this->assertArrayNotHasKey('capabilities', LBFA_Transient_Helper::$__cache);
    }

    public function testNormalizeCapabilitiesAppliesDefaultsForMissingFields(): void
    {
        $controller = new LBFA_Capability_API_Controller();

        $normalized = $controller->normalize_capabilities([
            'mode' => 'gdpr-only',
            'features' => ['gdpr' => true],
        ]);

        $this->assertSame('gdpr-only', $normalized['mode']);
        $this->assertTrue($normalized['features']['gdpr']);
        $this->assertFalse($normalized['features']['accessibility']);
        $this->assertFalse($normalized['features']['accessibilityWidget']);
        $this->assertFalse($normalized['documents']['privacyPolicy']);
        $this->assertFalse($normalized['resources']['accessibilityWidgetConfigured']);
        $this->assertNull($normalized['warnings']['accessibilityWidget']);
    }

    public function testNormalizeCapabilitiesPreservesWidgetWarning(): void
    {
        $normalized = (new LBFA_Capability_API_Controller())->normalize_capabilities([
            'mode' => 'hybrid',
            'features' => ['accessibilityWidget' => true],
            'warnings' => ['accessibilityWidget' => 'configuration_expired'],
        ]);

        $this->assertSame('configuration_expired', $normalized['warnings']['accessibilityWidget']);
    }

    private function normalizedFixture(): array
    {
        return [
            'mode' => 'hybrid',
            'features' => [
                'gdpr' => true,
                'accessibility' => true,
                'cookieBannerV2' => true,
                'accessibilityDeclaration' => true,
                'accessibilityWidget' => true,
            ],
            'documents' => [
                'privacyPolicy' => true,
                'cookiePolicy' => true,
                'termsOfService' => true,
                'accessibilityDeclaration' => true,
            ],
            'resources' => [
                'accessibilityWidgetConfigured' => true,
            ],
            'warnings' => [
                'accessibilityWidget' => null,
            ],
        ];
    }
}
