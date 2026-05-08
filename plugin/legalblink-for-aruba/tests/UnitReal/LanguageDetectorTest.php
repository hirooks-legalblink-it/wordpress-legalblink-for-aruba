<?php
/**
 * Tests for LBFA_Language_Detector.
 *
 * Translation plugin globals are wired in tests/TranslationStubs.php
 * (loaded by tests/bootstrap.php when LBFA_USE_STUBS=0). Each test sets
 * $GLOBALS['__lbfa_test_translation'] to drive WPML / Polylang /
 * qTranslate / TranslatePress scenarios.
 */

declare(strict_types=1);

namespace LegalBlink\Tests\UnitReal;

use Brain\Monkey;
use Brain\Monkey\Functions;
use LBFA_Language_Detector;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

require_once dirname(__DIR__, 2) . '/classes/class-lbfa-language-detector.php';

class LanguageDetectorTest extends TestCase
{
    protected function set_up(): void
    {
        parent::set_up();
        Monkey\setUp();

        // Reset every translation stub state.
        lbfa_test_reset_translation_stubs();

        Functions\when('sanitize_text_field')->returnArg(1);
        Functions\when('sanitize_url')->returnArg(1);
        Functions\when('wp_unslash')->returnArg(1);
        Functions\when('get_locale')->justReturn('en_US');
    }

    protected function tear_down(): void
    {
        Monkey\tearDown();
        parent::tear_down();
    }

    /* ---------- detect_language priority chain ---------- */

    public function testManualLanguageHasHighestPriority(): void
    {
        $GLOBALS['__lbfa_test_translation']['wpml_current_language'] = 'fr';
        Functions\when('get_locale')->justReturn('de_DE');

        $this->assertSame('en', LBFA_Language_Detector::detect_language('en'));
    }

    public function testManualLanguageIgnoredWhenUnsupported(): void
    {
        // Manual `xx` not supported → falls through. WPML returns nothing,
        // wp_locale defaults to en_US → 'en'.
        $this->assertSame('en', LBFA_Language_Detector::detect_language('xx'));
    }

    public function testWpmlSecondPriority(): void
    {
        $GLOBALS['__lbfa_test_translation']['wpml_current_language'] = 'fr';
        $this->assertSame('fr', LBFA_Language_Detector::detect_language());
    }

    public function testWpmlIgnoresAllSentinel(): void
    {
        $GLOBALS['__lbfa_test_translation']['wpml_current_language'] = 'all';
        Functions\when('get_locale')->justReturn('it_IT');

        $this->assertSame('it', LBFA_Language_Detector::detect_language());
    }

    public function testPolylangThirdPriority(): void
    {
        $GLOBALS['__lbfa_test_translation']['pll_current_language'] = 'es';
        $this->assertSame('es', LBFA_Language_Detector::detect_language());
    }

    public function testQtranslateFourthPriority(): void
    {
        $GLOBALS['__lbfa_test_translation']['qtranxf_language'] = 'de';
        $this->assertSame('de', LBFA_Language_Detector::detect_language());
    }

    public function testTranslatePressFifthPriority(): void
    {
        $GLOBALS['__lbfa_test_translation']['trp_url_lang'] = 'fr';
        $_SERVER['REQUEST_URI'] = '/fr/page';

        $this->assertSame('fr', LBFA_Language_Detector::detect_language());
    }

    public function testWordPressLocaleFallback(): void
    {
        Functions\when('get_locale')->justReturn('it_IT');
        $this->assertSame('it', LBFA_Language_Detector::detect_language());
    }

    public function testDefaultLanguageWhenLocaleUnsupported(): void
    {
        Functions\when('get_locale')->justReturn('zh_CN');
        $this->assertSame('it', LBFA_Language_Detector::detect_language());
    }

    /* ---------- supported languages ---------- */

    public function testGetSupportedLanguages(): void
    {
        $this->assertSame(['it', 'en', 'de', 'fr', 'es'], LBFA_Language_Detector::get_supported_languages());
    }

    public function testGetLanguageNameKnown(): void
    {
        $this->assertSame('Italiano', LBFA_Language_Detector::get_language_name('it'));
        $this->assertSame('English', LBFA_Language_Detector::get_language_name('en'));
        $this->assertSame('Français', LBFA_Language_Detector::get_language_name('fr'));
    }

    public function testGetLanguageNameUnknownUppercases(): void
    {
        $this->assertSame('XX', LBFA_Language_Detector::get_language_name('xx'));
    }

    /* ---------- get_translated_page_id ---------- */

    public function testGetTranslatedPageIdReturnsZeroWhenNoPage(): void
    {
        $this->assertSame(0, LBFA_Language_Detector::get_translated_page_id(0));
    }

    public function testGetTranslatedPageIdViaPolylang(): void
    {
        $GLOBALS['__lbfa_test_translation']['pll_get_post_map'] = ['10|en' => 42];

        $this->assertSame(42, LBFA_Language_Detector::get_translated_page_id(10, 'en'));
    }

    public function testGetTranslatedPageIdReturnsOriginalWhenNoTranslation(): void
    {
        $this->assertSame(10, LBFA_Language_Detector::get_translated_page_id(10, 'en'));
    }

    /* ---------- get_page_language ---------- */

    public function testGetPageLanguageWithPolylang(): void
    {
        $GLOBALS['__lbfa_test_translation']['pll_get_post_language_map'] = [10 => 'en'];

        $this->assertSame('en', LBFA_Language_Detector::get_page_language(10));
    }

    public function testGetPageLanguageWithWpmlInfo(): void
    {
        $GLOBALS['__lbfa_test_translation']['wpml_language_info_map'] = [
            10 => ['language_code' => 'fr_FR'],
        ];

        $this->assertSame('fr', LBFA_Language_Detector::get_page_language(10));
    }

    public function testGetPageLanguageFallsBackToDetect(): void
    {
        Functions\when('get_locale')->justReturn('de_DE');
        $this->assertSame('de', LBFA_Language_Detector::get_page_language(10));
    }

    public function testGetPageLanguageWithoutPageIdDefaultsToDetect(): void
    {
        Functions\when('get_locale')->justReturn('it_IT');
        $this->assertSame('it', LBFA_Language_Detector::get_page_language(0));
    }

    /* ---------- should_show_content / fallback chain ---------- */

    public function testShouldShowContentExactMatch(): void
    {
        $this->assertTrue(LBFA_Language_Detector::should_show_content('en', 'en'));
        $this->assertFalse(LBFA_Language_Detector::should_show_content('de', 'en'));
    }

    public function testShouldShowContentUsesEnglishFallback(): void
    {
        $this->assertTrue(LBFA_Language_Detector::should_show_content('en', 'fr'));
    }

    public function testShouldShowContentUsesDefaultLanguageFallback(): void
    {
        $this->assertTrue(LBFA_Language_Detector::should_show_content('it', 'fr'));
    }

    public function testGetLanguageFallbackChain(): void
    {
        $this->assertSame(['fr', 'en', 'it'], LBFA_Language_Detector::get_language_fallback_chain('fr'));
        $this->assertSame(['en', 'it'], LBFA_Language_Detector::get_language_fallback_chain('en'));
        $this->assertSame(['it', 'en'], LBFA_Language_Detector::get_language_fallback_chain('it'));
    }

    /* ---------- debug_detection ---------- */

    public function testDebugDetectionReturnsArrayWithExpectedKeys(): void
    {
        $debug = LBFA_Language_Detector::debug_detection('en');

        $this->assertSame('en', $debug['manual_lang']);
        $this->assertArrayHasKey('wpml_detected', $debug);
        $this->assertArrayHasKey('polylang_detected', $debug);
        $this->assertArrayHasKey('qtranslate_detected', $debug);
        $this->assertArrayHasKey('translatepress_detected', $debug);
        $this->assertArrayHasKey('detected_language', $debug);
        $this->assertSame(['it', 'en', 'de', 'fr', 'es'], $debug['supported_languages']);
    }
}
