<?php
/**
 * Unit tests for LBFA_Base_API_Controller.
 *
 * Validates the shared response/sanitize/permission helpers used by every
 * concrete controller. Uses a thin Harness subclass to expose protected
 * methods.
 */

declare(strict_types=1);

namespace LegalBlink\Tests\Unit;

use Brain\Monkey\Functions;
use LBFA_Base_API_Controller;
use LBFA_Config_Helper;
use LBFA_Transient_Helper;
use LegalBlink\Tests\TestCase;
use Mockery;
use WP_Error;

/**
 * Concrete subclass to exercise the abstract base controller and expose the
 * protected helpers through public proxies.
 */
class BaseApiControllerHarness extends LBFA_Base_API_Controller
{
    public function register_routes() {}

    public function publicCreateApiResponse($success = true, $data = [], $errors = null, string $message = '', int $http_status = 200)
    {
        return $this->create_api_response($success, $data, $errors, $message, $http_status);
    }

    public function publicCreateErrorResponse($errors, string $message = '', array $data = [])
    {
        return $this->create_error_response($errors, $message, $data);
    }

    public function publicVerifyRestNonce($request)
    {
        return $this->verify_rest_nonce($request);
    }

    public function publicCheckRateLimit()
    {
        return $this->check_rate_limit();
    }

    public function publicSanitize($input, array $options = [])
    {
        return $this->sanitize_and_validate_input($input, $options);
    }
}

class BaseApiControllerTest extends TestCase
{
    protected function set_up(): void
    {
        parent::set_up();

        LBFA_Transient_Helper::reset();
        LBFA_Config_Helper::reset();

        Functions\when('__')->returnArg(1);
        Functions\when('sanitize_text_field')->returnArg(1);
        Functions\when('sanitize_email')->alias(static fn ($v) => $v);
        Functions\when('esc_url_raw')->alias(static fn ($v) => $v);
        Functions\when('sanitize_textarea_field')->returnArg(1);
        Functions\when('wp_kses_post')->returnArg(1);
    }

    protected function tear_down(): void
    {
        Mockery::close();
        parent::tear_down();
    }

    public function testCreateApiResponseSuccessShape(): void
    {
        $response = (new BaseApiControllerHarness())
            ->publicCreateApiResponse(true, ['x' => 1]);

        $payload = $response->get_data();
        $this->assertTrue($payload['success']);
        $this->assertSame(['x' => 1], $payload['data']);
        $this->assertNull($payload['errors']);
        $this->assertSame(200, $response->get_status());
    }

    public function testCreateApiResponseFullShape(): void
    {
        $response = (new BaseApiControllerHarness())
            ->publicCreateApiResponse(false, ['ctx' => 'a'], ['boom'], 'fail', 422);

        $payload = $response->get_data();
        $this->assertFalse($payload['success']);
        $this->assertSame(['boom'], $payload['errors']);
        $this->assertSame('fail', $payload['message']);
        $this->assertSame(['ctx' => 'a'], $payload['data']);
        $this->assertSame(422, $response->get_status());
    }

    public function testCreateErrorResponseAlwaysReturnsHttp200(): void
    {
        // Repository convention: error responses keep HTTP 200 to avoid
        // breaking REST clients that branch on the HTTP code.
        $response = (new BaseApiControllerHarness())
            ->publicCreateErrorResponse('boom');

        $this->assertSame(200, $response->get_status());
        $payload = $response->get_data();
        $this->assertFalse($payload['success']);
        $this->assertSame(['boom'], $payload['errors']);
    }

    public function testCreateErrorResponseAcceptsArrayOfErrors(): void
    {
        $response = (new BaseApiControllerHarness())
            ->publicCreateErrorResponse(['e1', 'e2'], 'fail', ['ctx' => 1]);

        $payload = $response->get_data();
        $this->assertSame(['e1', 'e2'], $payload['errors']);
        $this->assertSame('fail', $payload['message']);
        $this->assertSame(['ctx' => 1], $payload['data']);
    }

    public function testVerifyRestNonceWithValidNonce(): void
    {
        $request = new \WP_REST_Request([], 'GET', ['X-WP-Nonce' => 'good']);

        Functions\expect('wp_verify_nonce')
            ->once()
            ->with('good', 'wp_rest')
            ->andReturn(1);

        $this->assertTrue((bool) (new BaseApiControllerHarness())->publicVerifyRestNonce($request));
    }

    public function testVerifyRestNonceWithInvalidNonce(): void
    {
        $request = new \WP_REST_Request([], 'GET', ['X-WP-Nonce' => 'bad']);

        Functions\expect('wp_verify_nonce')
            ->once()
            ->andReturn(false);

        $this->assertFalse((bool) (new BaseApiControllerHarness())->publicVerifyRestNonce($request));
    }

    public function testCheckAdminPermissionsWithNonceRequiresBothNonceAndCapability(): void
    {
        $controller = new BaseApiControllerHarness();
        $request = new \WP_REST_Request([], 'GET', ['X-WP-Nonce' => 'abc']);

        // Nonce invalid → false even if user is admin.
        Functions\expect('wp_verify_nonce')->once()->andReturn(false);
        Functions\expect('current_user_can')->never();
        $this->assertFalse((bool) $controller->check_admin_permissions_with_nonce($request));
    }

    public function testCheckAdminPermissionsWithNonceFailsWithoutCapability(): void
    {
        $controller = new BaseApiControllerHarness();
        $request = new \WP_REST_Request([], 'GET', ['X-WP-Nonce' => 'abc']);

        Functions\expect('wp_verify_nonce')->once()->andReturn(1);
        Functions\expect('current_user_can')->once()->with('manage_options')->andReturn(false);

        $this->assertFalse((bool) $controller->check_admin_permissions_with_nonce($request));
    }

    public function testCheckAdminPermissionsWithNoncePassesForAdmin(): void
    {
        $controller = new BaseApiControllerHarness();
        $request = new \WP_REST_Request([], 'GET', ['X-WP-Nonce' => 'abc']);

        Functions\expect('wp_verify_nonce')->once()->andReturn(1);
        Functions\expect('current_user_can')->once()->andReturn(true);

        $this->assertTrue((bool) $controller->check_admin_permissions_with_nonce($request));
    }

    public function testCheckRateLimitFirstHitInitializesCounter(): void
    {
        Functions\when('get_current_user_id')->justReturn(42);

        $result = (new BaseApiControllerHarness())->publicCheckRateLimit();

        $this->assertTrue($result);
        $this->assertSame(1, LBFA_Transient_Helper::$__cache['rate_limit_42']);
    }

    public function testCheckRateLimitBelowThresholdIncrements(): void
    {
        Functions\when('get_current_user_id')->justReturn(42);
        LBFA_Transient_Helper::$__cache['rate_limit_42'] = 5;

        $result = (new BaseApiControllerHarness())->publicCheckRateLimit();

        $this->assertTrue($result);
        $this->assertSame(6, LBFA_Transient_Helper::$__cache['rate_limit_42']);
    }

    public function testCheckRateLimitAboveThresholdReturnsWpError(): void
    {
        Functions\when('get_current_user_id')->justReturn(42);
        LBFA_Config_Helper::$__overrides['api.rate_limit'] = 10;
        LBFA_Transient_Helper::$__cache['rate_limit_42'] = 10;

        $result = (new BaseApiControllerHarness())->publicCheckRateLimit();

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('rate_limit_exceeded', $result->code);
    }

    public function testSanitizeRoutesByType(): void
    {
        $h = new BaseApiControllerHarness();

        $this->assertSame('a@b.io', $h->publicSanitize('a@b.io', ['type' => 'email']));
        $this->assertSame('https://x', $h->publicSanitize('https://x', ['type' => 'url']));
        $this->assertSame("text", $h->publicSanitize('text', ['type' => 'textarea']));
        $this->assertSame('<b>x</b>', $h->publicSanitize('<b>x</b>', ['type' => 'html']));
        $this->assertSame(7, $h->publicSanitize('7', ['type' => 'int']));
        $this->assertTrue($h->publicSanitize('1', ['type' => 'bool']));
        $this->assertFalse($h->publicSanitize('', ['type' => 'bool']));
        $this->assertSame('plain', $h->publicSanitize('plain'));
    }

    public function testSanitizeRecursesIntoArrays(): void
    {
        $h = new BaseApiControllerHarness();
        $out = $h->publicSanitize(['a' => 'x', 'b' => ['c' => 'y']]);
        $this->assertSame(['a' => 'x', 'b' => ['c' => 'y']], $out);
    }
}
