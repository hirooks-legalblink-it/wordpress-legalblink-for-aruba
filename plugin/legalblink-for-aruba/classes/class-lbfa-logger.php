<?php

if (!defined('ABSPATH')) {
    exit;
}

if ( ! class_exists( 'LBFA_Logger' ) ) {
    class LBFA_Logger
    {
        /**
         * Log levels
         */
        const LEVEL_DEBUG = 'debug';
        const LEVEL_INFO = 'info';
        const LEVEL_WARNING = 'warning';
        const LEVEL_ERROR = 'error';
        const LEVEL_CRITICAL = 'critical';

        /**
         * Log categories/prefixes
         */
        const CATEGORY_API = 'api';
        const CATEGORY_SHORTCODE = 'shortcode';
        const CATEGORY_AUTH = 'auth';
        const CATEGORY_DOCUMENT = 'document';
        const CATEGORY_LANGUAGE = 'language';
        const CATEGORY_CACHE = 'cache';
        const CATEGORY_CONFIG = 'config';
        const CATEGORY_GENERAL = 'general';

        /**
         * Default retention period in days
         */
        const DEFAULT_RETENTION_DAYS = 60;

        /**
         * Maximum log file size in MB
         */
        const MAX_FILE_SIZE_MB = 10;

        /**
         * Plugin directory path
         */
        private static $plugin_dir = null;

        /**
         * Logs directory path
         */
        private static $logs_dir = null;

        /**
         * Whether logging is enabled
         */
        private static $enabled = null;

        /**
         * Initialize logger
         */
        private static function init()
        {
            if (self::$plugin_dir === null) {
                // Get the main plugin directory (one level up from classes/)
                self::$plugin_dir = dirname(plugin_dir_path(__FILE__));
                self::$logs_dir = self::$plugin_dir . '/logs';

                // Check if logging is enabled (default: only in debug mode)
                self::$enabled = defined('WP_DEBUG') && WP_DEBUG;

                // Allow override via option
                $option_enabled = LBFA_Option_Helper::getOption('logging_enabled', null);
                if ($option_enabled !== null) {
                    self::$enabled = (bool)$option_enabled;
                }
            }
        }

        /**
         * Enable or disable logging
         *
         * @param bool $enabled Whether to enable logging
         */
        public static function set_enabled($enabled)
        {
            self::init();
            self::$enabled = (bool)$enabled;
            LBFA_Option_Helper::setOption('logging_enabled', self::$enabled);
        }

        /**
         * Check if logging is enabled
         *
         * @return bool
         */
        public static function is_enabled()
        {
            self::init();
            return self::$enabled;
        }

        /**
         * Create logs directory if it doesn't exist
         *
         * @param string $subDir Optional subdirectory
         * @return string|false Directory path or false on failure
         */
        private static function ensure_logs_directory($subDir = '')
        {
            self::init();

            if (!self::$enabled) {
                return false;
            }

            $dir = self::$logs_dir;

            // Create main logs directory
            if (!is_dir($dir)) {
                if (!wp_mkdir_p($dir)) {
                    return false;
                }

                // Add .htaccess for security
                $htaccess_content = "# Deny direct access to log files\n";
                $htaccess_content .= "Order deny,allow\n";
                $htaccess_content .= "Deny from all\n";

                file_put_contents($dir . '/.htaccess', $htaccess_content);

                // Add index.php for additional security
                file_put_contents($dir . '/index.php', '<?php // Silence is golden');
            }

            // Create subdirectory if specified
            if (!empty($subDir)) {
                $subDir = sanitize_file_name($subDir);
                $full_subdir = $dir . '/' . $subDir;

                if (!is_dir($full_subdir)) {
                    if (!wp_mkdir_p($full_subdir)) {
                        return false;
                    }

                    // Add index.php to subdirectory
                    file_put_contents($full_subdir . '/index.php', '<?php // Silence is golden');
                }

                $dir = $full_subdir;
            }

            return $dir;
        }

        /**
         * Main logging function
         *
         * @param mixed $message Message to log (string, array, object)
         * @param string $level Log level (debug, info, warning, error, critical)
         * @param string $category Log category/prefix
         * @param string $context Optional context information
         * @param string $subDir Optional subdirectory for logs
         * @return bool Success status
         */
        public static function log($message, $level = self::LEVEL_INFO, $category = self::CATEGORY_GENERAL, $context = '', $subDir = '')
        {
            self::init();

            if (!self::$enabled) {
                return false;
            }

            try {
                $dir = self::ensure_logs_directory($subDir);
                if (!$dir) {
                    return false;
                }

                // Validate log level
                $valid_levels = [self::LEVEL_DEBUG, self::LEVEL_INFO, self::LEVEL_WARNING, self::LEVEL_ERROR, self::LEVEL_CRITICAL];
                if (!in_array($level, $valid_levels)) {
                    $level = self::LEVEL_INFO;
                }

                // Sanitize category
                $category = sanitize_file_name($category);
                if (empty($category)) {
                    $category = self::CATEGORY_GENERAL;
                }

                // Generate filename
                $timestamp = wp_date('Ymd');
                $filename = $dir . '/' . $category . '_' . $level . '_' . $timestamp . '.log';

                // Check file size and rotate if necessary
                if (file_exists($filename) && filesize($filename) > (self::MAX_FILE_SIZE_MB * 1024 * 1024)) {
                    $counter = 1;
                    do {
                        $rotated_filename = $dir . '/' . $category . '_' . $level . '_' . $timestamp . '_' . $counter . '.log';
                        $counter++;
                    } while (file_exists($rotated_filename));

                    $filename = $rotated_filename;
                }

                // Prepare message
                if (is_object($message)) {
                    $message = get_object_vars($message);
                }

                if (is_array($message)) {
                    $message = wp_json_encode($message);
                }

                // Prepare log entry
                $log_entry = '';
                $log_entry .= '[' . wp_date('Y-m-d H:i:s') . ']';
                $log_entry .= ' [' . strtoupper($level) . ']';
                $log_entry .= ' [' . strtoupper($category) . ']';

                if (!empty($context)) {
                    $log_entry .= ' [CONTEXT: ' . $context . ']';
                }

                $log_entry .= "\n";
                $log_entry .= $message . "\n";
                $log_entry .= str_repeat('-', 80) . "\n";

                // Write to file
                $result = file_put_contents($filename, $log_entry, FILE_APPEND | LOCK_EX);

                // Cleanup old logs
                self::cleanup_old_logs($dir);

                return $result !== false;

            } catch (Exception $e) {
                // Silent failure - logging system should not cause errors in production
                return false;
            }
        }

        /**
         * Log debug message
         */
        public static function debug($message, $category = self::CATEGORY_GENERAL, $context = '', $subDir = '')
        {
            return self::log($message, self::LEVEL_DEBUG, $category, $context, $subDir);
        }

        /**
         * Log info message
         */
        public static function info($message, $category = self::CATEGORY_GENERAL, $context = '', $subDir = '')
        {
            return self::log($message, self::LEVEL_INFO, $category, $context, $subDir);
        }

        /**
         * Log warning message
         */
        public static function warning($message, $category = self::CATEGORY_GENERAL, $context = '', $subDir = '')
        {
            return self::log($message, self::LEVEL_WARNING, $category, $context, $subDir);
        }

        /**
         * Log error message
         */
        public static function error($message, $category = self::CATEGORY_GENERAL, $context = '', $subDir = '')
        {
            return self::log($message, self::LEVEL_ERROR, $category, $context, $subDir);
        }

        /**
         * Log critical message
         */
        public static function critical($message, $category = self::CATEGORY_GENERAL, $context = '', $subDir = '')
        {
            return self::log($message, self::LEVEL_CRITICAL, $category, $context, $subDir);
        }

        /**
         * Log API-related messages
         */
        public static function api($message, $level = self::LEVEL_INFO, $context = '')
        {
            return self::log($message, $level, self::CATEGORY_API, $context);
        }

        /**
         * Log shortcode-related messages
         */
        public static function shortcode($message, $level = self::LEVEL_INFO, $context = '')
        {
            return self::log($message, $level, self::CATEGORY_SHORTCODE, $context);
        }

        /**
         * Log authentication-related messages
         */
        public static function auth($message, $level = self::LEVEL_INFO, $context = '')
        {
            return self::log($message, $level, self::CATEGORY_AUTH, $context);
        }

        /**
         * Log document-related messages
         */
        public static function document($message, $level = self::LEVEL_INFO, $context = '')
        {
            return self::log($message, $level, self::CATEGORY_DOCUMENT, $context);
        }

        /**
         * Log language-related messages
         */
        public static function language($message, $level = self::LEVEL_INFO, $context = '')
        {
            return self::log($message, $level, self::CATEGORY_LANGUAGE, $context);
        }

        /**
         * Clean up old log files
         *
         * @param string $dir Directory to clean
         */
        private static function cleanup_old_logs($dir)
        {
            try {
                $retention_days = LBFA_Option_Helper::getOption('log_retention_days', self::DEFAULT_RETENTION_DAYS);
                $retention_seconds = $retention_days * 24 * 3600;

                if (!is_dir($dir)) {
                    return;
                }

                $files = scandir($dir);
                if (!$files) {
                    return;
                }

                foreach ($files as $file) {
                    if ($file === '.' || $file === '..' || !preg_match('/\.log$/', $file)) {
                        continue;
                    }

                    $filepath = $dir . '/' . $file;
                    $file_modified = filemtime($filepath);

                    if ($file_modified && (time() - $file_modified) > $retention_seconds) {
                        wp_delete_file($filepath);
                    }
                }
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }

        /**
         * Get log files information
         *
         * @return array Log files info
         */
        public static function get_log_files_info()
        {
            self::init();

            $info = [
                'enabled' => self::$enabled,
                'logs_dir' => self::$logs_dir,
                'retention_days' => LBFA_Option_Helper::getOption('log_retention_days', self::DEFAULT_RETENTION_DAYS),
                'files' => []
            ];

            if (!self::$enabled || !is_dir(self::$logs_dir)) {
                return $info;
            }

            try {
                // Use scandir instead of glob for better Windows compatibility
                $files = [];
                if (is_dir(self::$logs_dir)) {
                    $dir_files = scandir(self::$logs_dir);
                    foreach ($dir_files as $file) {
                        if (preg_match('/\.log$/', $file)) {
                            $files[] = self::$logs_dir . '/' . $file;
                        }
                    }
                }

                foreach ($files as $file) {
                    $relative_path = str_replace(self::$logs_dir . '/', '', $file);
                    $info['files'][] = [
                        'path' => $relative_path,
                        'size' => filesize($file),
                        'modified' => filemtime($file),
                        'modified_human' => wp_date('Y-m-d H:i:s', filemtime($file))
                    ];
                }
            } catch (Exception $e) {
                $info['error'] = $e->getMessage();
            }

            return $info;
        }

        /**
         * Clear all log files
         *
         * @return bool Success status
         */
        public static function clear_all_logs()
        {
            self::init();

            if (!self::$enabled || !is_dir(self::$logs_dir)) {
                return false;
            }

            try {
                // Use scandir instead of glob for better Windows compatibility
                if (is_dir(self::$logs_dir)) {
                    $dir_files = scandir(self::$logs_dir);
                    foreach ($dir_files as $file) {
                        if (preg_match('/\.log$/', $file)) {
                            wp_delete_file(self::$logs_dir . '/' . $file);
                        }
                    }
                }

                return true;
            } catch (Exception $e) {
                return false;
            }
        }

        /**
         * Set log retention period
         *
         * @param int $days Number of days to retain logs
         */
        public static function set_retention_days($days)
        {
            $days = max(1, min(365, (int)$days)); // Between 1 and 365 days
            LBFA_Option_Helper::setOption('log_retention_days', $days);
        }

        /**
         * Get current retention period
         *
         * @return int Days
         */
        public static function get_retention_days()
        {
            return LBFA_Option_Helper::getOption('log_retention_days', self::DEFAULT_RETENTION_DAYS);
        }
    }
}
