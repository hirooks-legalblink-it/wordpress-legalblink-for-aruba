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
use LBFA_Config_Helper;
use LBFA_Option_Helper;
use LBFA_Transient_Helper;
use LegalBlink\Tests\TestCase;
use Mockery;
use WP_Error;
use WP_REST_Request;

require_once dirname(__DIR__, 2) . '/classes/controller/api/class-lbfa-accessibility-api-controller.php';

class AccessibilityDeclarationTest extends TestCase
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
    }

    protected function tear_down(): void
    {
        Mockery::close();
        parent::tear_down();
    }

    public function testRegisterRoutesRegistersDeclarationGetAndUpdatePage(): void
    {
        Functions\expect('register_rest_route')
            ->atLeast()
            ->times(2)
            ->with(
                'lbfa/v1',
                Mockery::anyOf('/accessibility/declaration', '/accessibility/declaration/update-page', '/accessibility/widget'),
                Mockery::type('array')
            );

        (new LBFA_Accessibility_API_Controller())->register_routes();
        $this->addToAssertionCount(1);
    }

    public function testGetDeclarationReturnsErrorWhenJwtMissing(): void
    {
        Functions\expect('wp_remote_get')->never();

        $payload = (new LBFA_Accessibility_API_Controller())->get_declaration()->get_data();
        $this->assertFalse($payload['success']);
        $this->assertNotEmpty($payload['errors']);
    }

    public function testGetDeclarationCacheHitSkipsHttpAndHydratesPageOptions(): void
    {
        $cached = $this->normalizedFixture();

        LBFA_Option_Helper::$__options = [
            'jwt_token' => 'jwt-token',
            'page_accessibility_declaration_id_it' => '42',
            'page_accessibility_declaration_use_html_snippet_it' => true,
        ];
        LBFA_Transient_Helper::$__cache['accessibility_declaration'] = $cached;
        Functions\expect('wp_remote_get')->never();

        $payload = (new LBFA_Accessibility_API_Controller())->get_declaration()->get_data();

        $this->assertTrue($payload['success']);
        $this->assertSame('42', $payload['data']['document']['languages']['it']['pageId']);
        $this->assertTrue($payload['data']['document']['languages']['it']['useHtmlSnippet']);
    }

    public function testGetDeclarationFetchesAndPersistsLanguageUrls(): void
    {
        LBFA_Option_Helper::$__options['jwt_token'] = 'jwt-token';

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

        $payload = (new LBFA_Accessibility_API_Controller())->get_declaration()->get_data();

        $this->assertTrue($payload['success']);
        $this->assertTrue($payload['data']['available']);
        // URL must have been persisted to the language-specific option.
        $this->assertSame(
            'https://example.test/it.html',
            LBFA_Option_Helper::getLanguageOption('documents_accessibility_declaration_html_url', 'it')
        );
        $this->assertSame(
            'https://example.test/en.html',
            LBFA_Option_Helper::getLanguageOption('documents_accessibility_declaration_html_url', 'en')
        );
        // And the normalized payload cached for next call.
        $this->assertArrayHasKey('accessibility_declaration', LBFA_Transient_Helper::$__cache);
    }

    public function testGetDeclarationMapsBackendErrorToErrorResponse(): void
    {
        LBFA_Option_Helper::$__options['jwt_token'] = 'jwt-token';

        Functions\expect('wp_remote_get')
            ->once()
            ->andReturn(['response' => ['code' => 502], 'body' => '{}']);

        $payload = (new LBFA_Accessibility_API_Controller())->get_declaration()->get_data();
        $this->assertFalse($payload['success']);
        $this->assertNotEmpty($payload['errors']);
        $this->assertArrayNotHasKey('accessibility_declaration', LBFA_Transient_Helper::$__cache);
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
        LBFA_Option_Helper::$__options['page_accessibility_declaration_id_it'] = 99;
        LBFA_Option_Helper::$__options['page_accessibility_declaration_use_html_snippet_it'] = true;
        Functions\expect('get_post')->never();
        Functions\expect('wp_update_post')->never();

        $request = new WP_REST_Request([
            'page_id' => 0,
            'use_html_snippet' => false,
            'language' => 'it',
        ]);

        $payload = (new LBFA_Accessibility_API_Controller())->update_declaration_page($request)->get_data();
        $this->assertTrue($payload['success']);
        $this->assertSame(0, LBFA_Option_Helper::getLanguageOption('page_accessibility_declaration_id', 'it'));
        $this->assertFalse(LBFA_Option_Helper::getLanguageOption('page_accessibility_declaration_use_html_snippet', 'it'));
    }

    public function testUpdateDeclarationPageWritesShortcodeAndPersistsOptions(): void
    {
        Functions\when('get_post')->justReturn((object) ['ID' => 99]);
        Functions\when('wp_update_post')->justReturn(99);

        $request = new WP_REST_Request([
            'page_id' => 99,
            'use_html_snippet' => true,
            'language' => 'en',
        ]);

        $payload = (new LBFA_Accessibility_API_Controller())->update_declaration_page($request)->get_data();
        $this->assertTrue($payload['success']);
        $this->assertSame(99, LBFA_Option_Helper::getLanguageOption('page_accessibility_declaration_id', 'en'));
        $this->assertTrue(LBFA_Option_Helper::getLanguageOption('page_accessibility_declaration_use_html_snippet', 'en'));
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
