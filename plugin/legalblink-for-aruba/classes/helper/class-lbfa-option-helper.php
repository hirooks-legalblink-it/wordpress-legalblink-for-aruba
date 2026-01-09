<?php

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

if ( ! class_exists( 'LBFA_Option_Helper' ) ) {
    class LBFA_Option_Helper
    {
        /**
         * Transient key prefix to avoid conflicts
         */
        const OPTION_PREFIX = 'lbfa_';

        /**
         * Get an option from the WordPress options table
         *
         * @param string $key The option key
         * @param mixed $default The default value if option doesn't exist
         * @return mixed
         */
        public static function getOption(string $key, $default = null)
        {
            if (LBFA_Multisite_Helper::is_multisite() &&
                (LBFA_Multisite_Helper::get_context() === 'network' || LBFA_Multisite_Helper::is_network_context_forced())) {
                return get_site_option(self::OPTION_PREFIX . $key, $default);
            }
            return get_option(self::OPTION_PREFIX . $key, $default);
        }

        /**
         * Set an option in the WordPress options table
         *
         * @param string $key The option key
         * @param mixed $value The option value
         * @return bool
         */
        public static function setOption(string $key, $value)
        {
            if (LBFA_Multisite_Helper::is_multisite() &&
                (LBFA_Multisite_Helper::get_context() === 'network' || LBFA_Multisite_Helper::is_network_context_forced())) {
                return update_site_option(self::OPTION_PREFIX . $key, $value);
            }
            return update_option(self::OPTION_PREFIX . $key, $value);
        }

        /**
         * Delete an option from the WordPress options table
         *
         * @param string $key The option key
         * @return bool
         */
        public static function deleteOption($key)
        {
            if (LBFA_Multisite_Helper::is_multisite() &&
                (LBFA_Multisite_Helper::get_context() === 'network' || LBFA_Multisite_Helper::is_network_context_forced())) {
                return delete_site_option(self::OPTION_PREFIX . $key);
            }
            return delete_option(self::OPTION_PREFIX . $key);
        }

        /**
         * Get language-specific option (for multilingual content)
         *
         * @param string $key The option key
         * @param string $language The language code
         * @param mixed $default The default value
         * @return mixed
         */
        public static function getLanguageOption(string $key, string $language = 'it', $default = '')
        {
            $key = "{$key}_{$language}";
            return self::getOption($key, $default);
        }

        /**
         * Set language-specific option (for multilingual content)
         *
         * @param string $key The option key
         * @param string $language The language code
         * @param mixed $value The value to set
         * @return bool
         */
        public static function setLanguageOption(string $key, $value, string $language = 'it')
        {
            $key = "{$key}_{$language}";
            return self::setOption($key, $value);
        }

        /**
         * Get timestamp option for language-specific content
         *
         * @param string $policy_type The policy type (privacy, cookie, cgv)
         * @param string $language The language code
         * @param mixed $default The default value
         * @return mixed
         */
        public static function getLanguageTimestamp($policy_type, $language, $default = '')
        {
            $key = "{$policy_type}_codes_updated_{$language}";
            return self::getOption($key, $default);
        }

        /**
         * Set timestamp option for language-specific content
         *
         * @param string $policy_type The policy type (privacy, cookie, cgv)
         * @param string $language The language code
         * @param mixed $value The timestamp value
         * @return bool
         */
        public static function setLanguageTimestamp($policy_type, $language, $value)
        {
            $key = "{$policy_type}_codes_updated_{$language}";
            return self::setOption($key, $value);
        }
    }
}
