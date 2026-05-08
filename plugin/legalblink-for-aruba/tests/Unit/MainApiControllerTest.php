<?php
/**
 * Unit tests for LBFA_Main_API_Controller (banner endpoints + pages).
 *
 * Validates handle_banner_data dispatcher (GET/PUT), get_banner_data,
 * set_banner_data, fetch_banner_snippet (with v1/v2 dispatch), and
 * get_wordpress_pages.
 */

declare(strict_types=1);

namespace LegalBlink\Tests\Unit;

use Brain\Monkey\Functions;
use LBFA_Capability_API_Controller;
use LBFA_Config_Helper;
use LBFA_Main_API_Controller;
use LBFA_Option_Helper;
use LBFA_Transient_Helper;
use LegalBlink\Tests\TestCase;
use Mockery;
use ReflectionClass;
use WP_Error;
use WP_REST_Request;

require_once dirname(__DIR__, 2) . '/classes/controller/api/class-lbfa-capability-api-controller.php';
require_once dirname(__DIR__, 2) . '/classes/controller/api/class-lbfa-cache-api-controller.php';
require_once dirname(__DIR__, 2) . '/classes/controller/api/class-lbfa-auth-api-controller.php';
require_once dirname(__DIR__, 2) . '/classes/controller/api/class-lbfa-external-api-controller.php';
require_once dirname(__DIR__, 2) . '/classes/controller/api/class-lbfa-document-api-controller.php';
require_once dirname(__DIR__, 2) . '/classes/controller/api/class-lbfa-accessibility-api-controller.php';
require_once dirname(__DIR__, 2) . '/classes/controller/api/class-lbfa-main-api-controller.php';

class MainApiControllerTest extends TestCase
{
    protected function set_up(): void
    {
        parent::set_up();

        LBFA_Option_Helper::reset();
        LBFA_Transient_Helper::reset();
        LBFA_Config_Helper::reset();

        Functions\when('__')->returnArg(1);
        Functions\when('add_action')->justReturn(true);
        Functions\when('is_wp_error')->alias(static fn ($v) => $v instanceof WP_Error);
        Functions\when('wp_remote_retrieve_response_code')->alias(static fn ($r) => $r['response']['code'] ?? 0);
        Functions\when('wp_remote_retrieve_body')->alias(static fn ($r) => $r['body'] ?? '');
        Functions\when('wp_json_encode')->alias(static fn ($v) => json_encode($v));
        Functions\when('get_permalink')->alias(static fn ($id) => "https://site.test/p/{$id}");

        // Sanitization helpers used by the base class.
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

    /**
     * Bypasses the private constructor (which calls add_action) by reflecting
     * a fresh instance. Keeps tests isolated across runs.
     */
    private function makeController(): LBFA_Main_API_Controller
    {
        $reflect = new ReflectionClass(LBFA_Main_API_Controller::class);
        return $reflect->newInstanceWithoutConstructor();
    }

    public function testRegisterRoutesRegistersBannerAndPages(): void
    {
        Functions\expect('register_rest_route')
            ->times(2)
            ->with('lbfa/v1', Mockery::anyOf('/banner', '/pages'), Mockery::type('array'));

        $this->makeController()->register_routes();
        $this->addToAssertionCount(1);
    }

    public function testGetBannerDataReturnsEnabledStateAndPersistsHtml(): void
    {
        LBFA_Option_Helper::$__options = [
            'jwt_token' => 'jwt',
            'cookie_banner_enabled' => true,
        ];

        Functions\expect('wp_remote_get')
            ->once()
            ->andReturn([
                'response' => ['code' => 200],
                'body' => json_encode(['html' => '<script>banner</script>']),
            ]);

        $payload = $this->makeController()->get_banner_data()->get_data();

        $this->assertTrue($payload['success']);
        $this->assertTrue($payload['data']['enabled']);
        $this->assertSame('<script>banner</script>', $payload['data']['html']);
        $this->assertSame('<script>banner</script>', LBFA_Option_Helper::getOption('cookie_banner_html_code'));
    }

    public function testSetBannerDataUpdatesOptionAndClearsTransient(): void
    {
        LBFA_Transient_Helper::$__cache['banner'] = 'stale';
        $request = new WP_REST_Request(['enabled' => true], 'PUT');

        $payload = $this->makeController()->set_banner_data($request)->get_data();

        $this->assertTrue($payload['success']);
        $this->assertTrue($payload['data']['enabled']);
        $this->assertTrue(LBFA_Option_Helper::getOption('cookie_banner_enabled'));
        $this->assertArrayNotHasKey('banner', LBFA_Transient_Helper::$__cache);
    }

    public function testHandleBannerDataDispatchesGet(): void
    {
        LBFA_Option_Helper::$__options = ['jwt_token' => 'jwt', 'cookie_banner_enabled' => false];
        Functions\expect('wp_remote_get')
            ->once()
            ->andReturn(['response' => ['code' => 200], 'body' => json_encode(['html' => ''])]);

        $request = new WP_REST_Request([], 'GET');
        $payload = $this->makeController()->handle_banner_data($request)->get_data();

        $this->assertTrue($payload['success']);
        $this->assertFalse($payload['data']['enabled']);
    }

    public function testHandleBannerDataDispatchesPut(): void
    {
        $request = new WP_REST_Request(['enabled' => true], 'PUT');
        $payload = $this->makeController()->handle_banner_data($request)->get_data();

        $this->assertTrue($payload['success']);
    }

    public function testHandleBannerDataRejectsUnsupportedMethod(): void
    {
        $request = new WP_REST_Request([], 'DELETE');
        $payload = $this->makeController()->handle_banner_data($request)->get_data();
        $this->assertFalse($payload['success']);
    }

    public function testFetchBannerSnippetUsesV1WhenCapabilityFalse(): void
    {
        LBFA_Option_Helper::$__options['jwt_token'] = 'jwt';
        // No capabilities cached → cookieBannerV2 falsy → /embed legacy path.

        Functions\expect('wp_remote_get')
            ->once()
            ->with(
                'https://backend.example.test/integrations/wordpress/cookie-solution/embed?language=it',
                Mockery::type('array')
            )
            ->andReturn(['response' => ['code' => 200], 'body' => json_encode(['html' => '<script>v1</script>'])]);

        $html = $this->makeController()->fetch_banner_snippet();
        $this->assertSame('<script>v1</script>', $html);
    }

    public function testFetchBannerSnippetUsesV2WhenCapabilityTrue(): void
    {
        LBFA_Option_Helper::$__options['jwt_token'] = 'jwt';
        LBFA_Transient_Helper::$__cache['capabilities'] = [
            'features' => ['cookieBannerV2' => true],
        ];

        Functions\expect('wp_remote_get')
            ->once()
            ->with(
                'https://backend.example.test/integrations/wordpress/cookie-solution/embed-v2',
                Mockery::type('array')
            )
            ->andReturn(['response' => ['code' => 200], 'body' => json_encode(['html' => '<script>v2</script>'])]);

        $html = $this->makeController()->fetch_banner_snippet();
        $this->assertSame('<script>v2</script>', $html);
    }

    public function testFetchBannerSnippetWithMissingJwtReturnsErrorResponse(): void
    {
        Functions\expect('wp_remote_get')->never();

        $result = $this->makeController()->fetch_banner_snippet();
        $payload = $result->get_data();
        $this->assertFalse($payload['success']);
    }

    public function testFetchBannerSnippetWith5xxReturnsErrorResponse(): void
    {
        LBFA_Option_Helper::$__options['jwt_token'] = 'jwt';

        Functions\expect('wp_remote_get')
            ->once()
            ->andReturn(['response' => ['code' => 500], 'body' => '{}']);

        $result = $this->makeController()->fetch_banner_snippet();
        $payload = $result->get_data();
        $this->assertFalse($payload['success']);
    }

    public function testFetchBannerSnippetWithWpErrorReturnsEmptyString(): void
    {
        LBFA_Option_Helper::$__options['jwt_token'] = 'jwt';

        Functions\expect('wp_remote_get')
            ->once()
            ->andReturn(new WP_Error('http_request_failed', 'boom'));

        $this->assertSame('', $this->makeController()->fetch_banner_snippet());
    }

    public function testGetWordpressPagesReturnsFormattedList(): void
    {
        Functions\when('get_pages')->justReturn([
            (object) ['ID' => 1, 'post_title' => 'A', 'post_name' => 'a', 'post_modified' => '', 'post_content' => 'abc'],
            (object) ['ID' => 2, 'post_title' => 'B', 'post_name' => 'b', 'post_modified' => '', 'post_content' => 'abcdef'],
        ]);

        $payload = $this->makeController()->get_wordpress_pages()->get_data();
        $this->assertTrue($payload['success']);
        $this->assertSame(2, $payload['data']['total']);
        $this->assertSame(3, $payload['data']['pages'][0]['content_length']);
    }
}
