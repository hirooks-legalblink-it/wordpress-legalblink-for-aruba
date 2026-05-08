<?php
/**
 * PHPUnit bootstrap file for LegalBlink for Aruba plugin tests.
 *
 * Two modes of operation, selected via the LBFA_USE_STUBS env var:
 *
 * - LBFA_USE_STUBS=1 (default): the Unit testsuite runs against in-memory
 *   stub helpers in tests/Stubs.php. Brain\Monkey can't mock static methods,
 *   so the stubs replace LBFA_Logger/LBFA_*_Helper at autoload time.
 *
 * - LBFA_USE_STUBS=0: the UnitReal testsuite exercises the real
 *   classes/helper/*.php and classes/class-lbfa-logger.php. Tests in that
 *   directory require the production files explicitly and mock the
 *   underlying WordPress functions per-test via Brain\Monkey.
 *
 * Both modes share the WP core class shims (WpCoreShims.php) and translation
 * stubs (TranslationStubs.php).
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

if (!defined('LBFA_PLUGIN_DIR')) {
    define('LBFA_PLUGIN_DIR', dirname(__DIR__) . '/');
}

if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

$autoloader = dirname(__DIR__) . '/vendor/autoload.php';
if (!file_exists($autoloader)) {
    fwrite(
        STDERR,
        "Run `composer install` in plugin/legalblink-for-aruba before running the test suite.\n"
    );
    exit(1);
}

require_once $autoloader;

$useStubs = getenv('LBFA_USE_STUBS') !== '0';

if ($useStubs) {
    // In-memory stubs that replace LBFA helpers + WP core class shims.
    require_once __DIR__ . '/Stubs.php';
    require_once dirname(__DIR__) . '/classes/controller/api/class-lbfa-base-api-controller.php';
} else {
    // Real helpers: tests require them explicitly, we just install the
    // shared WP shims and translation plugin globals.
    require_once __DIR__ . '/WpCoreShims.php';
    require_once __DIR__ . '/TranslationStubs.php';
}
