<?php
/**
 * Unit tests for LBFA_Base_Shortcode.
 *
 * Uses BaseShortcodeHarness to expose the protected methods. Coverage:
 * handle (defaults merge), get_language (explicit attr), get_document_url,
 * generate_error_notice, generate_iframe, generate_html_content,
 * get_html_content (cache hit/miss/empty url), generate_common_output
 * (no url → notice, html=false → iframe, html=true with content → html
 * block, html=true without content → fallback iframe).
 */

declare(strict_types=1);

namespace LegalBlink\Tests\Unit;

use Brain\Monkey\Functions;
use LBFA_Base_Shortcode;
use LBFA_Option_Helper;
use LBFA_Transient_Helper;
use LegalBlink\Tests\TestCase;
use Mockery;
use WP_Error;

require_once dirname(__DIR__, 2) . '/classes/shortcode/class-lbfa-base-shortcode.php';

/**
 * Concrete subclass exposing the protected helpers and short-circuiting the
 * abstract `generate_output`.
 */
class BaseShortcodeHarness extends LBFA_Base_Shortcode
{
    protected $tag = 'LBFA_TEST_HARNESS';
    protected $policy_type = 'cookie_policy';

    public string $configuredTitle = 'Cookie Policy';
    public string $configuredNotConfigured = 'Not configured.';
    public string $configuredFallback = 'Visualizza qui';

    protected function generate_output($attrs, $content)
    {
        return $this->generate_common_output(
            $attrs,
            $content,
            $this->policy_type,
            $this->configuredTitle,
            $this->configuredNotConfigured,
            $this->configuredFallback
        );
    }

    public function publicGetLanguage($attrs) { return $this->get_language($attrs); }
    public function publicGetDocumentUrl(string $type, string $lang) { return $this->get_document_url($type, $lang); }
    public function publicGenerateErrorNotice(string $title, string $message, string $type) { return $this->generate_error_notice($title, $message, $type); }
    public function publicGenerateIframe(string $url, array $attrs, string $title, string $fallback, string $type) { return $this->generate_iframe($url, $attrs, $title, $fallback, $type); }
    public function publicGenerateHtmlContent(string $html, string $type) { return $this->generate_html_content($html, $type); }
    public function publicGetHtmlContent(string $type, string $lang) { return $this->get_html_content($type, $lang); }
    public function publicHandle(array $attrs, $content = null) { return $this->handle($attrs, $content); }
}

class BaseShortcodeTest extends TestCase
{
    protected function set_up(): void
    {
        parent::set_up();

        LBFA_Option_Helper::reset();
        LBFA_Transient_Helper::reset();

        Functions\when('__')->returnArg(1);
        Functions\when('add_shortcode')->justReturn(true);
        Functions\when('shortcode_atts')->alias(static fn ($defaults, $attrs) => array_merge($defaults, (array) $attrs));
        Functions\when('sanitize_text_field')->returnArg(1);
        Functions\when('esc_attr')->returnArg(1);
        Functions\when('esc_html')->returnArg(1);
        Functions\when('esc_url')->returnArg(1);
        Functions\when('is_wp_error')->alias(static fn ($v) => $v instanceof WP_Error);
        Functions\when('wp_remote_retrieve_response_code')->alias(static fn ($r) => $r['response']['code'] ?? 0);
        Functions\when('wp_remote_retrieve_body')->alias(static fn ($r) => $r['body'] ?? '');
        Functions\when('wp_kses')->alias(static fn ($content, $allowed) => $content);
        Functions\when('wp_kses_allowed_html')->alias(static fn ($context) => []);
    }

    protected function tear_down(): void
    {
        Mockery::close();
        parent::tear_down();
    }

    public function testHandleMergesDefaultAttributes(): void
    {
        $h = new BaseShortcodeHarness();
        // No URL configured → generate_common_output must return the
        // "not configured" notice via generate_error_notice.
        $output = $h->publicHandle(['lang' => 'it']);
        $this->assertStringContainsString('lbp-cookie_policy-policy-notice', $output);
    }

    public function testGetLanguageReturnsExplicitAttr(): void
    {
        $this->assertSame('en', (new BaseShortcodeHarness())->publicGetLanguage(['lang' => 'en']));
    }

    public function testGetDocumentUrlReadsLanguageOption(): void
    {
        LBFA_Option_Helper::$__options['documents_cookie_policy_html_url_it'] = 'https://x/cp.html';

        $h = new BaseShortcodeHarness();
        $this->assertSame('https://x/cp.html', $h->publicGetDocumentUrl('cookie_policy', 'it'));
    }

    public function testGenerateErrorNoticeIncludesTitleAndMessage(): void
    {
        $html = (new BaseShortcodeHarness())
            ->publicGenerateErrorNotice('Privacy', 'Configura URL', 'privacy_policy');

        $this->assertStringContainsString('lbp-privacy_policy-policy-notice', $html);
        $this->assertStringContainsString('Privacy', $html);
        $this->assertStringContainsString('Configura URL', $html);
    }

    public function testGenerateIframeAppliesSizingAndStyle(): void
    {
        $html = (new BaseShortcodeHarness())->publicGenerateIframe(
            'https://x/doc.html',
            ['height' => '300px', 'width' => '50%', 'style' => 'border-radius:4px;'],
            'Doc',
            'fallback',
            'cookie_policy'
        );

        $this->assertStringContainsString('https://x/doc.html', $html);
        $this->assertStringContainsString('height:300px', $html);
        $this->assertStringContainsString('width:50%', $html);
        $this->assertStringContainsString('border-radius:4px;', $html);
        $this->assertStringContainsString('lbp-cookie_policy-policy-container', $html);
    }

    public function testGenerateHtmlContentWrapsInDiv(): void
    {
        $html = (new BaseShortcodeHarness())
            ->publicGenerateHtmlContent('<p>Hello</p>', 'cookie_policy');

        $this->assertStringContainsString('lbp-cookie_policy-policy-html-container', $html);
        $this->assertStringContainsString('<p>Hello</p>', $html);
    }

    public function testGetHtmlContentCacheHit(): void
    {
        LBFA_Transient_Helper::$__cache['documents_cookie_policy_html_content_it'] = '<p>cached</p>';
        LBFA_Option_Helper::$__options['documents_cookie_policy_html_url_it'] = 'https://x/cp.html';

        Functions\expect('wp_remote_get')->never();

        $this->assertSame('<p>cached</p>', (new BaseShortcodeHarness())->publicGetHtmlContent('cookie_policy', 'it'));
    }

    public function testGetHtmlContentCacheMissFetchesAndCaches(): void
    {
        LBFA_Option_Helper::$__options = [
            'documents_cookie_policy_html_url_it' => 'https://x/cp.html',
            'cache_duration' => 1,
        ];

        Functions\expect('wp_remote_get')
            ->once()
            ->with('https://x/cp.html', Mockery::type('array'))
            ->andReturn(['response' => ['code' => 200], 'body' => '<style>.bad{color:red}</style><p>Hello</p>']);

        $content = (new BaseShortcodeHarness())->publicGetHtmlContent('cookie_policy', 'it');

        // <style> blocks must be stripped entirely (per shortcode contract).
        $this->assertStringNotContainsString('<style', $content);
        $this->assertStringContainsString('<p>Hello</p>', $content);
        $this->assertSame($content, LBFA_Transient_Helper::$__cache['documents_cookie_policy_html_content_it']);
    }

    public function testGetHtmlContentStripsEmptyAndBrOnlyParagraphs(): void
    {
        LBFA_Option_Helper::$__options = [
            'documents_cookie_policy_html_url_it' => 'https://x/cp.html',
            'cache_duration' => 1,
        ];

        $upstream = '<p>First</p>'
            . '<p></p>'
            . '<p>   </p>'
            . '<p>&nbsp;</p>'
            . '<p>&#160;</p>'
            . '<p><br></p>'
            . '<p><br/></p>'
            . '<p><br /></p>'
            . '<p>  <br>  </p>'
            . '<p class="x"></p>'
            . '<p>Last</p>'
            . '<p>Keep <br> me</p>';

        Functions\expect('wp_remote_get')
            ->once()
            ->andReturn(['response' => ['code' => 200], 'body' => $upstream]);

        $content = (new BaseShortcodeHarness())->publicGetHtmlContent('cookie_policy', 'it');

        // Empty / whitespace / br-only paragraphs are gone.
        $this->assertStringNotContainsString('<p></p>', $content);
        $this->assertStringNotContainsString('<p>&nbsp;</p>', $content);
        $this->assertStringNotContainsString('<p>&#160;</p>', $content);
        $this->assertStringNotContainsString('<p><br></p>', $content);
        $this->assertStringNotContainsString('<p><br/></p>', $content);
        $this->assertStringNotContainsString('<p><br /></p>', $content);
        $this->assertStringNotContainsString('<p class="x"></p>', $content);
        // Meaningful paragraphs survive — including those with a <br> mixed
        // with real text content.
        $this->assertStringContainsString('First', $content);
        $this->assertStringContainsString('Last', $content);
        $this->assertStringContainsString('Keep', $content);
        $this->assertStringContainsString('me', $content);
    }

    public function testGetHtmlContentStripsScriptBlocksToAvoidJavascriptLeakingAsText(): void
    {
        // Upstream HTML may include third-party scripts (e.g. Cloudflare
        // challenge loader). wp_kses would strip the <script> tag but leave
        // the inner JS as plain text in the page. Strip the whole block
        // (tag + content) to avoid the leak.
        LBFA_Option_Helper::$__options = [
            'documents_privacy_policy_html_url_it' => 'https://x/pp.html',
            'cache_duration' => 1,
        ];

        $upstream = '<p>Privacy</p>'
            . '<script>window.__CF$cv$params={r:\'abc\'};var a=document.createElement("script");a.src="/cdn-cgi/...";</script>'
            . '<script src="/foo.js"></script>'
            . '<p>After</p>';

        Functions\expect('wp_remote_get')
            ->once()
            ->andReturn(['response' => ['code' => 200], 'body' => $upstream]);

        $content = (new BaseShortcodeHarness())->publicGetHtmlContent('privacy_policy', 'it');

        $this->assertStringNotContainsString('<script', $content);
        $this->assertStringNotContainsString('CF$cv$params', $content);
        $this->assertStringNotContainsString('cdn-cgi', $content);
        $this->assertStringContainsString('<p>Privacy</p>', $content);
        $this->assertStringContainsString('<p>After</p>', $content);
    }

    public function testGetHtmlContentReturnsNullWhenUrlMissing(): void
    {
        Functions\expect('wp_remote_get')->never();
        $this->assertNull((new BaseShortcodeHarness())->publicGetHtmlContent('cookie_policy', 'it'));
    }

    public function testGenerateCommonOutputNoUrlReturnsErrorNotice(): void
    {
        $output = (new BaseShortcodeHarness())->publicHandle(['lang' => 'it']);
        $this->assertStringContainsString('lbp-cookie_policy-policy-notice', $output);
    }

    public function testGenerateCommonOutputIframeWhenHtmlFalse(): void
    {
        LBFA_Option_Helper::$__options['documents_cookie_policy_html_url_it'] = 'https://x/cp.html';

        $output = (new BaseShortcodeHarness())->publicHandle(['lang' => 'it', 'html' => 'false']);
        $this->assertStringContainsString('<iframe', $output);
        $this->assertStringContainsString('https://x/cp.html', $output);
    }

    public function testGenerateCommonOutputHtmlBlockWhenHtmlTrueAndContentValid(): void
    {
        LBFA_Option_Helper::$__options['documents_cookie_policy_html_url_it'] = 'https://x/cp.html';
        LBFA_Transient_Helper::$__cache['documents_cookie_policy_html_content_it'] = '<p>Inline</p>';

        $output = (new BaseShortcodeHarness())->publicHandle(['lang' => 'it', 'html' => 'true']);
        $this->assertStringContainsString('lbp-cookie_policy-policy-html-container', $output);
        $this->assertStringContainsString('<p>Inline</p>', $output);
        $this->assertStringNotContainsString('<iframe', $output);
    }

    public function testGenerateCommonOutputFallsBackToIframeWhenHtmlContentEmpty(): void
    {
        LBFA_Option_Helper::$__options = [
            'documents_cookie_policy_html_url_it' => 'https://x/cp.html',
            'cache_duration' => 1,
        ];

        Functions\expect('wp_remote_get')
            ->once()
            ->andReturn(['response' => ['code' => 500], 'body' => '']);

        $output = (new BaseShortcodeHarness())->publicHandle(['lang' => 'it', 'html' => 'true']);
        $this->assertStringContainsString('<iframe', $output);
    }
}
