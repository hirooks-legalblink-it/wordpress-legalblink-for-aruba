<?php
/**
 * Tests for the real LBFA_Transient_Helper.
 */

declare(strict_types=1);

namespace LegalBlink\Tests\UnitReal;

use Brain\Monkey;
use Brain\Monkey\Functions;
use LBFA_Transient_Helper;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

require_once dirname(__DIR__, 2) . '/classes/helper/class-lbfa-multisite-helper.php';
require_once dirname(__DIR__, 2) . '/classes/helper/class-lbfa-transient-helper.php';

class TransientHelperTest extends TestCase
{
    /**
     * In-memory simulation of the WP transient + option layer used by the
     * helper. Keys are kept exactly as the helper passes them
     * (`lbfa_<key>`).
     */
    private static array $store = [];
    private static array $options = [];
    /** @var list<string> */
    public static array $wpdbQueries = [];

    protected function set_up(): void
    {
        parent::set_up();
        Monkey\setUp();

        self::$store = [];
        self::$options = [];
        self::$wpdbQueries = [];

        // Minimal $wpdb stub that records queries and removes matching keys
        // from the in-memory $store. clearAll() now falls back to direct
        // wpdb deletion for keys missing from the registry.
        global $wpdb;
        $wpdb = new class {
            public string $options = 'wp_options';
            public string $sitemeta = 'wp_sitemeta';

            public function prepare(string $sql, ...$args): string
            {
                // Substitute %s placeholders with the arg values so the test
                // can assert against the final query (good enough for these
                // tests; production wpdb does proper escaping).
                foreach ($args as $arg) {
                    $sql = preg_replace('/%s/', is_string($arg) ? $arg : (string) $arg, $sql, 1);
                }
                return $sql;
            }

            public function query(string $sql): int
            {
                TransientHelperTest::$wpdbQueries[] = $sql;
                // Crude pattern detection for the lbfa transient cleanup
                // query (escaped backslashes from the LIKE pattern make a
                // plain substring match unreliable, so we look for both
                // tokens in any escape form).
                if (str_contains($sql, 'transient') && str_contains($sql, 'lbfa')) {
                    foreach (array_keys(TransientHelperTest::storeRef()) as $key) {
                        if (str_starts_with($key, 'lbfa_')) {
                            TransientHelperTest::unsetStore($key);
                        }
                    }
                }
                return 1;
            }
        };

        Functions\when('is_multisite')->justReturn(false);
        Functions\when('is_network_admin')->justReturn(false);
        Functions\when('is_admin')->justReturn(false);
        Functions\when('current_user_can')->justReturn(false);
        Functions\when('current_time')->justReturn(1_700_000_000);
        Functions\when('maybe_serialize')->alias(static fn ($v) => is_scalar($v) ? (string) $v : serialize($v));
        Functions\when('wp_cache_flush_group')->justReturn(true);

        Functions\when('get_transient')->alias(static fn ($k) => array_key_exists($k, self::$store) ? self::$store[$k] : false);
        Functions\when('set_transient')->alias(static function ($k, $v, $expiration) {
            self::$store[$k] = $v;
            return true;
        });
        Functions\when('delete_transient')->alias(static function ($k) {
            unset(self::$store[$k]);
            return true;
        });
        Functions\when('get_site_transient')->alias(static fn ($k) => array_key_exists($k, self::$store) ? self::$store[$k] : false);
        Functions\when('set_site_transient')->alias(static function ($k, $v, $expiration) {
            self::$store[$k] = $v;
            return true;
        });
        Functions\when('delete_site_transient')->alias(static function ($k) {
            unset(self::$store[$k]);
            return true;
        });

        Functions\when('get_option')->alias(static fn ($k, $default = false) => self::$options[$k] ?? $default);
        Functions\when('update_option')->alias(static function ($k, $v) {
            self::$options[$k] = $v;
            return true;
        });
        Functions\when('get_site_option')->alias(static fn ($k, $default = false) => self::$options[$k] ?? $default);
        Functions\when('update_site_option')->alias(static function ($k, $v) {
            self::$options[$k] = $v;
            return true;
        });
    }

    public static function &storeRef(): array
    {
        return self::$store;
    }

    public static function unsetStore(string $key): void
    {
        unset(self::$store[$key]);
    }

    protected function tear_down(): void
    {
        Monkey\tearDown();
        parent::tear_down();
    }

    public function testGetReturnsDefaultWhenMissing(): void
    {
        $this->assertSame('fallback', LBFA_Transient_Helper::get('missing', 'fallback'));
    }

    public function testSetGetRoundtripWithPrefix(): void
    {
        LBFA_Transient_Helper::set('hello', 'world');

        $this->assertArrayHasKey('lbfa_hello', self::$store);
        $this->assertSame('world', LBFA_Transient_Helper::get('hello'));
    }

    public function testSetRegistersKeyInRegistry(): void
    {
        LBFA_Transient_Helper::set('k1', 'v1', 60);
        LBFA_Transient_Helper::set('k2', 'v2', 120);

        $registry = self::$options['lbfa_transient_registry'];
        $this->assertArrayHasKey('lbfa_k1', $registry);
        $this->assertArrayHasKey('lbfa_k2', $registry);
        $this->assertSame(2, (int) $registry['lbfa_k1']['size']);
    }

    public function testDeleteRemovesFromStoreAndRegistry(): void
    {
        LBFA_Transient_Helper::set('k', 'v');
        LBFA_Transient_Helper::delete('k');

        $this->assertArrayNotHasKey('lbfa_k', self::$store);
        $this->assertArrayNotHasKey('lbfa_k', self::$options['lbfa_transient_registry']);
    }

    public function testRememberCachesCallback(): void
    {
        $invocations = 0;
        $callback = static function () use (&$invocations) {
            $invocations++;
            return 'lazy';
        };

        $first = LBFA_Transient_Helper::remember('memo', $callback);
        $second = LBFA_Transient_Helper::remember('memo', $callback);

        $this->assertSame('lazy', $first);
        $this->assertSame('lazy', $second);
        $this->assertSame(1, $invocations);
    }

    public function testLanguageVariantsPersistWithLanguageSuffix(): void
    {
        LBFA_Transient_Helper::setLanguage('docs_html', 'en', '<p>en</p>');
        $this->assertSame('<p>en</p>', LBFA_Transient_Helper::getLanguage('docs_html', 'en'));
        $this->assertArrayHasKey('lbfa_docs_html_en', self::$store);

        LBFA_Transient_Helper::deleteLanguage('docs_html', 'en');
        $this->assertArrayNotHasKey('lbfa_docs_html_en', self::$store);
    }

    public function testCacheApiResponseAndGetCachedApiResponse(): void
    {
        LBFA_Transient_Helper::cacheApiResponse('languages', ['it', 'en']);
        $this->assertSame(['it', 'en'], LBFA_Transient_Helper::getCachedApiResponse('languages'));
    }

    public function testClearAllWipesEveryRegisteredTransient(): void
    {
        LBFA_Transient_Helper::set('a', 1);
        LBFA_Transient_Helper::set('b', 2);
        LBFA_Transient_Helper::set('c', 3);

        LBFA_Transient_Helper::clearAll();

        $this->assertArrayNotHasKey('lbfa_a', self::$store);
        $this->assertArrayNotHasKey('lbfa_b', self::$store);
        $this->assertArrayNotHasKey('lbfa_c', self::$store);
        $this->assertSame([], self::$options['lbfa_transient_registry']);
    }

    public function testClearAllFallsBackToWpdbForKeysMissingFromRegistry(): void
    {
        // Simulate a legacy / out-of-band write: a transient is present in
        // the store but NOT registered. Pre-fix, clearAll() left this key
        // alive forever; now the wpdb fallback wipes it.
        self::$store['lbfa_legacy_orphan'] = 'stale-payload';
        // Registry intentionally empty.
        self::$options['lbfa_transient_registry'] = [];

        LBFA_Transient_Helper::clearAll();

        $this->assertArrayNotHasKey('lbfa_legacy_orphan', self::$store);
        $this->assertNotEmpty(self::$wpdbQueries);
        // Escaped LIKE pattern contains both tokens — assert tolerantly.
        $this->assertStringContainsString('transient', self::$wpdbQueries[0]);
        $this->assertStringContainsString('lbfa', self::$wpdbQueries[0]);
    }

    public function testExistsReflectsStorePresence(): void
    {
        $this->assertFalse(LBFA_Transient_Helper::exists('absent'));
        LBFA_Transient_Helper::set('present', 'x');
        $this->assertTrue(LBFA_Transient_Helper::exists('present'));
    }

    public function testSetWithTimestampStoresTimestampedEnvelope(): void
    {
        LBFA_Transient_Helper::setWithTimestamp('w', 'value', 60);

        $envelope = LBFA_Transient_Helper::get('w');
        $this->assertSame('value', $envelope['value']);
        $this->assertArrayHasKey('cached_at', $envelope);
        $this->assertArrayHasKey('expires_at', $envelope);
    }

    public function testGetWithTimestampReturnsEnvelopeWhenValid(): void
    {
        LBFA_Transient_Helper::setWithTimestamp('w', 'value', 60);
        $this->assertIsArray(LBFA_Transient_Helper::getWithTimestamp('w'));

        // Plain (non-envelope) value → fallback default returned.
        LBFA_Transient_Helper::set('plain', 'just-string');
        $this->assertNull(LBFA_Transient_Helper::getWithTimestamp('plain'));
    }

    public function testGetStatsAggregatesRegistry(): void
    {
        LBFA_Transient_Helper::set('a', 'aa');
        LBFA_Transient_Helper::set('b', 'bbbb');

        $stats = LBFA_Transient_Helper::getStats();
        $this->assertSame(2, $stats['total_transients']);
        $this->assertSame(6, $stats['size_estimate']);
    }
}
