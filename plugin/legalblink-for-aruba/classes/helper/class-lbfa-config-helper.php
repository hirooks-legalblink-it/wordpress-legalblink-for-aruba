<?php

if (!defined('ABSPATH')) {
    die;
}

if (!class_exists('LBFA_Config_Helper')) {
    /**
     * Configuration Helper
     * Manages plugin configuration loading and access
     */
    class LBFA_Config_Helper
    {
        /**
         * Loaded configuration
         * @var array|null
         */
        private static $config = null;

        /**
         * Default configuration values
         * @var array
         */
        private static $defaults = array(
            'api' => array(
                'namespace' => 'lbfa/v1',
                'base_url' => 'https://app.legalblink.it/api/integrations/wordpress',
                'bearer_token' => '',
                'rate_limit' => 60,
                'cache_time' => 3600,
            ),
        );

        /**
         * Load configuration from file
         *
         * @return array Configuration array
         */
        private static function load_config()
        {
            if (self::$config !== null) {
                return self::$config;
            }

            $config_file = LBFA_PLUGIN_DIR . 'config.php';

            if (file_exists($config_file)) {
                $loaded_config = require $config_file;

                if (is_array($loaded_config)) {
                    // Merge with defaults to ensure all keys exist
                    self::$config = array_replace_recursive(self::$defaults, $loaded_config);
                } else {
                    LBFA_Logger::warning(
                        'Config file does not return an array, using defaults',
                        LBFA_Logger::CATEGORY_GENERAL,
                        'LBFA_Config_Helper::load_config'
                    );
                    self::$config = self::$defaults;
                }
            } else {
                LBFA_Logger::warning(
                    'Config file not found at: ' . $config_file . ', using defaults',
                    LBFA_Logger::CATEGORY_GENERAL,
                    'LBFA_Config_Helper::load_config'
                );
                self::$config = self::$defaults;
            }

            return self::$config;
        }

        /**
         * Get configuration value by key path
         *
         * @param string $key Dot-notation key (e.g., 'api.namespace')
         * @param mixed $default Default value if key not found
         * @return mixed Configuration value
         */
        public static function get($key, $default = null)
        {
            $config = self::load_config();

            $keys = explode('.', $key);
            $value = $config;

            foreach ($keys as $k) {
                if (!is_array($value) || !isset($value[$k])) {
                    return $default;
                }
                $value = $value[$k];
            }

            return $value;
        }

        /**
         * Get all API configuration
         *
         * @return array API configuration array
         */
        public static function get_api_config()
        {
            return self::get('api', self::$defaults['api']);
        }

        /**
         * Get API namespace
         *
         * @return string API namespace
         */
        public static function get_api_namespace()
        {
            return self::get('api.namespace', self::$defaults['api']['namespace']);
        }

        /**
         * Get API base URL
         *
         * @return string API base URL
         */
        public static function get_api_base_url()
        {
            return self::get('api.base_url', self::$defaults['api']['base_url']);
        }

        /**
         * Get API bearer token
         *
         * @return string API bearer token
         */
        public static function get_api_bearer_token()
        {
            return self::get('api.bearer_token', self::$defaults['api']['bearer_token']);
        }

        /**
         * Get API rate limit
         *
         * @return int API rate limit (calls per minute)
         */
        public static function get_api_rate_limit()
        {
            return (int) self::get('api.rate_limit', self::$defaults['api']['rate_limit']);
        }

        /**
         * Get API cache time
         *
         * @return int API cache time in seconds
         */
        public static function get_api_cache_time()
        {
            return (int) self::get('api.cache_time', self::$defaults['api']['cache_time']);
        }

        /**
         * Check if configuration is valid
         *
         * @return bool True if configuration is valid
         */
        public static function is_valid()
        {
            $config = self::load_config();

            // Check if bearer token is set and not the default value
            $bearer_token = self::get_api_bearer_token();

            return !empty($bearer_token) && $bearer_token !== 'your-api-token-here';
        }

        /**
         * Reset loaded configuration (useful for testing)
         */
        public static function reset()
        {
            self::$config = null;
        }
    }
}

