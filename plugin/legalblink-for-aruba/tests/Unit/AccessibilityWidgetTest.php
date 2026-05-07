<?php
/**
 * Unit tests for the widget surface of LBFA_Accessibility_API_Controller.
 *
 * Validates GET /accessibility/widget (cache + missing creds + remote fetch
 * with localEnabled hydration + error mapping + warning normalization that
 * forces configured=false + invalid warning filtering) and PUT
 * /accessibility/widget (toggle persisted to option storage).
 */

declare(strict_types=1);

namespace LegalBlink\Tests\Unit;

use Brain\Monkey\Functions;
use LBFA_Accessibility_API_Controller;
use LegalBlink\Tests\TestCase;
use Mockery;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

require_once dirname(__DIR__, 2) . '/classes/controller/api/class-lbfa-accessibility-api-controller.php';

if (!class_exists(WP_REST_Response::class, false)) {
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

if (!class_exists(WP_REST_Request::class, false)) {
    class WP_REST_Request
    {
        public function __construct(private array $params = []) {}
        public function get_param(string $key) { return $this->params[$key] ?? null; }
        public function get_method(): string { return 'PUT'; }
        public function get_header(string $key): string { return ''; }
    }
}

class AccessibilityWidgetTest extends TestCase
{
    protected function set_up(): void
    {
        parent::set_up();

        Functions\when('__')->returnArg(1);
        Functions\when('sprintf')->alias(static fn (...$args) => sprintf(...$args));
        Functions\when('current_time')->justReturn(0);
        Functions\when('maybe_serialize')->alias(static fn ($value) => is_scalar($value) ? (string) $value : serialize($value));
        Functions\when('is_wp_error')->alias(static fn ($value) => $value instanceof WP_Error);
        Functions\when('wp_remote_retrieve_response_code')->alias(static fn ($response) => $response['response']['code'] ?? 0);
        Functions\when('wp_remote_retrieve_body')->alias(static fn ($response) => $response['body'] ?? '');
        Functions\when('is_multisite')->justReturn(false);
        Functions\when('LBFA_Logger::info')->justReturn(null);
        Functions\when('LBFA_Logger::warning')->justReturn(null);
        Functions\when('LBFA_Logger::error')->justReturn(null);
        Functions\when('LBFA_Logger::debug')->justReturn(null);
        Functions\when('LBFA_Config_Helper::get_api_namespace')->justReturn('lbfa/v1');
        Functions\when('LBFA_Config_Helper::get_api_base_url')->justReturn('https://backend.example.test/integrations/wordpress');
        Functions\when('LBFA_Config_Helper::get_api_cache_time')->justReturn(3600);
        Functions\when('LBFA_Config_Helper::get_api_rate_limit')->justReturn(60);
        Functions\when('sanitize_text_field')->returnArg(1);
        Functions\when('sanitize_email')->returnArg(1);
        Functions\when('esc_url_raw')->returnArg(1);
        Functions\when('sanitize_textarea_field')->returnArg(1);
        Functions\when('wp_kses_post')->returnArg(1);
    }

    protected function tear_down(): void
    {
        Mockery::close();
        parent::tear_down();
    }

    public function testGetWidgetReturnsErrorWhenJwtMissing(): void
    {
        Functions\when('LBFA_Option_Helper::getOption')->justReturn('');
        Functions\when('LBFA_Transient_Helper::get')->justReturn(false);
        Functions\expect('wp_remote_get')->never();

        $payload = (new LBFA_Accessibility_API_Controller())->get_widget()->get_data();
        $this->assertFalse($payload['success']);
        $this->assertNotEmpty($payload['errors']);
    }

    public function testGetWidgetCacheHitHydratesLocalToggle(): void
    {
        $cached = $this->normalizedFixture(['localEnabled' => null]);
        unset($cached['localEnabled']);

        Functions\when('LBFA_Option_Helper::getOption')->alias(static function ($key, $default = null) {
            if ($key === 'jwt_token') return 'jwt-token';
            if ($key === 'accessibility_widget_enabled') return true;
            return $default;
        });
        Functions\when('LBFA_Transient_Helper::get')->justReturn($cached);
        Functions\expect('wp_remote_get')->never();

        $payload = (new LBFA_Accessibility_API_Controller())->get_widget()->get_data();

        $this->assertTrue($payload['success']);
        $this->assertTrue($payload['data']['localEnabled']);
        $this->assertTrue($payload['data']['available']);
    }

    public function testGetWidgetFetchesAndCachesNormalizedPayload(): void
    {
        Functions\when('LBFA_Option_Helper::getOption')->alias(static function ($key, $default = null) {
            if ($key === 'jwt_token') return 'jwt-token';
            if ($key === 'accessibility_widget_enabled') return false;
            return $default;
        });
        Functions\when('LBFA_Transient_Helper::get')->justReturn(false);

        Functions\expect('wp_remote_get')
            ->once()
            ->with(
                'https://backend.example.test/integrations/wordpress/accessibility/widget',
                Mockery::on(static fn ($args) => ($args['headers']['Authorization'] ?? '') === 'Bearer jwt-token')
            )
            ->andReturn([
                'response' => ['code' => 200],
                'body' => json_encode([
                    'available' => true,
                    'configured' => true,
                    'domain' => 'example.com',
                    'html' => '<script src="https://app.legalblink.it/api/scripts/lb_as.js" defer></script>',
                    'warnings' => [],
                ]),
            ]);

        Functions\expect('LBFA_Transient_Helper::set')
            ->once()
            ->with('accessibility_widget_snippet', Mockery::type('array'), Mockery::type('integer'))
            ->andReturn(true);

        $payload = (new LBFA_Accessibility_API_Controller())->get_widget()->get_data();

        $this->assertTrue($payload['success']);
        $this->assertTrue($payload['data']['configured']);
        $this->assertSame('example.com', $payload['data']['domain']);
        $this->assertFalse($payload['data']['localEnabled']);
    }

    public function testGetWidgetMapsBackendErrorToErrorResponse(): void
    {
        Functions\when('LBFA_Option_Helper::getOption')->justReturn('jwt-token');
        Functions\when('LBFA_Transient_Helper::get')->justReturn(false);

        Functions\expect('wp_remote_get')
            ->once()
            ->andReturn(['response' => ['code' => 503], 'body' => '{}']);
        Functions\expect('LBFA_Transient_Helper::set')->never();

        $payload = (new LBFA_Accessibility_API_Controller())->get_widget()->get_data();
        $this->assertFalse($payload['success']);
    }

    public function testNormalizeWidgetDropsInvalidWarnings(): void
    {
        $normalized = (new LBFA_Accessibility_API_Controller())->normalize_widget([
            'available' => true,
            'configured' => true,
            'domain' => 'x.test',
            'html' => '<script></script>',
            'warnings' => ['configuration_missing', 'unknown_value', 42],
        ]);

        $this->assertSame(['configuration_missing'], $normalized['warnings']);
        // Configured must drop to false in presence of any warning.
        $this->assertFalse($normalized['configured']);
    }

    public function testNormalizeWidgetForcesConfiguredFalseWhenWarningsPresent(): void
    {
        $normalized = (new LBFA_Accessibility_API_Controller())->normalize_widget([
            'available' => true,
            'configured' => true,
            'domain' => 'x.test',
            'html' => '',
            'warnings' => ['domain_mismatch'],
        ]);

        $this->assertFalse($normalized['configured']);
        $this->assertSame(['domain_mismatch'], $normalized['warnings']);
    }

    public function testSetWidgetLocalToggleStoresOption(): void
    {
        Functions\expect('LBFA_Option_Helper::setOption')
            ->once()
            ->with('accessibility_widget_enabled', true)
            ->andReturn(true);

        $request = new WP_REST_Request(['enabled' => true]);
        $payload = (new LBFA_Accessibility_API_Controller())->set_widget_local_toggle($request)->get_data();

        $this->assertTrue($payload['success']);
        $this->assertTrue($payload['data']['enabled']);
    }

    public function testSetWidgetLocalToggleAcceptsFalse(): void
    {
        Functions\expect('LBFA_Option_Helper::setOption')
            ->once()
            ->with('accessibility_widget_enabled', false)
            ->andReturn(true);

        $request = new WP_REST_Request(['enabled' => false]);
        $payload = (new LBFA_Accessibility_API_Controller())->set_widget_local_toggle($request)->get_data();

        $this->assertTrue($payload['success']);
        $this->assertFalse($payload['data']['enabled']);
    }

    private function normalizedFixture(array $overrides = []): array
    {
        return array_replace([
            'available' => true,
            'configured' => true,
            'domain' => 'example.com',
            'html' => '<script></script>',
            'warnings' => [],
        ], $overrides);
    }
}
