<?php
/**
 * Unit tests for LBFA_Document_API_Controller (legacy GDPR documents).
 *
 * Validates GET /documents (cache + remote fetch + parse_documents allowlist
 * + per-language URL persistence + pageId/useHtmlSnippet hydration), POST
 * /documents/update-page (page_id=0 clears, valid page writes shortcode),
 * GET /pages.
 */

declare(strict_types=1);

namespace LegalBlink\Tests\Unit;

use Brain\Monkey\Functions;
use LBFA_Config_Helper;
use LBFA_Document_API_Controller;
use LBFA_Option_Helper;
use LBFA_Transient_Helper;
use LegalBlink\Tests\TestCase;
use Mockery;
use WP_Error;
use WP_REST_Request;

require_once dirname(__DIR__, 2) . '/classes/controller/api/class-lbfa-document-api-controller.php';

class DocumentApiControllerTest extends TestCase
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
        Functions\when('wp_json_encode')->alias(static fn ($v) => json_encode($v));
        Functions\when('get_permalink')->alias(static fn ($id) => "https://site.test/p/{$id}");
    }

    protected function tear_down(): void
    {
        Mockery::close();
        parent::tear_down();
    }

    public function testRegisterRoutesRegistersDocumentEndpoints(): void
    {
        Functions\expect('register_rest_route')
            ->times(3)
            ->with('lbfa/v1', Mockery::anyOf('/documents', '/pages', '/documents/update-page'), Mockery::type('array'));

        (new LBFA_Document_API_Controller())->register_routes();
        $this->addToAssertionCount(1);
    }

    public function testGetDocumentsCacheHitHydratesPageOptions(): void
    {
        $cached = [
            'cookie_policy' => [
                'id' => 'doc-cp', 'slug' => 'cookie-policy', 'createdAt' => '', 'updatedAt' => '',
                'languages' => ['it' => ['url' => ['html' => 'h', 'pdf' => 'p']]],
            ],
        ];
        LBFA_Transient_Helper::$__cache['api_response_documents'] = $cached;
        LBFA_Option_Helper::$__options = [
            'page_cookie_policy_id_it' => '7',
            'page_cookie_policy_use_html_snippet_it' => true,
        ];
        Functions\expect('wp_remote_get')->never();

        $payload = (new LBFA_Document_API_Controller())->get_documents()->get_data();

        $this->assertTrue($payload['success']);
        $this->assertSame('7', $payload['data']['data']['cookie_policy']['languages']['it']['pageId']);
        $this->assertTrue($payload['data']['data']['cookie_policy']['languages']['it']['useHtmlSnippet']);
    }

    public function testGetDocumentsMissingJwtReturnsError(): void
    {
        Functions\expect('wp_remote_get')->never();

        $payload = (new LBFA_Document_API_Controller())->get_documents()->get_data();
        $this->assertFalse($payload['success']);
    }

    public function testGetDocumentsCacheMissFetchesAndPersistsUrls(): void
    {
        LBFA_Option_Helper::$__options['jwt_token'] = 'jwt';

        $remote = [
            'data' => [
                [
                    'id' => 'd1', 'active' => true, 'createdAt' => '', 'updatedAt' => '',
                    'template' => ['slug' => 'cookie-policy-base'],
                    'files' => [
                        ['language' => ['code' => 'it'], 'html' => ['url' => 'https://x/cp-it.html'], 'pdf' => ['url' => 'https://x/cp-it.pdf']],
                    ],
                ],
                [
                    'id' => 'd2', 'active' => true, 'createdAt' => '', 'updatedAt' => '',
                    'template' => ['slug' => 'privacy-policy-extended'],
                    'files' => [
                        ['language' => ['code' => 'it'], 'html' => ['url' => 'https://x/pp-it.html'], 'pdf' => ['url' => '']],
                    ],
                ],
            ],
        ];

        Functions\expect('wp_remote_get')
            ->once()
            ->with(
                'https://backend.example.test/integrations/wordpress/documents',
                Mockery::on(static fn ($args) => ($args['headers']['Authorization'] ?? '') === 'Bearer jwt')
            )
            ->andReturn(['response' => ['code' => 200], 'body' => json_encode($remote)]);

        $payload = (new LBFA_Document_API_Controller())->get_documents()->get_data();

        $this->assertTrue($payload['success']);
        $this->assertSame(2, $payload['data']['count']);
        $this->assertArrayHasKey('cookie_policy', $payload['data']['data']);
        $this->assertArrayHasKey('privacy_policy', $payload['data']['data']);
        $this->assertSame(
            'https://x/cp-it.html',
            LBFA_Option_Helper::getLanguageOption('documents_cookie_policy_html_url', 'it')
        );
    }

    public function testGetDocumentsBackendErrorReturnsErrorAndDoesNotCache(): void
    {
        LBFA_Option_Helper::$__options['jwt_token'] = 'jwt';

        Functions\expect('wp_remote_get')
            ->once()
            ->andReturn(['response' => ['code' => 502], 'body' => '{}']);

        $payload = (new LBFA_Document_API_Controller())->get_documents()->get_data();
        $this->assertFalse($payload['success']);
        $this->assertArrayNotHasKey('api_response_documents', LBFA_Transient_Helper::$__cache);
    }

    public function testParseDocumentsAppliesAllowlistAndSkipsInvalid(): void
    {
        $documents = [
            ['active' => false, 'template' => ['slug' => 'cookie-policy'], 'files' => [['language' => ['code' => 'it'], 'html' => ['url' => 'h'], 'pdf' => ['url' => 'p']]]],
            ['active' => true, 'template' => ['slug' => 'unknown-slug'], 'files' => [['language' => ['code' => 'it'], 'html' => ['url' => 'h'], 'pdf' => ['url' => 'p']]]],
            ['active' => true, 'template' => ['slug' => 'privacy-policy-x'], 'files' => []],
            ['id' => 'a', 'active' => true, 'template' => ['slug' => 'termini-y'], 'files' => [['language' => ['code' => 'it'], 'html' => ['url' => 'h'], 'pdf' => ['url' => 'p']]], 'createdAt' => '', 'updatedAt' => ''],
            // Duplicate of `terms_of_service` slug — must be skipped.
            ['id' => 'b', 'active' => true, 'template' => ['slug' => 'termini-z'], 'files' => [['language' => ['code' => 'en'], 'html' => ['url' => 'h2'], 'pdf' => ['url' => 'p2']]], 'createdAt' => '', 'updatedAt' => ''],
        ];

        $parsed = (new LBFA_Document_API_Controller())->parse_documents($documents);

        $this->assertCount(1, $parsed);
        $this->assertArrayHasKey('terms_of_service', $parsed);
        $this->assertSame('a', $parsed['terms_of_service']['id']);
    }

    public function testUpdatePageContentWithZeroPageIdClearsLanguageOptions(): void
    {
        LBFA_Option_Helper::$__options['page_cookie_policy_id_it'] = '99';
        LBFA_Option_Helper::$__options['page_cookie_policy_use_html_snippet_it'] = true;

        Functions\expect('wp_update_post')->never();
        Functions\expect('get_post')->never();

        $request = new WP_REST_Request([
            'policy_type' => 'cookie_policy',
            'page_id' => 0,
            'use_html_snippet' => false,
            'language' => 'it',
        ]);

        $payload = (new LBFA_Document_API_Controller())->update_page_content($request)->get_data();
        $this->assertTrue($payload['success']);
        $this->assertSame(0, LBFA_Option_Helper::getLanguageOption('page_cookie_policy_id', 'it'));
        $this->assertFalse(LBFA_Option_Helper::getLanguageOption('page_cookie_policy_use_html_snippet', 'it'));
    }

    public function testUpdatePageContentWithValidPageWritesShortcode(): void
    {
        Functions\when('get_post')->justReturn((object) ['ID' => 7]);
        Functions\expect('wp_update_post')
            ->once()
            ->with(Mockery::on(static function ($args) {
                return ($args['ID'] ?? null) === 7
                    && str_contains((string) ($args['post_content'] ?? ''), '[LBFA_PRIVACY_POLICY')
                    && str_contains((string) ($args['post_content'] ?? ''), 'lang="en"')
                    && str_contains((string) ($args['post_content'] ?? ''), 'html="true"');
            }))
            ->andReturn(7);

        $request = new WP_REST_Request([
            'policy_type' => 'privacy_policy',
            'page_id' => 7,
            'use_html_snippet' => true,
            'language' => 'en',
        ]);

        $payload = (new LBFA_Document_API_Controller())->update_page_content($request)->get_data();
        $this->assertTrue($payload['success']);
        $this->assertSame(7, LBFA_Option_Helper::getLanguageOption('page_privacy_policy_id', 'en'));
        $this->assertTrue(LBFA_Option_Helper::getLanguageOption('page_privacy_policy_use_html_snippet', 'en'));
    }

    public function testUpdatePageContentWithMissingPageReturnsError(): void
    {
        Functions\when('get_post')->justReturn(null);
        Functions\expect('wp_update_post')->never();

        $request = new WP_REST_Request([
            'policy_type' => 'cookie_policy',
            'page_id' => 9999,
            'use_html_snippet' => false,
            'language' => 'it',
        ]);

        $payload = (new LBFA_Document_API_Controller())->update_page_content($request)->get_data();
        $this->assertFalse($payload['success']);
    }

    public function testGetWordpressPagesReturnsFormattedList(): void
    {
        Functions\when('get_pages')->justReturn([
            (object) ['ID' => 1, 'post_title' => 'About', 'post_name' => 'about', 'post_modified' => '2026-01-01 10:00:00'],
            (object) ['ID' => 2, 'post_title' => 'Contact', 'post_name' => 'contact', 'post_modified' => '2026-02-01 10:00:00'],
        ]);

        $payload = (new LBFA_Document_API_Controller())->get_wordpress_pages()->get_data();
        $this->assertTrue($payload['success']);
        $this->assertCount(2, $payload['data']['pages']);
        $this->assertSame(1, $payload['data']['pages'][0]['id']);
        $this->assertSame('https://site.test/p/1', $payload['data']['pages'][0]['url']);
    }
}
