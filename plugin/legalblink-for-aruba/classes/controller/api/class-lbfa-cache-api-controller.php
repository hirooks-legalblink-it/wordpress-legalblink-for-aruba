<?php

if (!defined('ABSPATH')) {
    die;
}

if (!class_exists('LBFA_Cache_API_Controller')) {
    /**
     * Cache API Controller
     * Handles all cache-related operations
     */
    class LBFA_Cache_API_Controller extends LBFA_Base_API_Controller
    {
        /**
         * Register cache-related routes
         */
        public function register_routes()
        {
            // Clear cache endpoint
            register_rest_route(self::get_api_namespace(), '/cache/clear', array(
                'methods' => 'POST',
                'callback' => array($this, 'clear_cache'),
                'permission_callback' => array($this, 'check_admin_permissions_with_nonce'),
            ));

            // Cache settings endpoint
            register_rest_route(self::get_api_namespace(), '/cache/settings', array(
                'methods' => array('GET', 'POST'),
                'callback' => array($this, 'handle_cache_settings'),
                'permission_callback' => array($this, 'check_admin_permissions_with_nonce'),
                'args' => array(
                    'cache_duration' => array(
                        'required' => false,
                        'type' => 'integer',
                    ),
                )
            ));
        }

        /**
         * Clear cache based on type
         */
        public function clear_cache()
        {
            try {
                $this->clear_all_cache();
                LBFA_Logger::info('Cache cleared successfully', LBFA_Logger::CATEGORY_CONFIG, 'clear_cache');
                return $this->create_api_response(true, array(), null,
                    /* translators: Success message when cache is cleared */
                    __('Cache svuotata con successo.', 'legalblink-for-aruba'));
            } catch (Exception $e) {
                return $this->create_error_response(
                    /* translators: Error message for unexpected cache clearing errors */
                    __('Errore imprevisto nella pulizia cache', 'legalblink-for-aruba'),
                    /* translators: English error message for cache clear exception */
                    __('Cache clear exception', 'legalblink-for-aruba')
                );
            }
        }

        /**
         * Handle cache settings (GET and POST)
         */
        public function handle_cache_settings($request)
        {
            if ($request->get_method() === 'GET') {
                return $this->get_cache_settings();
            }

            $cache_duration = $request->get_param('cache_duration');
            return $this->save_cache_settings($cache_duration);
        }

        /**
         * Get current cache settings
         */
        private function get_cache_settings()
        {
            $cache_duration = LBFA_Option_Helper::getOption('cache_duration', 30);

            return $this->create_api_response(true, array(
                'cache_duration' => (int)$cache_duration,
            ));
        }

        /**
         * Save cache settings
         */
        private function save_cache_settings($cache_duration)
        {
            $old_duration = (int)LBFA_Option_Helper::getOption('cache_duration', 30);

            // Clear all cache when duration changes
            if ($old_duration !== $cache_duration) {
                // Update cache duration
                LBFA_Option_Helper::setOption('cache_duration', (int)$cache_duration);
                $this->clear_all_cache();
            }

            return $this->create_api_response(true, array(
                'cache_duration' => (int)$cache_duration
            ), null,
                /* translators: Success message when cache settings are saved */
                __('Impostazioni cache salvate con successo.', 'legalblink-for-aruba'));
        }

        /**
         * Clear all policy cache
         */
        private function clear_all_cache()
        {
            LBFA_Transient_Helper::clearAll();
        }
    }
}
