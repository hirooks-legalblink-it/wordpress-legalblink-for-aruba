<?php

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

if ( ! class_exists( 'LBFA_Multisite_Helper' ) ) {
    class LBFA_Multisite_Helper
    {
        /**
         * Check if WordPress is running in multisite mode
         * @return bool
         */
        public static function is_multisite()
        {
            return is_multisite();
        }

        /**
         * Check if current admin is network admin
         * @return bool
         */
        public static function is_network_admin()
        {
            return is_network_admin();
        }

        /**
         * Get current blog/site ID
         * @return int
         */
        public static function get_current_blog_id()
        {
            return get_current_blog_id();
        }

        /**
         * Get all sites in the network (if multisite)
         * @return array|false
         */
        public static function get_sites($args = array())
        {
            if (!is_multisite()) return false;
            if (function_exists('get_sites')) {
                return get_sites($args);
            }
            return false;
        }

        /**
         * Get current context: 'network', 'site', or 'frontend'
         * @return string
         */
        public static function get_context()
        {
            // Check if we're in a REST API request and have network context
            if (defined('REST_REQUEST') && REST_REQUEST) {
                // For REST API requests, check user capabilities directly without nonce verification
                // as REST API has its own authentication mechanisms
                if (current_user_can('manage_network_options')) {
                    return 'network';
                }
            }

            if (is_network_admin()) return 'network';
            if (is_admin()) return 'site';
            return 'frontend';
        }

        /**
         * Force network context (for specific operations)
         * @return bool
         */
        public static function is_network_context_forced()
        {
            return defined('LBFA_FORCE_NETWORK_CONTEXT') && LBFA_FORCE_NETWORK_CONTEXT;
        }
    }
}
