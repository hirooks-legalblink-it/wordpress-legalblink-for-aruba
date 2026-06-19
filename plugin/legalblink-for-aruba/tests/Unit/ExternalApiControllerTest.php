<?php
/**
 * Unit tests for LBFA_External_API_Controller.
 *
 * Validates GET /languages and GET /branding (cache hit, jwt missing,
 * remote fetch + cache, http/parse errors).
 */

declare(strict_types=1);

namespace LegalBlink\Tests\Unit;

use Brain\Monkey\Functions;
use LBFA_Config_Helper;
use LBFA_External_API_Controller;
use LBFA_Option_Helper;
use LBFA_Transient_Helper;
use LegalBlink\Tests\TestCase;
use Mockery;
use WP_Error;

require_once dirname(__DIR__, 2) . '/classes/controller/api/class-lbfa-external-api-controller.php';

class ExternalApiControllerTest extends TestCase
{
    protected function set_up(): void
    {
        parent::set_up();

        LBFA_Option_Helper::reset();
        LBFA_Transient_Helper::reset();
        LBFA_Config_Helper::reset();

        Functions\when('__')->returnArg(1);
        Functions\when('is_wp_error')->alias(static fn ($v) => $v instanceof WP_Error);
        Functions\when('wp_remote_retrieve_response_code')->alias(static fn ($r) => $r['response']['code'] ?? 0);
        Functions\when('wp_remote_retrieve_body')->alias(static fn ($r) => $r['body'] ?? '');
    }

    protected function tear_down(): void
    {
        Mockery::close();
        parent::tear_down();
    }

    public function testRegisterRoutesRegistersLanguagesAndBranding(): void
    {
        Functions\expect('register_rest_route')
            ->times(2)
            ->with('lbfa/v1', Mockery::anyOf('/languages', '/branding'), Mockery::type('array'));

        (new LBFA_External_API_Controller())->register_routes();
        $this->addToAssertionCount(1);
    }

    public function testGetLanguagesCacheHit(): void
    {
        $cached = ['count' => 2, 'data' => [['code' => 'it'], ['code' => 'en']]];
        LBFA_Transient_Helper::$__cache['languages'] = $cached;
        Functions\expect('wp_remote_get')->never();

        $payload = (new LBFA_External_API_Controller())->get_languages()->get_data();
        $this->assertTrue($payload['success']);
        $this->assertSame($cached, $payload['data']);
    }

    public function testGetLanguagesMissingJwtReturnsError(): void
    {
        Functions\expect('wp_remote_get')->never();

        $payload = (new LBFA_External_API_Controller())->get_languages()->get_data();
        $this->assertFalse($payload['success']);
    }

    public function testGetLanguagesCacheMissFetchesAndCaches(): void
    {
        LBFA_Option_Helper::$__options['jwt_token'] = 'jwt';
        $remote = ['count' => 1, 'data' => [['code' => 'it']]];

        Functions\expect('wp_remote_get')
            ->once()
            ->with(
                'https://backend.example.test/integrations/wordpress/languages',
                Mockery::on(static fn ($args) => ($args['headers']['Authorization'] ?? '') === 'Bearer jwt')
            )
            ->andReturn(['response' => ['code' => 200], 'body' => json_encode($remote)]);

        $payload = (new LBFA_External_API_Controller())->get_languages()->get_data();

        $this->assertTrue($payload['success']);
        $this->assertSame($remote, $payload['data']);
        $this->assertSame($remote, LBFA_Transient_Helper::$__cache['languages']);
    }

    public function testGetLanguagesWith5xxReturnsError(): void
    {
        LBFA_Option_Helper::$__options['jwt_token'] = 'jwt';

        Functions\expect('wp_remote_get')
            ->once()
            ->andReturn(['response' => ['code' => 500], 'body' => '{}']);

        $payload = (new LBFA_External_API_Controller())->get_languages()->get_data();
        $this->assertFalse($payload['success']);
        $this->assertArrayNotHasKey('languages', LBFA_Transient_Helper::$__cache);
    }

    public function testGetLanguagesWithWpErrorReturnsError(): void
    {
        LBFA_Option_Helper::$__options['jwt_token'] = 'jwt';

        Functions\expect('wp_remote_get')
            ->once()
            ->andReturn(new WP_Error('http_request_failed', 'boom'));

        $payload = (new LBFA_External_API_Controller())->get_languages()->get_data();
        $this->assertFalse($payload['success']);
    }

    public function testGetBrandingCacheHit(): void
    {
        $cached = ['logo' => 'https://x/logo.png', 'colors' => []];
        LBFA_Transient_Helper::$__cache['branding_data'] = $cached;
        Functions\expect('wp_remote_get')->never();

        $payload = (new LBFA_External_API_Controller())->get_branding()->get_data();
        $this->assertTrue($payload['success']);
        $this->assertSame($cached, $payload['data']);
    }

    public function testGetBrandingMissingJwtReturnsError(): void
    {
        Functions\expect('wp_remote_get')->never();

        $payload = (new LBFA_External_API_Controller())->get_branding()->get_data();
        $this->assertFalse($payload['success']);
    }

    public function testGetBrandingCacheMissFetchesAndCaches(): void
    {
        LBFA_Option_Helper::$__options['jwt_token'] = 'jwt';
        $remote = ['logo' => 'https://x/logo.png', 'colors' => ['primary' => '#000']];

        Functions\expect('wp_remote_get')
            ->once()
            ->with(
                'https://backend.example.test/integrations/wordpress/config/cobranding',
                Mockery::on(static fn ($args) => ($args['headers']['Authorization'] ?? '') === 'Bearer jwt')
            )
            ->andReturn(['response' => ['code' => 200], 'body' => json_encode($remote)]);

        $payload = (new LBFA_External_API_Controller())->get_branding()->get_data();

        $this->assertTrue($payload['success']);
        $this->assertSame($remote, $payload['data']);
        $this->assertSame($remote, LBFA_Transient_Helper::$__cache['branding_data']);
    }

    public function testGetBrandingWith5xxReturnsError(): void
    {
        LBFA_Option_Helper::$__options['jwt_token'] = 'jwt';

        Functions\expect('wp_remote_get')
            ->once()
            ->andReturn(['response' => ['code' => 500], 'body' => '{}']);

        $payload = (new LBFA_External_API_Controller())->get_branding()->get_data();
        $this->assertFalse($payload['success']);
    }
}
