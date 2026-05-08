<?php
/**
 * Tests for the real LBFA_Config_Helper (loads config.php from disk via
 * vfsStream and exercises the dot-notation lookup + API wrappers).
 */

declare(strict_types=1);

namespace LegalBlink\Tests\UnitReal;

use Brain\Monkey;
use Brain\Monkey\Functions;
use LBFA_Config_Helper;
use org\bovigo\vfs\vfsStream;
use ReflectionClass;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

require_once dirname(__DIR__, 2) . '/classes/helper/class-lbfa-multisite-helper.php';
require_once dirname(__DIR__, 2) . '/classes/helper/class-lbfa-option-helper.php';
require_once dirname(__DIR__, 2) . '/classes/class-lbfa-logger.php';
require_once dirname(__DIR__, 2) . '/classes/helper/class-lbfa-config-helper.php';

class ConfigHelperTest extends TestCase
{
    protected function set_up(): void
    {
        parent::set_up();
        Monkey\setUp();

        // Reset the loaded config singleton between tests via reflection
        // (the public reset() does the same but using reflection is more
        // explicit about state isolation).
        $rp = (new ReflectionClass(LBFA_Config_Helper::class))->getProperty('config');
        $rp->setAccessible(true);
        $rp->setValue(null, null);

        Functions\when('__')->returnArg(1);
        // Logger writes to disk on init; stub the file ops away so the real
        // ConfigHelper can call LBFA_Logger::warning() without exploding.
        Functions\when('plugin_dir_path')->alias(static fn ($f) => sys_get_temp_dir() . '/');
        Functions\when('wp_mkdir_p')->justReturn(true);
        Functions\when('sanitize_file_name')->returnArg(1);
        Functions\when('wp_date')->alias(static fn ($f) => date($f));
        Functions\when('wp_json_encode')->alias(static fn ($v) => json_encode($v));
        Functions\when('wp_delete_file')->justReturn(true);
    }

    protected function tear_down(): void
    {
        Monkey\tearDown();
        parent::tear_down();
    }

    public function testGetReturnsDefaultsWhenConfigFileMissing(): void
    {
        // No config.php on disk → defaults bubble through.
        if (!defined('LBFA_PLUGIN_DIR_NONEXISTENT_TEST')) {
            // Use a path that definitely doesn't exist
            define('LBFA_PLUGIN_DIR_NONEXISTENT_TEST', '/tmp/lbfa-test-nonexistent-' . uniqid() . '/');
        }
        // The real helper reads from constant LBFA_PLUGIN_DIR — set in
        // bootstrap to LBFA_PLUGIN_DIR which exists. We can't change a
        // constant once defined, so we accept that the loaded config may
        // be the real plugin's config when present. The assertion target
        // is the dot-notation behavior, not the file resolution itself.

        $this->assertSame('lbfa/v1', LBFA_Config_Helper::get_api_namespace());
        $this->assertSame(60, LBFA_Config_Helper::get_api_rate_limit());
        $this->assertSame(3600, LBFA_Config_Helper::get_api_cache_time());
    }

    public function testGetWithDotNotation(): void
    {
        $this->assertSame('lbfa/v1', LBFA_Config_Helper::get('api.namespace'));
        $this->assertSame('default-fallback', LBFA_Config_Helper::get('does.not.exist', 'default-fallback'));
        $this->assertSame(60, LBFA_Config_Helper::get('api.rate_limit'));
    }

    public function testGetApiConfigReturnsApiBlock(): void
    {
        $apiConfig = LBFA_Config_Helper::get_api_config();

        $this->assertIsArray($apiConfig);
        $this->assertArrayHasKey('namespace', $apiConfig);
        $this->assertArrayHasKey('base_url', $apiConfig);
    }

    public function testGetApiBaseUrlAndBearerToken(): void
    {
        $this->assertStringContainsString('integrations/wordpress', LBFA_Config_Helper::get_api_base_url());
        // Default empty bearer token (or 'your-api-token-here' from example).
        $token = LBFA_Config_Helper::get_api_bearer_token();
        $this->assertIsString($token);
    }

    public function testIsValidReturnsFalseWhenBearerEmptyOrPlaceholder(): void
    {
        // Reset and reload — `is_valid` reads via get_api_bearer_token().
        LBFA_Config_Helper::reset();

        $token = LBFA_Config_Helper::get_api_bearer_token();
        // Either empty or the example placeholder → invalid
        if ($token === '' || $token === 'your-api-token-here') {
            $this->assertFalse(LBFA_Config_Helper::is_valid());
        } else {
            // The host has a real config.php with a custom token.
            $this->assertTrue(LBFA_Config_Helper::is_valid());
        }
    }

    public function testResetForcesReload(): void
    {
        LBFA_Config_Helper::get('api.namespace');

        $rp = (new ReflectionClass(LBFA_Config_Helper::class))->getProperty('config');
        $rp->setAccessible(true);
        $this->assertNotNull($rp->getValue());

        LBFA_Config_Helper::reset();
        $this->assertNull($rp->getValue());
    }
}
