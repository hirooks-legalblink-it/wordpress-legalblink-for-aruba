<?php
/**
 * PHPUnit bootstrap file for LegalBlink for Aruba plugin tests.
 *
 * Test cases use Brain\Monkey to mock WordPress core functions; setUp/tearDown
 * is wired in the shared TestCase (see tests/TestCase.php).
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

$autoloader = dirname(__DIR__) . '/vendor/autoload.php';
if (!file_exists($autoloader)) {
    fwrite(
        STDERR,
        "Run `composer install` in plugin/legalblink-for-aruba before running the test suite.\n"
    );
    exit(1);
}

require_once $autoloader;

require_once dirname(__DIR__) . '/classes/helper/class-lbfa-multisite-helper.php';
require_once dirname(__DIR__) . '/classes/helper/class-lbfa-config-helper.php';
require_once dirname(__DIR__) . '/classes/helper/class-lbfa-option-helper.php';
require_once dirname(__DIR__) . '/classes/helper/class-lbfa-transient-helper.php';
require_once dirname(__DIR__) . '/classes/class-lbfa-logger.php';
require_once dirname(__DIR__) . '/classes/controller/api/class-lbfa-base-api-controller.php';
