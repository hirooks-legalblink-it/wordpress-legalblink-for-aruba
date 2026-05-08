<?php
/**
 * Test stubs that replace WordPress core classes and the LBFA helper classes
 * that the controllers depend on.
 *
 * Brain\Monkey cannot mock static methods on classes; rather than ship a
 * heavier framework, we provide deterministic stub classes whose state is
 * mutated directly from the tests via static properties / reset() methods.
 *
 * Loaded once from tests/bootstrap.php. The real helper classes
 * (classes/helper/*.php) are NOT required by the bootstrap so PHPUnit picks
 * up these stubs first and skips the real implementations.
 */

declare(strict_types=1);

// WP core class shims live in WpCoreShims.php (shared with UnitReal bootstrap).
require_once __DIR__ . '/WpCoreShims.php';

/* ------------------------------------------------------------------------- */
/* LBFA helper stubs                                                          */
/* ------------------------------------------------------------------------- */

if (!class_exists('LBFA_Logger', false)) {
    /**
     * No-op logger stub mirroring the surface of classes/class-lbfa-logger.php
     * (constants + level/category methods). Tests don't care about log output;
     * the real logger is exercised by tests/UnitReal/LoggerTest.php instead.
     */
    class LBFA_Logger
    {
        const LEVEL_DEBUG = 'debug';
        const LEVEL_INFO = 'info';
        const LEVEL_WARNING = 'warning';
        const LEVEL_ERROR = 'error';
        const LEVEL_CRITICAL = 'critical';

        const CATEGORY_API = 'api';
        const CATEGORY_SHORTCODE = 'shortcode';
        const CATEGORY_AUTH = 'auth';
        const CATEGORY_DOCUMENT = 'document';
        const CATEGORY_LANGUAGE = 'language';
        const CATEGORY_CACHE = 'cache';
        const CATEGORY_CONFIG = 'config';
        const CATEGORY_GENERAL = 'general';

        public static function log($message, string $level = self::LEVEL_INFO, string $category = self::CATEGORY_GENERAL, string $context = '', string $subDir = ''): bool { return true; }
        public static function debug($message, string $category = self::CATEGORY_GENERAL, string $context = '', string $subDir = ''): bool { return true; }
        public static function info($message, string $category = self::CATEGORY_GENERAL, string $context = '', string $subDir = ''): bool { return true; }
        public static function warning($message, string $category = self::CATEGORY_GENERAL, string $context = '', string $subDir = ''): bool { return true; }
        public static function error($message, string $category = self::CATEGORY_GENERAL, string $context = '', string $subDir = ''): bool { return true; }
        public static function critical($message, string $category = self::CATEGORY_GENERAL, string $context = '', string $subDir = ''): bool { return true; }
        public static function api($message, string $level = self::LEVEL_INFO, string $context = ''): bool { return true; }
        public static function shortcode($message, string $level = self::LEVEL_INFO, string $context = ''): bool { return true; }
        public static function auth($message, string $level = self::LEVEL_INFO, string $context = ''): bool { return true; }
        public static function document($message, string $level = self::LEVEL_INFO, string $context = ''): bool { return true; }
        public static function language($message, string $level = self::LEVEL_INFO, string $context = ''): bool { return true; }
        public static function set_enabled($enabled): void {}
        public static function is_enabled(): bool { return false; }
        public static function get_log_files_info(): array { return ['enabled' => false, 'logs_dir' => '', 'retention_days' => 60, 'files' => []]; }
        public static function clear_all_logs(): bool { return true; }
        public static function set_retention_days($days): void {}
        public static function get_retention_days(): int { return 60; }
    }
}

if (!class_exists('LBFA_Multisite_Helper', false)) {
    class LBFA_Multisite_Helper
    {
        public static function is_multisite(): bool { return false; }
        public static function get_context(): string { return 'site'; }
        public static function is_network_context_forced(): bool { return false; }
    }
}

if (!class_exists('LBFA_Config_Helper', false)) {
    /**
     * Test-only LBFA_Config_Helper: returns deterministic defaults.
     * Tests can override via the static $__overrides map keyed by config path
     * (e.g. 'api.base_url' => 'http://test.local').
     */
    class LBFA_Config_Helper
    {
        public static array $__overrides = [];

        public static function reset(): void
        {
            self::$__overrides = [];
        }

        public static function get_api_namespace(): string
        {
            return self::$__overrides['api.namespace'] ?? 'lbfa/v1';
        }

        public static function get_api_base_url(): string
        {
            return self::$__overrides['api.base_url'] ?? 'https://backend.example.test/integrations/wordpress';
        }

        public static function get_api_bearer_token(): string
        {
            return self::$__overrides['api.bearer_token'] ?? '';
        }

        public static function get_api_rate_limit(): int
        {
            return (int) (self::$__overrides['api.rate_limit'] ?? 60);
        }

        public static function get_api_cache_time(): int
        {
            return (int) (self::$__overrides['api.cache_time'] ?? 3600);
        }

        public static function is_valid(): bool
        {
            return true;
        }
    }
}

if (!class_exists('LBFA_Option_Helper', false)) {
    /**
     * In-memory LBFA_Option_Helper: tests can preset values via the
     * $__options static map (or call setOption directly). Language-aware
     * options are stored under the suffixed key "key_lang".
     */
    class LBFA_Option_Helper
    {
        const OPTION_PREFIX = 'lbfa_';

        public static array $__options = [];

        public static function reset(): void
        {
            self::$__options = [];
        }

        public static function getOption(string $key, $default = null)
        {
            return array_key_exists($key, self::$__options) ? self::$__options[$key] : $default;
        }

        public static function setOption(string $key, $value)
        {
            self::$__options[$key] = $value;
            return true;
        }

        public static function deleteOption($key)
        {
            unset(self::$__options[$key]);
            return true;
        }

        public static function getLanguageOption(string $key, string $language = 'it', $default = '')
        {
            return self::getOption("{$key}_{$language}", $default);
        }

        public static function setLanguageOption(string $key, $value, string $language = 'it')
        {
            return self::setOption("{$key}_{$language}", $value);
        }

        public static function getLanguageTimestamp($policy_type, $language, $default = '')
        {
            return self::getOption("{$policy_type}_codes_updated_{$language}", $default);
        }

        public static function setLanguageTimestamp($policy_type, $language, $value)
        {
            return self::setOption("{$policy_type}_codes_updated_{$language}", $value);
        }
    }
}

if (!class_exists('LBFA_Transient_Helper', false)) {
    /**
     * In-memory LBFA_Transient_Helper: tests can preset values via $__cache
     * or call set() directly. Tracks set() calls in $__sets for assertions.
     */
    class LBFA_Transient_Helper
    {
        const DEFAULT_EXPIRATION = 3600;
        const TRANSIENT_PREFIX = 'lbfa_';

        public static array $__cache = [];
        public static array $__sets = [];

        public static function reset(): void
        {
            self::$__cache = [];
            self::$__sets = [];
        }

        public static function get(string $key, $default = false)
        {
            return array_key_exists($key, self::$__cache) ? self::$__cache[$key] : $default;
        }

        public static function set(string $key, $value, int $expiration = self::DEFAULT_EXPIRATION)
        {
            self::$__cache[$key] = $value;
            self::$__sets[] = ['key' => $key, 'value' => $value, 'expiration' => $expiration];
            return true;
        }

        public static function delete(string $key)
        {
            unset(self::$__cache[$key]);
            return true;
        }

        public static function remember(string $key, callable $callback, int $expiration = self::DEFAULT_EXPIRATION)
        {
            if (!array_key_exists($key, self::$__cache)) {
                self::$__cache[$key] = call_user_func($callback);
            }
            return self::$__cache[$key];
        }

        public static function getLanguage(string $key, string $language = 'it', $default = false)
        {
            return self::get("{$key}_{$language}", $default);
        }

        public static function setLanguage(string $key, string $language, $value, int $expiration = self::DEFAULT_EXPIRATION)
        {
            return self::set("{$key}_{$language}", $value, $expiration);
        }

        public static function deleteLanguage(string $key, string $language)
        {
            return self::delete("{$key}_{$language}");
        }

        public static function cacheApiResponse(string $endpoint, $data, int $expiration = self::DEFAULT_EXPIRATION)
        {
            return self::set("api_response_{$endpoint}", $data, $expiration);
        }

        public static function getCachedApiResponse(string $endpoint)
        {
            return self::get("api_response_{$endpoint}");
        }

        public static function clearAll(): void
        {
            self::$__cache = [];
        }

        public static function exists(string $key): bool
        {
            return array_key_exists($key, self::$__cache);
        }
    }
}
