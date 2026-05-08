<?php
/**
 * Tests for the real LBFA_Logger.
 *
 * Uses vfsStream to isolate filesystem writes. WP functions
 * (plugin_dir_path / wp_mkdir_p / wp_date / wp_json_encode /
 * sanitize_file_name / wp_delete_file) are mocked via Brain\Monkey to
 * point at the vfs root. The logger's static state (plugin_dir / logs_dir
 * / enabled) is reset between tests via reflection.
 *
 * NOTE: PHP's `glob()` does not support stream wrappers, so we discover
 * log files with `scandir()` + a regex filter instead.
 */

declare(strict_types=1);

namespace LegalBlink\Tests\UnitReal;

use Brain\Monkey;
use Brain\Monkey\Functions;
use LBFA_Logger;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use ReflectionClass;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

require_once dirname(__DIR__, 2) . '/classes/helper/class-lbfa-multisite-helper.php';
require_once dirname(__DIR__, 2) . '/classes/helper/class-lbfa-option-helper.php';
require_once dirname(__DIR__, 2) . '/classes/class-lbfa-logger.php';

class LoggerTest extends TestCase
{
    private static array $options = [];
    private vfsStreamDirectory $root;
    private string $pluginDir;

    protected function set_up(): void
    {
        parent::set_up();
        Monkey\setUp();

        // LBFA_Option_Helper prefixes every key with `lbfa_` before delegating
        // to get_option/update_option, so the in-memory store mirrors that.
        self::$options = ['lbfa_logging_enabled' => true];

        // Fresh vfs root per test so file operations are isolated.
        $this->root = vfsStream::setup('lbfa-plugin');
        $this->pluginDir = vfsStream::url('lbfa-plugin');

        // Reset the logger's static state between tests.
        $rc = new ReflectionClass(LBFA_Logger::class);
        foreach (['plugin_dir', 'logs_dir', 'enabled'] as $name) {
            $prop = $rc->getProperty($name);
            $prop->setAccessible(true);
            $prop->setValue(null, null);
        }

        Functions\when('is_multisite')->justReturn(false);
        Functions\when('is_network_admin')->justReturn(false);
        Functions\when('is_admin')->justReturn(false);
        Functions\when('current_user_can')->justReturn(false);

        Functions\when('get_option')->alias(static fn ($k, $default = false) => self::$options[$k] ?? $default);
        Functions\when('update_option')->alias(static function ($k, $v) {
            self::$options[$k] = $v;
            return true;
        });
        Functions\when('delete_option')->alias(static function ($k) {
            unset(self::$options[$k]);
            return true;
        });

        // The logger calls `dirname(plugin_dir_path(__FILE__))` and uses the
        // result as the plugin root. We point plugin_dir_path at
        // vfs://lbfa-plugin/classes/ so dirname yields vfs://lbfa-plugin.
        Functions\when('plugin_dir_path')->alias(fn ($f) => $this->pluginDir . '/classes/');
        Functions\when('wp_mkdir_p')->alias(static function ($dir) {
            if (is_dir($dir)) return true;
            return mkdir($dir, 0777, true);
        });
        Functions\when('wp_date')->alias(static fn ($f) => date($f));
        Functions\when('wp_json_encode')->alias(static fn ($v) => json_encode($v));
        Functions\when('sanitize_file_name')->returnArg(1);
        Functions\when('wp_delete_file')->alias(static function ($path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        });

        if (!defined('WP_DEBUG')) {
            define('WP_DEBUG', false);
        }
    }

    protected function tear_down(): void
    {
        Monkey\tearDown();
        parent::tear_down();
    }

    /**
     * vfsStream-friendly file lookup. PHP's glob() doesn't traverse stream
     * wrappers, so we use scandir() + a regex pattern instead.
     */
    private function findLogFiles(string $dir, string $pattern): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $files = [];
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            if (preg_match($pattern, $entry)) {
                $files[] = $dir . '/' . $entry;
            }
        }
        return $files;
    }

    public function testIsEnabledReadsOption(): void
    {
        self::$options['lbfa_logging_enabled'] = true;
        $this->assertTrue(LBFA_Logger::is_enabled());

        // Reset internals so init() reloads the option.
        $rc = new ReflectionClass(LBFA_Logger::class);
        $rc->getProperty('plugin_dir')->setValue(null, null);
        $rc->getProperty('enabled')->setValue(null, null);

        self::$options['lbfa_logging_enabled'] = false;
        $this->assertFalse(LBFA_Logger::is_enabled());
    }

    public function testSetEnabledPersistsOption(): void
    {
        LBFA_Logger::set_enabled(false);
        $this->assertFalse(self::$options['lbfa_logging_enabled']);

        LBFA_Logger::set_enabled(true);
        $this->assertTrue(self::$options['lbfa_logging_enabled']);
    }

    public function testLogReturnsFalseWhenDisabled(): void
    {
        self::$options['lbfa_logging_enabled'] = false;
        $this->assertFalse(LBFA_Logger::info('hello'));
    }

    public function testLogWritesToCategoryFile(): void
    {
        $this->assertTrue(LBFA_Logger::info('Hello world', LBFA_Logger::CATEGORY_API, 'test_source'));

        $logsDir = $this->pluginDir . '/logs';
        $this->assertDirectoryExists($logsDir);

        $files = $this->findLogFiles($logsDir, '/^api_info_.*\.log$/');
        $this->assertNotEmpty($files);

        $contents = (string) file_get_contents($files[0]);
        $this->assertStringContainsString('Hello world', $contents);
        $this->assertStringContainsString('[INFO]', $contents);
        $this->assertStringContainsString('[API]', $contents);
        $this->assertStringContainsString('[CONTEXT: test_source]', $contents);
    }

    public function testLogCreatesIndexAndHtaccessOnFirstWrite(): void
    {
        LBFA_Logger::info('seed');

        $logsDir = $this->pluginDir . '/logs';
        $this->assertFileExists($logsDir . '/.htaccess');
        $this->assertFileExists($logsDir . '/index.php');
        $this->assertStringContainsString('Deny from all', (string) file_get_contents($logsDir . '/.htaccess'));
    }

    public function testLogSerializesArrayMessages(): void
    {
        LBFA_Logger::warning(['key' => 'value', 'n' => 42], LBFA_Logger::CATEGORY_GENERAL);

        $files = $this->findLogFiles($this->pluginDir . '/logs', '/^general_warning_.*\.log$/');
        $this->assertNotEmpty($files);

        $contents = (string) file_get_contents($files[0]);
        $this->assertStringContainsString('"key":"value"', $contents);
        $this->assertStringContainsString('"n":42', $contents);
    }

    public function testLogIgnoresInvalidLevelAndCoercesToInfo(): void
    {
        LBFA_Logger::log('m', 'not-a-level', LBFA_Logger::CATEGORY_GENERAL);

        $files = $this->findLogFiles($this->pluginDir . '/logs', '/^general_info_.*\.log$/');
        $this->assertNotEmpty($files);
    }

    public function testCategoryConvenienceWrappers(): void
    {
        LBFA_Logger::api('a');
        LBFA_Logger::shortcode('s');
        LBFA_Logger::auth('au');
        LBFA_Logger::document('d');
        LBFA_Logger::language('l');

        $logsDir = $this->pluginDir . '/logs';
        $this->assertNotEmpty($this->findLogFiles($logsDir, '/^api_info_.*\.log$/'));
        $this->assertNotEmpty($this->findLogFiles($logsDir, '/^shortcode_info_.*\.log$/'));
        $this->assertNotEmpty($this->findLogFiles($logsDir, '/^auth_info_.*\.log$/'));
        $this->assertNotEmpty($this->findLogFiles($logsDir, '/^document_info_.*\.log$/'));
        $this->assertNotEmpty($this->findLogFiles($logsDir, '/^language_info_.*\.log$/'));
    }

    public function testGetLogFilesInfoListsLogs(): void
    {
        LBFA_Logger::info('one', LBFA_Logger::CATEGORY_API);
        LBFA_Logger::error('two', LBFA_Logger::CATEGORY_AUTH);

        $info = LBFA_Logger::get_log_files_info();

        $this->assertTrue($info['enabled']);
        $this->assertSame(60, $info['retention_days']);
        $this->assertNotEmpty($info['files']);
        $paths = array_column($info['files'], 'path');
        $this->assertNotEmpty(array_filter($paths, static fn ($p) => str_starts_with($p, 'api_info_')));
    }

    public function testClearAllLogsRemovesLogFiles(): void
    {
        LBFA_Logger::info('a', LBFA_Logger::CATEGORY_API);
        LBFA_Logger::error('b', LBFA_Logger::CATEGORY_AUTH);

        $logsDir = $this->pluginDir . '/logs';
        $this->assertNotEmpty($this->findLogFiles($logsDir, '/\.log$/'));

        LBFA_Logger::clear_all_logs();
        $this->assertEmpty($this->findLogFiles($logsDir, '/\.log$/'));
    }

    public function testSetAndGetRetentionDaysClampedToValidRange(): void
    {
        LBFA_Logger::set_retention_days(0);
        $this->assertSame(1, LBFA_Logger::get_retention_days());

        LBFA_Logger::set_retention_days(500);
        $this->assertSame(365, LBFA_Logger::get_retention_days());

        LBFA_Logger::set_retention_days(45);
        $this->assertSame(45, LBFA_Logger::get_retention_days());
    }
}
