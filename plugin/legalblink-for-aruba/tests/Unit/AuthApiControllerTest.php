<?php
/**
 * Unit tests for LBFA_Auth_API_Controller (legacy GDPR auth flow).
 *
 * Validates GET /auth/verify, POST /auth/login (with credential persistence),
 * POST /auth/logout (with full credential + cache wipe).
 */

declare(strict_types=1);

namespace LegalBlink\Tests\Unit;

use Brain\Monkey\Functions;
use LBFA_Auth_API_Controller;
use LBFA_Config_Helper;
use LBFA_Option_Helper;
use LBFA_Transient_Helper;
use LegalBlink\Tests\TestCase;
use Mockery;
use WP_Error;
use WP_REST_Request;

require_once dirname(__DIR__, 2) . '/classes/controller/api/class-lbfa-auth-api-controller.php';

class AuthApiControllerTest extends TestCase
{
    protected function set_up(): void
    {
        parent::set_up();

        LBFA_Option_Helper::reset();
        LBFA_Transient_Helper::reset();
        LBFA_Config_Helper::reset();

        Functions\when('__')->returnArg(1);
        Functions\when('sanitize_text_field')->returnArg(1);
        Functions\when('is_wp_error')->alias(static fn ($v) => $v instanceof WP_Error);
        Functions\when('wp_remote_retrieve_response_code')->alias(static fn ($r) => $r['response']['code'] ?? 0);
        Functions\when('wp_remote_retrieve_body')->alias(static fn ($r) => $r['body'] ?? '');
        Functions\when('wp_json_encode')->alias(static fn ($v) => json_encode($v));
        Functions\when('home_url')->justReturn('https://site.test');
        Functions\when('wp_parse_url')->alias(static function ($url, $component) {
            return parse_url($url, $component);
        });
    }

    protected function tear_down(): void
    {
        Mockery::close();
        parent::tear_down();
    }

    public function testRegisterRoutesRegistersThreeAuthEndpoints(): void
    {
        Functions\expect('register_rest_route')
            ->times(3)
            ->with('lbfa/v1', Mockery::anyOf('/auth/verify', '/auth/login', '/auth/logout'), Mockery::type('array'));

        (new LBFA_Auth_API_Controller())->register_routes();
        $this->addToAssertionCount(1);
    }

    public function testIsLoggedInWithMissingCredentialsReturnsError(): void
    {
        Functions\expect('wp_remote_get')->never();

        $payload = (new LBFA_Auth_API_Controller())->is_logged_in()->get_data();
        $this->assertFalse($payload['success']);
        $this->assertNotEmpty($payload['errors']);
    }

    public function testIsLoggedInWith200ReturnsUserData(): void
    {
        LBFA_Option_Helper::$__options = [
            'external_id' => 'ext-1',
            'jwt_token' => 'jwt',
        ];

        Functions\expect('wp_remote_get')
            ->once()
            ->with(
                'https://backend.example.test/integrations/wordpress/users/me',
                Mockery::on(static fn ($args) => ($args['headers']['Authorization'] ?? '') === 'Bearer jwt')
            )
            ->andReturn(['response' => ['code' => 200], 'body' => json_encode(['id' => 'u1', 'email' => 'a@b'])]);

        $payload = (new LBFA_Auth_API_Controller())->is_logged_in()->get_data();
        $this->assertTrue($payload['success']);
        $this->assertSame('u1', $payload['data']['id']);
    }

    public function testIsLoggedInWith401ReturnsError(): void
    {
        LBFA_Option_Helper::$__options = ['external_id' => 'ext-1', 'jwt_token' => 'jwt'];

        Functions\expect('wp_remote_get')
            ->once()
            ->andReturn(['response' => ['code' => 401], 'body' => '{}']);

        $payload = (new LBFA_Auth_API_Controller())->is_logged_in()->get_data();
        $this->assertFalse($payload['success']);
    }

    public function testIsLoggedInWithWpErrorReturnsError(): void
    {
        LBFA_Option_Helper::$__options = ['external_id' => 'ext-1', 'jwt_token' => 'jwt'];

        Functions\expect('wp_remote_get')
            ->once()
            ->andReturn(new WP_Error('http_request_failed', 'connection refused'));

        $payload = (new LBFA_Auth_API_Controller())->is_logged_in()->get_data();
        $this->assertFalse($payload['success']);
        $this->assertNotEmpty($payload['errors']);
    }

    public function testLoginPersistsCredentialsOn201(): void
    {
        $request = new WP_REST_Request(['external_id' => 'extX']);

        Functions\expect('wp_remote_post')
            ->once()
            ->with(
                'https://backend.example.test/integrations/wordpress/auth',
                Mockery::on(static function ($args) {
                    $body = json_decode($args['body'] ?? '{}', true);
                    return ($body['accessToken'] ?? '') === 'extX'
                        && ($body['domain'] ?? '') === 'site.test';
                })
            )
            ->andReturn([
                'response' => ['code' => 201],
                'body' => json_encode(['token' => 'jwt-x', 'user' => ['id' => 'u1']]),
            ]);

        $payload = (new LBFA_Auth_API_Controller())->login($request)->get_data();
        $this->assertTrue($payload['success']);
        $this->assertSame('extX', LBFA_Option_Helper::getOption('external_id'));
        $this->assertSame('jwt-x', LBFA_Option_Helper::getOption('jwt_token'));
        $this->assertNotNull(LBFA_Option_Helper::getOption('auth_data'));
    }

    public function testLoginWith401ReturnsErrorAndDoesNotPersist(): void
    {
        $request = new WP_REST_Request(['external_id' => 'extX']);

        Functions\expect('wp_remote_post')
            ->once()
            ->andReturn(['response' => ['code' => 401], 'body' => json_encode(['message' => 'unauthorized'])]);

        $payload = (new LBFA_Auth_API_Controller())->login($request)->get_data();
        $this->assertFalse($payload['success']);
        $this->assertNull(LBFA_Option_Helper::getOption('jwt_token'));
    }

    public function testLoginWithWpErrorMapsToErrorResponse(): void
    {
        $request = new WP_REST_Request(['external_id' => 'extX']);

        Functions\expect('wp_remote_post')
            ->once()
            ->andReturn(new WP_Error('http_request_failed', 'boom'));

        $payload = (new LBFA_Auth_API_Controller())->login($request)->get_data();
        $this->assertFalse($payload['success']);
    }

    public function testLogoutWipesCredentialsConfigsAndTransients(): void
    {
        LBFA_Option_Helper::$__options = [
            'external_id' => 'ext-1',
            'jwt_token' => 'jwt',
            'auth_data' => ['user' => 'u1'],
            'cache_duration' => 30,
            'cookie_banner_html_code' => '<script></script>',
            'cookie_banner_enabled' => true,
            'unrelated_option' => 'preserved',
        ];
        LBFA_Transient_Helper::$__cache = ['banner' => 'x', 'capabilities' => ['mode' => 'hybrid']];

        $payload = (new LBFA_Auth_API_Controller())->logout()->get_data();

        $this->assertTrue($payload['success']);
        // Cleared options
        $this->assertNull(LBFA_Option_Helper::getOption('external_id'));
        $this->assertNull(LBFA_Option_Helper::getOption('jwt_token'));
        $this->assertNull(LBFA_Option_Helper::getOption('auth_data'));
        $this->assertNull(LBFA_Option_Helper::getOption('cache_duration'));
        $this->assertNull(LBFA_Option_Helper::getOption('cookie_banner_html_code'));
        $this->assertNull(LBFA_Option_Helper::getOption('cookie_banner_enabled'));
        // Unrelated options preserved
        $this->assertSame('preserved', LBFA_Option_Helper::getOption('unrelated_option'));
        // Transients cleared
        $this->assertSame([], LBFA_Transient_Helper::$__cache);
    }
}
