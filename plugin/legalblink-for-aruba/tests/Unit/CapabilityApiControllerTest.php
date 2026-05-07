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
use LegalBlink\Tests\TestCase;
use Mockery;
use WP_Error;
use WP_REST_Response;

require_once dirname(__DIR__, 2) . '/classes/controller/api/class-lbfa-capability-api-controller.php';

if (!class_exists(WP_REST_Response::class, false)) {
    /**
     * Minimal stand-in for the WP REST Response — Brain\Monkey does not
     * provide WordPress core classes, so we shim the surface used by the
     * base controller (status + data accessor).
     */
    class WP_REST_Response
    {
        public function __construct(public $data = null, public int $status = 200) {}
        public function get_data() { return $this->data; }
        public function get_status(): int { return $this->status; }
    }
}

if (!class_exists(WP_Error::class, false)) {
    class WP_Error
    {
        public function __construct(public string $code = '', public string $message = '', public array $data = []) {}
        public function get_error_message(): string { return $this->message; }
    }
}

class CapabilityApiControllerTest extends TestCase
{
    protected function set_up(): void
    {
        parent::set_up();

        // Translations: pass-through.
        Functions\when('__')->returnArg(1);
        Functions\when('sprintf')->alias(static fn (...$args) => sprintf(...$args));
        Functions\when('wp_json_encode')->alias(static fn ($value) => json_encode($value));
        Functions\when('current_time')->justReturn(0);
        Functions\when('maybe_serialize')->alias(static fn ($value) => is_scalar($value) ? (string) $value : serialize($value));
        Functions\when('is_wp_error')->alias(static fn ($value) => $value instanceof WP_Error);
        Functions\when('wp_remote_retrieve_response_code')->alias(static fn ($response) => $response['response']['code'] ?? 0);
        Functions\when('wp_remote_retrieve_body')->alias(static fn ($response) => $response['body'] ?? '');
        Functions\when('get_site_option')->justReturn(false);
        Functions\when('update_site_option')->justReturn(true);
        Functions\when('get_option')->justReturn(false);
        Functions\when('update_option')->justReturn(true);
        Functions\when('get_transient')->justReturn(false);
        Functions\when('set_transient')->justReturn(true);
        Functions\when('delete_transient')->justReturn(true);
        Functions\when('get_site_transient')->justReturn(false);
        Functions\when('set_site_transient')->justReturn(true);
        Functions\when('delete_site_transient')->justReturn(true);
        Functions\when('is_multisite')->justReturn(false);

        // Logger: silence info/warning/error/debug.
        Functions\when('LBFA_Logger::info')->justReturn(null);
        Functions\when('LBFA_Logger::warning')->justReturn(null);
        Functions\when('LBFA_Logger::error')->justReturn(null);
        Functions\when('LBFA_Logger::debug')->justReturn(null);

        // Config defaults so static helpers work without loading config.php.
        Functions\when('LBFA_Config_Helper::get_api_namespace')->justReturn('lbfa/v1');
        Functions\when('LBFA_Config_Helper::get_api_base_url')->justReturn('https://backend.example.test/integrations/wordpress');
        Functions\when('LBFA_Config_Helper::get_api_cache_time')->justReturn(3600);
        Functions\when('LBFA_Config_Helper::get_api_rate_limit')->justReturn(60);
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
    }

    public function testGetCapabilitiesReturnsErrorWhenJwtMissing(): void
    {
        // No token + no cache hit forces the missing-credentials path.
        Functions\when('LBFA_Option_Helper::getOption')->justReturn('');
        Functions\when('LBFA_Transient_Helper::get')->justReturn(false);
        Functions\expect('wp_remote_get')->never();

        $controller = new LBFA_Capability_API_Controller();
        $response = $controller->get_capabilities();

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $payload = $response->get_data();
        $this->assertFalse($payload['success']);
        $this->assertNotEmpty($payload['errors']);
    }

    public function testGetCapabilitiesShortCircuitsOnCacheHit(): void
    {
        $cached = $this->normalizedFixture();

        Functions\when('LBFA_Option_Helper::getOption')->justReturn('jwt-token');
        Functions\when('LBFA_Transient_Helper::get')->justReturn($cached);
        Functions\expect('wp_remote_get')->never();

        $controller = new LBFA_Capability_API_Controller();
        $response = $controller->get_capabilities();

        $payload = $response->get_data();
        $this->assertTrue($payload['success']);
        $this->assertSame($cached, $payload['data']);
    }

    public function testGetCapabilitiesHitsBackendOnCacheMissAndCachesResult(): void
    {
        Functions\when('LBFA_Option_Helper::getOption')->justReturn('jwt-token');
        Functions\when('LBFA_Transient_Helper::get')->justReturn(false);

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

        Functions\expect('LBFA_Transient_Helper::set')
            ->once()
            ->with('capabilities', Mockery::type('array'), Mockery::type('integer'))
            ->andReturn(true);

        $controller = new LBFA_Capability_API_Controller();
        $response = $controller->get_capabilities();

        $payload = $response->get_data();
        $this->assertTrue($payload['success']);
        $this->assertSame('hybrid', $payload['data']['mode']);
        $this->assertTrue($payload['data']['features']['gdpr']);
        $this->assertTrue($payload['data']['features']['accessibilityWidget']);
    }

    public function testGetCapabilitiesMapsBackendErrorToErrorResponse(): void
    {
        Functions\when('LBFA_Option_Helper::getOption')->justReturn('jwt-token');
        Functions\when('LBFA_Transient_Helper::get')->justReturn(false);

        Functions\expect('wp_remote_get')
            ->once()
            ->andReturn(['response' => ['code' => 401], 'body' => json_encode(['error' => 'unauthorized'])]);

        Functions\expect('LBFA_Transient_Helper::set')->never();

        $controller = new LBFA_Capability_API_Controller();
        $response = $controller->get_capabilities();

        $payload = $response->get_data();
        $this->assertFalse($payload['success']);
        $this->assertNotEmpty($payload['errors']);
    }

    public function testNormalizeCapabilitiesAppliesDefaultsForMissingFields(): void
    {
        $controller = new LBFA_Capability_API_Controller();

        $normalized = $controller->normalize_capabilities([
            'mode' => 'gdpr-only',
            'features' => ['gdpr' => true],
            // documents/resources/warnings intentionally missing
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
        $controller = new LBFA_Capability_API_Controller();

        $normalized = $controller->normalize_capabilities([
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
