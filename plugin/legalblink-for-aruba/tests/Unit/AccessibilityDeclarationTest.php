<?php
/**
 * Unit tests for LBFA_Accessibility_API_Controller — declaration surface.
 *
 * Validates GET /accessibility/declaration (cache + missing creds + remote
 * fetch with option persistence + error mapping + normalization) and POST
 * /accessibility/declaration/update-page (page id 0 → clear options, valid
 * page id → write shortcode and persist options).
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
        public function get_method(): string { return 'POST'; }
        public function get_header(string $key): string { return ''; }
    }
}

class AccessibilityDeclarationTest extends TestCase
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
    }

    protected function tear_down(): void
    {
        Mockery::close();
        parent::tear_down();
    }

    public function testRegisterRoutesRegistersDeclarationGetAndUpdatePage(): void
    {
        Functions\expect('register_rest_route')
            ->twice()
            ->with(
                'lbfa/v1',
                Mockery::anyOf('/accessibility/declaration', '/accessibility/declaration/update-page'),
                Mockery::type('array')
            );

        (new LBFA_Accessibility_API_Controller())->register_routes();
    }

    public function testGetDeclarationReturnsErrorWhenJwtMissing(): void
    {
        Functions\when('LBFA_Option_Helper::getOption')->justReturn('');
        Functions\when('LBFA_Option_Helper::getLanguageOption')->justReturn('0');
        Functions\when('LBFA_Transient_Helper::get')->justReturn(false);
        Functions\expect('wp_remote_get')->never();

        $response = (new LBFA_Accessibility_API_Controller())->get_declaration();

        $payload = $response->get_data();
        $this->assertFalse($payload['success']);
        $this->assertNotEmpty($payload['errors']);
    }

    public function testGetDeclarationCacheHitSkipsHttpAndHydratesPageOptions(): void
    {
        $cached = $this->normalizedFixture();

        Functions\when('LBFA_Option_Helper::getOption')->justReturn('jwt-token');
        Functions\when('LBFA_Transient_Helper::get')->justReturn($cached);
        Functions\when('LBFA_Option_Helper::getLanguageOption')->alias(static function ($key, $iso, $default = '') {
            if ($key === 'page_accessibility_declaration_id' && $iso === 'it') return '42';
            if ($key === 'page_accessibility_declaration_use_html_snippet' && $iso === 'it') return true;
            return $default;
        });
        Functions\expect('wp_remote_get')->never();

        $response = (new LBFA_Accessibility_API_Controller())->get_declaration();
        $payload = $response->get_data();

        $this->assertTrue($payload['success']);
        $this->assertSame('42', $payload['data']['document']['languages']['it']['pageId']);
        $this->assertTrue($payload['data']['document']['languages']['it']['useHtmlSnippet']);
    }

    public function testGetDeclarationFetchesAndPersistsLanguageUrls(): void
    {
        Functions\when('LBFA_Option_Helper::getOption')->justReturn('jwt-token');
        Functions\when('LBFA_Transient_Helper::get')->justReturn(false);
        Functions\when('LBFA_Option_Helper::getLanguageOption')->justReturn('0');
        Functions\when('LBFA_Transient_Helper::set')->justReturn(true);

        Functions\expect('wp_remote_get')
            ->once()
            ->with(
                'https://backend.example.test/integrations/wordpress/accessibility/declaration',
                Mockery::on(static fn ($args) => ($args['headers']['Authorization'] ?? '') === 'Bearer jwt-token')
            )
            ->andReturn([
                'response' => ['code' => 200],
                'body' => json_encode($this->normalizedFixture()),
            ]);

        Functions\expect('LBFA_Option_Helper::setLanguageOption')
            ->atLeast()
            ->once()
            ->with('documents_accessibility_declaration_html_url', 'https://example.test/it.html', 'it')
            ->andReturn(true);

        $response = (new LBFA_Accessibility_API_Controller())->get_declaration();
        $payload = $response->get_data();

        $this->assertTrue($payload['success']);
        $this->assertTrue($payload['data']['available']);
    }

    public function testGetDeclarationMapsBackendErrorToErrorResponse(): void
    {
        Functions\when('LBFA_Option_Helper::getOption')->justReturn('jwt-token');
        Functions\when('LBFA_Option_Helper::getLanguageOption')->justReturn('0');
        Functions\when('LBFA_Transient_Helper::get')->justReturn(false);

        Functions\expect('wp_remote_get')
            ->once()
            ->andReturn(['response' => ['code' => 502], 'body' => '{}']);
        Functions\expect('LBFA_Transient_Helper::set')->never();

        $payload = (new LBFA_Accessibility_API_Controller())->get_declaration()->get_data();
        $this->assertFalse($payload['success']);
        $this->assertNotEmpty($payload['errors']);
    }

    public function testNormalizeDeclarationReturnsUnavailableWhenAvailableIsFalse(): void
    {
        $normalized = (new LBFA_Accessibility_API_Controller())
            ->normalize_declaration(['available' => false, 'document' => null]);

        $this->assertFalse($normalized['available']);
        $this->assertNull($normalized['document']);
    }

    public function testNormalizeDeclarationDropsInvalidLanguageCodes(): void
    {
        $normalized = (new LBFA_Accessibility_API_Controller())->normalize_declaration([
            'available' => true,
            'source' => 'canonical',
            'document' => [
                'id' => 'doc1',
                'slug' => 'declarationaccessibility',
                'languages' => [
                    'it' => ['url' => ['html' => 'h', 'pdf' => 'p']],
                    'INVALID' => ['url' => ['html' => 'h', 'pdf' => 'p']],
                    'en' => ['url' => ['html' => 'h2', 'pdf' => 'p2']],
                ],
            ],
        ]);

        $this->assertArrayHasKey('it', $normalized['document']['languages']);
        $this->assertArrayHasKey('en', $normalized['document']['languages']);
        $this->assertArrayNotHasKey('INVALID', $normalized['document']['languages']);
    }

    public function testUpdateDeclarationPageWithZeroPageIdClearsOptions(): void
    {
        Functions\expect('LBFA_Option_Helper::setLanguageOption')
            ->twice()
            ->andReturn(true);
        Functions\expect('get_post')->never();
        Functions\expect('wp_update_post')->never();

        $request = new WP_REST_Request([
            'page_id' => 0,
            'use_html_snippet' => false,
            'language' => 'it',
        ]);

        $payload = (new LBFA_Accessibility_API_Controller())->update_declaration_page($request)->get_data();
        $this->assertTrue($payload['success']);
    }

    public function testUpdateDeclarationPageWritesShortcodeAndPersistsOptions(): void
    {
        Functions\when('get_post')->justReturn((object) ['ID' => 99]);
        Functions\when('wp_update_post')->justReturn(99);
        Functions\expect('LBFA_Option_Helper::setLanguageOption')
            ->twice()
            ->andReturn(true);

        $request = new WP_REST_Request([
            'page_id' => 99,
            'use_html_snippet' => true,
            'language' => 'en',
        ]);

        $payload = (new LBFA_Accessibility_API_Controller())->update_declaration_page($request)->get_data();
        $this->assertTrue($payload['success']);
    }

    public function testUpdateDeclarationPageRejectsMissingPage(): void
    {
        Functions\when('get_post')->justReturn(null);
        Functions\expect('wp_update_post')->never();

        $request = new WP_REST_Request([
            'page_id' => 9999,
            'use_html_snippet' => false,
            'language' => 'it',
        ]);

        $payload = (new LBFA_Accessibility_API_Controller())->update_declaration_page($request)->get_data();
        $this->assertFalse($payload['success']);
        $this->assertNotEmpty($payload['errors']);
    }

    private function normalizedFixture(): array
    {
        return [
            'available' => true,
            'source' => 'canonical',
            'document' => [
                'id' => 'doc-1',
                'slug' => 'declarationaccessibility',
                'createdAt' => '2026-01-01T00:00:00Z',
                'updatedAt' => '2026-04-01T00:00:00Z',
                'languages' => [
                    'it' => ['url' => ['html' => 'https://example.test/it.html', 'pdf' => 'https://example.test/it.pdf']],
                    'en' => ['url' => ['html' => 'https://example.test/en.html', 'pdf' => 'https://example.test/en.pdf']],
                ],
            ],
        ];
    }
}
