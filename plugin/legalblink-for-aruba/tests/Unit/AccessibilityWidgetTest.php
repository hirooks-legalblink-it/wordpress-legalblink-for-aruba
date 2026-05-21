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
use LBFA_Config_Helper;
use LBFA_Option_Helper;
use LBFA_Transient_Helper;
use LegalBlink\Tests\TestCase;
use Mockery;
use WP_Error;
use WP_REST_Request;

require_once dirname(__DIR__, 2) . '/classes/controller/api/class-lbfa-accessibility-api-controller.php';

class AccessibilityWidgetTest extends TestCase
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
        Functions\expect('wp_remote_get')->never();

        $payload = (new LBFA_Accessibility_API_Controller())->get_widget()->get_data();
        $this->assertFalse($payload['success']);
        $this->assertNotEmpty($payload['errors']);
    }

    public function testGetWidgetCacheHitHydratesLocalToggle(): void
    {
        $cached = $this->normalizedFixture();
        LBFA_Option_Helper::$__options = [
            'jwt_token' => 'jwt-token',
            'accessibility_widget_enabled' => true,
        ];
        LBFA_Transient_Helper::$__cache['accessibility_widget_snippet'] = $cached;
        Functions\expect('wp_remote_get')->never();

        $payload = (new LBFA_Accessibility_API_Controller())->get_widget()->get_data();

        $this->assertTrue($payload['success']);
        $this->assertTrue($payload['data']['localEnabled']);
        $this->assertTrue($payload['data']['available']);
    }

    public function testGetWidgetFetchesAndCachesNormalizedPayload(): void
    {
        LBFA_Option_Helper::$__options = [
            'jwt_token' => 'jwt-token',
            'accessibility_widget_enabled' => false,
        ];

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

        $payload = (new LBFA_Accessibility_API_Controller())->get_widget()->get_data();

        $this->assertTrue($payload['success']);
        $this->assertTrue($payload['data']['configured']);
        $this->assertSame('example.com', $payload['data']['domain']);
        $this->assertFalse($payload['data']['localEnabled']);
        $this->assertArrayHasKey('accessibility_widget_snippet', LBFA_Transient_Helper::$__cache);
    }

    public function testGetWidgetMapsBackendErrorToErrorResponse(): void
    {
        LBFA_Option_Helper::$__options['jwt_token'] = 'jwt-token';

        Functions\expect('wp_remote_get')
            ->once()
            ->andReturn(['response' => ['code' => 503], 'body' => '{}']);

        $payload = (new LBFA_Accessibility_API_Controller())->get_widget()->get_data();
        $this->assertFalse($payload['success']);
        $this->assertArrayNotHasKey('accessibility_widget_snippet', LBFA_Transient_Helper::$__cache);
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
        $request = new WP_REST_Request(['enabled' => true]);
        $payload = (new LBFA_Accessibility_API_Controller())->set_widget_local_toggle($request)->get_data();

        $this->assertTrue($payload['success']);
        $this->assertTrue($payload['data']['enabled']);
        $this->assertTrue(LBFA_Option_Helper::getOption('accessibility_widget_enabled'));
    }

    public function testSetWidgetLocalToggleAcceptsFalse(): void
    {
        // Pre-populate to verify the toggle actually flips the value.
        LBFA_Option_Helper::$__options['accessibility_widget_enabled'] = true;

        $request = new WP_REST_Request(['enabled' => false]);
        $payload = (new LBFA_Accessibility_API_Controller())->set_widget_local_toggle($request)->get_data();

        $this->assertTrue($payload['success']);
        $this->assertFalse($payload['data']['enabled']);
        $this->assertFalse(LBFA_Option_Helper::getOption('accessibility_widget_enabled'));
    }

    public function testSetWidgetLocalToggleDoesNotTouchCache(): void
    {
        // The admin's full-payload cache (used to render the admin status
        // card) is decoupled from the frontend html cache. The toggle save
        // must NOT clear either — flipping the option is a render-time gate,
        // not a backend-state change.
        LBFA_Transient_Helper::$__cache['accessibility_widget_snippet'] = $this->normalizedFixture();

        $request = new WP_REST_Request(['enabled' => true]);
        (new LBFA_Accessibility_API_Controller())->set_widget_local_toggle($request);

        $this->assertArrayHasKey(
            'accessibility_widget_snippet',
            LBFA_Transient_Helper::$__cache,
            'admin widget cache must survive a toggle save'
        );
    }

    private function normalizedFixture(): array
    {
        return [
            'available' => true,
            'configured' => true,
            'domain' => 'example.com',
            'html' => '<script></script>',
            'warnings' => [],
        ];
    }
}
