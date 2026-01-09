<?php

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

if ( ! class_exists( 'LBFA_Transient_Helper' ) ) {
    /**
     * Helper class for managing WordPress transients
     * Provides utilities for caching data with expiration times
     */
    class LBFA_Transient_Helper
    {
        /**
         * Default cache duration in seconds (1 hour)
         */
        const DEFAULT_EXPIRATION = 3600;

        /**
         * Transient key prefix to avoid conflicts
         */
        const TRANSIENT_PREFIX = 'lbfa_';

        /**
         * Option name for the transients registry
         */
        const REGISTRY_OPTION = 'lbfa_transient_registry';

        /**
         * Get a transient value
         *
         * @param string $key The transient key
         * @param mixed $default The default value if transient doesn't exist or is expired
         * @return mixed
         */
        public static function get(string $key, $default = false)
        {
            $prefixed_key = self::TRANSIENT_PREFIX . $key;

            if (self::is_network_context()) {
                $value = get_site_transient($prefixed_key);
            } else {
                $value = get_transient($prefixed_key);
            }

            return ($value !== false) ? $value : $default;
        }

        /**
         * Set a transient value
         *
         * @param string $key The transient key
         * @param mixed $value The value to cache
         * @param int $expiration Expiration time in seconds (default: 1 hour)
         * @return bool
         */
        public static function set(string $key, $value, int $expiration = self::DEFAULT_EXPIRATION)
        {
            $prefixed_key = self::TRANSIENT_PREFIX . $key;

            $result = false;
            if (self::is_network_context()) {
                $result = set_site_transient($prefixed_key, $value, $expiration);
            } else {
                $result = set_transient($prefixed_key, $value, $expiration);
            }

            // Register key on success to allow safe clear/statistics without direct DB queries
            if ($result) {
                $size = strlen( maybe_serialize( $value ) );
                $expires_at = $expiration > 0 ? (int) current_time('timestamp') + (int) $expiration : 0;
                self::register_key($prefixed_key, $size, $expires_at);
            }

            return $result;
        }

        /**
         * Delete a transient
         *
         * @param string $key The transient key
         * @return bool
         */
        public static function delete(string $key)
        {
            $prefixed_key = self::TRANSIENT_PREFIX . $key;

            $result = false;
            if (self::is_network_context()) {
                $result = delete_site_transient($prefixed_key);
            } else {
                $result = delete_transient($prefixed_key);
            }

            if ($result) {
                self::unregister_key($prefixed_key);
            }

            return $result;
        }

        /**
         * Get or set a transient with a callback function
         * If the transient doesn't exist, executes the callback and caches the result
         *
         * @param string $key The transient key
         * @param callable $callback Function to execute if cache miss
         * @param int $expiration Expiration time in seconds
         * @return mixed
         */
        public static function remember(string $key, callable $callback, int $expiration = self::DEFAULT_EXPIRATION)
        {
            // Use null as default to detect a real cache miss (transient returns false when missing)
            $value = self::get($key, null);

            if ($value === null) {
                $value = call_user_func($callback);
                self::set($key, $value, $expiration);
            }

            return $value;
        }

        /**
         * Get language-specific transient
         *
         * @param string $key The transient key
         * @param string $language The language code
         * @param mixed $default The default value
         * @return mixed
         */
        public static function getLanguage(string $key, string $language = 'it', $default = false)
        {
            $language_key = "{$key}_{$language}";
            return self::get($language_key, $default);
        }

        /**
         * Set language-specific transient
         *
         * @param string $key The transient key
         * @param string $language The language code
         * @param mixed $value The value to cache
         * @param int $expiration Expiration time in seconds
         * @return bool
         */
        public static function setLanguage(string $key, string $language, $value, int $expiration = self::DEFAULT_EXPIRATION)
        {
            $language_key = "{$key}_{$language}";
            return self::set($language_key, $value, $expiration);
        }

        /**
         * Delete language-specific transient
         *
         * @param string $key The transient key
         * @param string $language The language code
         * @return bool
         */
        public static function deleteLanguage(string $key, string $language)
        {
            $language_key = "{$key}_{$language}";
            return self::delete($language_key);
        }

        /**
         * Cache WordPress pages list
         *
         * @param array $pages The pages data
         * @param int $expiration Expiration time in seconds (default: 30 minutes)
         * @return bool
         */
        public static function cacheWordPressPages(array $pages, int $expiration = self::DEFAULT_EXPIRATION)
        {
            return self::set('wordpress_pages', $pages, $expiration);
        }

        /**
         * Get cached WordPress pages
         *
         * @return array|null
         */
        public static function getCachedWordPressPages()
        {
            return self::get('wordpress_pages');
        }

        /**
         * Cache API response data
         *
         * @param string $endpoint The API endpoint identifier
         * @param mixed $data The response data to cache
         * @param int $expiration Expiration time in seconds
         * @return bool
         */
        public static function cacheApiResponse(string $endpoint, $data, int $expiration = self::DEFAULT_EXPIRATION)
        {
            $key = "api_response_{$endpoint}";
            return self::set($key, $data, $expiration);
        }

        /**
         * Get cached API response
         *
         * @param string $endpoint The API endpoint identifier
         * @return mixed|null
         */
        public static function getCachedApiResponse(string $endpoint)
        {
            $key = "api_response_{$endpoint}";
            return self::get($key);
        }

        /**
         * Clear all plugin-related transients
         * Useful for cache invalidation
         *
         * @return void
         */
        public static function clearAll()
        {
            // Iterate over the registry and delete keys using WP APIs (no direct DB queries)
            $registry = self::get_registry();
            if (is_array($registry) && ! empty($registry)) {
                foreach (array_keys($registry) as $prefixed_key) {
                    if (self::is_network_context()) {
                        delete_site_transient($prefixed_key);
                    } else {
                        delete_transient($prefixed_key);
                    }
                }
            }
            // Reset registry
            self::save_registry(array());
        }

        /**
         * Get cache statistics
         *
         * @return array
         */
        public static function getStats()
        {
            $stats = array(
                'total_transients' => 0,
                'size_estimate' => 0
            );

            $registry = self::get_registry();
            if (! is_array($registry)) {
                $registry = array();
            }

            $stats['total_transients'] = (int) count($registry);

            $dirty = false;
            $size_sum = 0;
            foreach ($registry as $prefixed_key => $meta) {
                if (isset($meta['size']) && is_numeric($meta['size'])) {
                    $size_sum += (int) $meta['size'];
                    continue;
                }

                // If size missing, try to compute from current transient value
                $value = self::is_network_context() ? get_site_transient($prefixed_key) : get_transient($prefixed_key);
                if ($value === false) {
                    // Transient no longer exists; clean up registry entry
                    unset($registry[$prefixed_key]);
                    $dirty = true;
                    continue;
                }
                $computed_size = strlen( maybe_serialize( $value ) );

                $size_sum += $computed_size;
                $registry[$prefixed_key]['size'] = $computed_size;
                $dirty = true;
            }

            if ($dirty) {
                self::save_registry($registry);
            }

            $stats['size_estimate'] = (int) $size_sum;

            return $stats;
        }

        /**
         * Check if a transient exists and is not expired
         *
         * @param string $key The transient key
         * @return bool
         */
        public static function exists(string $key)
        {
            $prefixed_key = self::TRANSIENT_PREFIX . $key;

            if (self::is_network_context()) {
                return get_site_transient($prefixed_key) !== false;
            } else {
                return get_transient($prefixed_key) !== false;
            }
        }

        /**
         * Set transient with timestamp for tracking when data was cached
         *
         * @param string $key The transient key
         * @param mixed $value The value to cache
         * @param int $expiration Expiration time in seconds
         * @return bool
         */
        public static function setWithTimestamp(string $key, $value, int $expiration = self::DEFAULT_EXPIRATION)
        {
            $data = array(
                'value' => $value,
                'cached_at' => current_time('timestamp'),
                'expires_at' => current_time('timestamp') + $expiration
            );

            return self::set($key, $data, $expiration);
        }

        /**
         * Get transient value with timestamp information
         *
         * @param string $key The transient key
         * @param mixed $default The default value
         * @return array|mixed
         */
        public static function getWithTimestamp(string $key, $default = null)
        {
            $data = self::get($key);

            if ($data && is_array($data) && isset($data['value'], $data['cached_at'])) {
                return $data;
            }

            return $default;
        }

        // ----------------------
        // Internal registry utils
        // ----------------------

        /**
         * Determine if we are in a network (multisite) context for transients
         *
         * @return bool
         */
        private static function is_network_context()
        {
            return ( function_exists('is_multisite') && LBFA_Multisite_Helper::is_multisite() )
                && ( LBFA_Multisite_Helper::get_context() === 'network' || LBFA_Multisite_Helper::is_network_context_forced() );
        }

        /**
         * Get registry array from the appropriate option storage.
         *
         * @return array
         */
        private static function get_registry()
        {
            $registry = self::is_network_context()
                ? get_site_option(self::REGISTRY_OPTION, array())
                : get_option(self::REGISTRY_OPTION, array());

            return is_array($registry) ? $registry : array();
        }

        /**
         * Persist registry array to the appropriate option storage.
         *
         * @param array $registry
         * @return void
         */
        private static function save_registry(array $registry)
        {
            if (self::is_network_context()) {
                update_site_option(self::REGISTRY_OPTION, $registry);
            } else {
                update_option(self::REGISTRY_OPTION, $registry);
            }
        }

        /**
         * Add or update a key in the registry with optional metadata
         *
         * @param string $prefixed_key
         * @param int $size
         * @param int $expires_at
         * @return void
         */
        private static function register_key($prefixed_key, $size = 0, $expires_at = 0)
        {
            $registry = self::get_registry();
            $registry[$prefixed_key] = array(
                'size' => (int) $size,
                'expires_at' => (int) $expires_at,
            );
            self::save_registry($registry);
        }

        /**
         * Remove a key from the registry
         *
         * @param string $prefixed_key
         * @return void
         */
        private static function unregister_key($prefixed_key)
        {
            $registry = self::get_registry();
            if (isset($registry[$prefixed_key])) {
                unset($registry[$prefixed_key]);
                self::save_registry($registry);
            }
        }
    }
}
