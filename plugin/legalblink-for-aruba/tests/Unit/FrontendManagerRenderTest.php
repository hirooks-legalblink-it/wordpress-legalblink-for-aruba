<?php
/**
 * Unit tests for LBFA_Frontend_Manager render paths.
 *
 * Validates render_cookie_banner (gating, cache key segregation v1/v2,
 * cache miss → fetch + persist), render_accessibility_widget (toggle off,
 * jwt missing, cache hit + warnings/html branches), and
 * fetch_accessibility_widget_payload (200 normalization + 5xx).
 *
 * Capture stdout via ob_start / ob_get_clean to assert on the echoed
 * snippet without coupling to wp_kses internals.
 */

declare(strict_types=1);

namespace LegalBlink\Tests\Unit;

use Brain\Monkey\Functions;
use LBFA_Frontend_Manager;
use LBFA_Option_Helper;
use LBFA_Transient_Helper;
use LegalBlink\Tests\TestCase;
use Mockery;
use ReflectionClass;
use WP_Error;

require_once dirname(__DIR__, 2) . '/classes/controller/api/class-lbfa-capability-api-controller.php';
require_once dirname(__DIR__, 2) . '/classes/class-lbfa-frontend-manager.php';

class FrontendManagerRenderTest extends TestCase
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
        Functions\when('wp_json_encode')->alias(static fn ($v) => json_encode($v));
        // wp_kses passes through verbatim — we only assert the snippet was
        // echoed at all, the kses whitelist itself is WP responsibility.
        Functions\when('wp_kses')->alias(static fn ($content, $allowed) => $content);
    }

    protected function tear_down(): void
    {
        Mockery::close();
        parent::tear_down();
    }

    private function makeManager(): LBFA_Frontend_Manager
    {
        $reflect = new ReflectionClass(LBFA_Frontend_Manager::class);
        return $reflect->newInstanceWithoutConstructor();
    }

    private function captureRender(callable $callback): string
    {
        ob_start();
        $callback();
        return (string) ob_get_clean();
    }

    /* ---------- render_cookie_banner ---------- */

    public function testGetScriptAllowedHtmlIncludesV2CookieBannerDataAttributes(): void
    {
        // wp_kses has no wildcard for `data-*`; the v2 cookie banner snippet
        // (data-license-id / data-blocking-mode / data-consent-mode /
        // data-tcf-enabled) requires each attribute to be enumerated, or
        // the CMP loader receives a stripped <script> and never boots.
        $allowed = LBFA_Frontend_Manager::get_script_allowed_html();

        $this->assertArrayHasKey('script', $allowed);
        $this->assertArrayHasKey('data-license-id', $allowed['script']);
        $this->assertArrayHasKey('data-blocking-mode', $allowed['script']);
        $this->assertArrayHasKey('data-consent-mode', $allowed['script']);
        $this->assertArrayHasKey('data-tcf-enabled', $allowed['script']);
        // Base attrs needed for the accessibility widget snippet.
        $this->assertArrayHasKey('src', $allowed['script']);
        $this->assertArrayHasKey('defer', $allowed['script']);
    }

    public function testRenderCookieBannerSkipsWhenDisabled(): void
    {
        // cookie_banner_enabled defaults to false → method returns early.
        Functions\expect('wp_remote_get')->never();
        $output = $this->captureRender(fn () => $this->makeManager()->render_cookie_banner());
        $this->assertSame('', $output);
    }

    public function testRenderCookieBannerEchoesCachedSnippetV1(): void
    {
        LBFA_Option_Helper::$__options['cookie_banner_enabled'] = true;
        LBFA_Transient_Helper::$__cache['cookie_banner_snippet'] = '<script>cached-v1</script>';

        Functions\expect('wp_remote_get')->never();

        $output = $this->captureRender(fn () => $this->makeManager()->render_cookie_banner());
        $this->assertSame('<script>cached-v1</script>', $output);
    }

    public function testRenderCookieBannerEchoesCachedSnippetV2WhenCapabilityTrue(): void
    {
        LBFA_Option_Helper::$__options['cookie_banner_enabled'] = true;
        LBFA_Transient_Helper::$__cache['capabilities'] = [
            'features' => ['cookieBannerV2' => true],
        ];
        LBFA_Transient_Helper::$__cache['cookie_banner_snippet_v2'] = '<script>cached-v2</script>';

        Functions\expect('wp_remote_get')->never();

        $output = $this->captureRender(fn () => $this->makeManager()->render_cookie_banner());
        $this->assertSame('<script>cached-v2</script>', $output);
    }

    public function testRenderCookieBannerCacheMissFetchesAndPersistsV1(): void
    {
        LBFA_Option_Helper::$__options = [
            'cookie_banner_enabled' => true,
            'jwt_token' => 'jwt',
            'cache_duration' => 1,
        ];

        Functions\expect('wp_remote_get')
            ->once()
            ->with(
                'https://backend.example.test/integrations/wordpress/cookie-solution/embed?language=it',
                Mockery::type('array')
            )
            ->andReturn(['response' => ['code' => 200], 'body' => json_encode(['html' => '<script>fetched-v1</script>'])]);

        $output = $this->captureRender(fn () => $this->makeManager()->render_cookie_banner());

        $this->assertSame('<script>fetched-v1</script>', $output);
        $this->assertSame('<script>fetched-v1</script>', LBFA_Transient_Helper::$__cache['cookie_banner_snippet']);
    }

    public function testRenderCookieBannerCacheMissFetchesV2WhenCapabilityTrue(): void
    {
        LBFA_Option_Helper::$__options = [
            'cookie_banner_enabled' => true,
            'jwt_token' => 'jwt',
            'cache_duration' => 1,
        ];
        LBFA_Transient_Helper::$__cache['capabilities'] = [
            'features' => ['cookieBannerV2' => true],
        ];

        Functions\expect('wp_remote_get')
            ->once()
            ->with(
                'https://backend.example.test/integrations/wordpress/cookie-solution/embed-v2',
                Mockery::type('array')
            )
            ->andReturn(['response' => ['code' => 200], 'body' => json_encode(['html' => '<script>fetched-v2</script>'])]);

        $output = $this->captureRender(fn () => $this->makeManager()->render_cookie_banner());

        $this->assertSame('<script>fetched-v2</script>', $output);
        $this->assertArrayHasKey('cookie_banner_snippet_v2', LBFA_Transient_Helper::$__cache);
        $this->assertArrayNotHasKey('cookie_banner_snippet', LBFA_Transient_Helper::$__cache);
    }

    public function testFetchBannerSnippetWith5xxReturnsEmpty(): void
    {
        LBFA_Option_Helper::$__options['jwt_token'] = 'jwt';

        Functions\expect('wp_remote_get')
            ->once()
            ->andReturn(['response' => ['code' => 500], 'body' => '{}']);

        $this->assertSame('', $this->makeManager()->fetch_banner_snippet());
    }

    public function testFetchBannerSnippetWithWpErrorReturnsEmpty(): void
    {
        LBFA_Option_Helper::$__options['jwt_token'] = 'jwt';

        Functions\expect('wp_remote_get')
            ->once()
            ->andReturn(new WP_Error('http', 'fail'));

        $this->assertSame('', $this->makeManager()->fetch_banner_snippet());
    }

    /* ---------- render_accessibility_widget ---------- */

    public function testRenderAccessibilityWidgetSkipsWhenToggleOff(): void
    {
        // accessibility_widget_enabled defaults to false → early return.
        Functions\expect('wp_remote_get')->never();
        $this->assertSame('', $this->captureRender(fn () => $this->makeManager()->render_accessibility_widget()));
    }

    public function testRenderAccessibilityWidgetSkipsWhenJwtMissing(): void
    {
        LBFA_Option_Helper::$__options['accessibility_widget_enabled'] = true;
        Functions\expect('wp_remote_get')->never();
        $this->assertSame('', $this->captureRender(fn () => $this->makeManager()->render_accessibility_widget()));
    }

    public function testRenderAccessibilityWidgetEchoesValidSnippet(): void
    {
        LBFA_Option_Helper::$__options = [
            'accessibility_widget_enabled' => true,
            'jwt_token' => 'jwt',
            'cache_duration' => 1,
        ];
        LBFA_Transient_Helper::$__cache['accessibility_widget_html'] = '<script>aw</script>';

        Functions\expect('wp_remote_get')->never();

        $output = $this->captureRender(fn () => $this->makeManager()->render_accessibility_widget());
        $this->assertSame('<script>aw</script>', $output);
    }

    public function testRenderAccessibilityWidgetSkipsWhenCachedHtmlEmpty(): void
    {
        // Empty-string cache hit is the deliberate "backend says no widget
        // today" signal — render bails without re-hitting the API.
        LBFA_Option_Helper::$__options = ['accessibility_widget_enabled' => true, 'jwt_token' => 'jwt'];
        LBFA_Transient_Helper::$__cache['accessibility_widget_html'] = '';

        Functions\expect('wp_remote_get')->never();

        $this->assertSame('', $this->captureRender(fn () => $this->makeManager()->render_accessibility_widget()));
    }

    public function testRenderAccessibilityWidgetCacheMissWithWarningsCachesEmpty(): void
    {
        // Cache miss → backend returns warnings → resolve to empty string,
        // cache the empty string, render bails.
        LBFA_Option_Helper::$__options = ['accessibility_widget_enabled' => true, 'jwt_token' => 'jwt'];

        Functions\expect('wp_remote_get')
            ->once()
            ->andReturn([
                'response' => ['code' => 200],
                'body' => json_encode([
                    'available' => true,
                    'configured' => true,
                    'domain' => 'x',
                    'html' => '<script></script>',
                    'warnings' => ['domain_mismatch'],
                ]),
            ]);

        $this->assertSame('', $this->captureRender(fn () => $this->makeManager()->render_accessibility_widget()));
        $this->assertArrayHasKey('accessibility_widget_html', LBFA_Transient_Helper::$__cache);
        $this->assertSame('', LBFA_Transient_Helper::$__cache['accessibility_widget_html']);
    }

    public function testRenderAccessibilityWidgetCacheMissWithEmptyHtmlCachesEmpty(): void
    {
        LBFA_Option_Helper::$__options = ['accessibility_widget_enabled' => true, 'jwt_token' => 'jwt'];

        Functions\expect('wp_remote_get')
            ->once()
            ->andReturn([
                'response' => ['code' => 200],
                'body' => json_encode([
                    'available' => true,
                    'configured' => true,
                    'domain' => 'x',
                    'html' => '',
                    'warnings' => [],
                ]),
            ]);

        $this->assertSame('', $this->captureRender(fn () => $this->makeManager()->render_accessibility_widget()));
        $this->assertSame('', LBFA_Transient_Helper::$__cache['accessibility_widget_html']);
    }

    public function testRenderAccessibilityWidgetCacheMissFetchesAndPersists(): void
    {
        LBFA_Option_Helper::$__options = [
            'accessibility_widget_enabled' => true,
            'jwt_token' => 'jwt',
            'cache_duration' => 1,
        ];

        Functions\expect('wp_remote_get')
            ->once()
            ->with(
                'https://backend.example.test/integrations/wordpress/accessibility/widget',
                Mockery::type('array')
            )
            ->andReturn([
                'response' => ['code' => 200],
                'body' => json_encode([
                    'available' => true,
                    'configured' => true,
                    'domain' => 'site.com',
                    'html' => '<script>fetch</script>',
                    'warnings' => [],
                ]),
            ]);

        $output = $this->captureRender(fn () => $this->makeManager()->render_accessibility_widget());

        $this->assertSame('<script>fetch</script>', $output);
        $this->assertSame('<script>fetch</script>', LBFA_Transient_Helper::$__cache['accessibility_widget_html']);
    }

    /* ---------- fetch_accessibility_widget_payload ---------- */

    public function testFetchAccessibilityWidgetPayload200ReturnsNormalized(): void
    {
        LBFA_Option_Helper::$__options['jwt_token'] = 'jwt';

        Functions\expect('wp_remote_get')
            ->once()
            ->andReturn([
                'response' => ['code' => 200],
                'body' => json_encode([
                    'available' => true,
                    'configured' => true,
                    'domain' => 'site.com',
                    'html' => '<script></script>',
                    'warnings' => ['domain_mismatch'],
                ]),
            ]);

        $payload = $this->makeManager()->fetch_accessibility_widget_payload();

        $this->assertSame('site.com', $payload['domain']);
        $this->assertSame(['domain_mismatch'], $payload['warnings']);
    }

    public function testFetchAccessibilityWidgetPayload5xxReturnsNull(): void
    {
        LBFA_Option_Helper::$__options['jwt_token'] = 'jwt';

        Functions\expect('wp_remote_get')
            ->once()
            ->andReturn(['response' => ['code' => 503], 'body' => '{}']);

        $this->assertNull($this->makeManager()->fetch_accessibility_widget_payload());
    }

    public function testFetchAccessibilityWidgetPayloadMissingJwtReturnsNull(): void
    {
        Functions\expect('wp_remote_get')->never();
        $this->assertNull($this->makeManager()->fetch_accessibility_widget_payload());
    }
}
