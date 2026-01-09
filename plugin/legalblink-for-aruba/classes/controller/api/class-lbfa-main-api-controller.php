<?php

if (!defined('ABSPATH')) {
    die;
}

if (!class_exists('LBFA_Main_API_Controller')) {
    /**
     * Main API Controller
     * Coordinates all API controllers and handles general functionality
     */
    final class LBFA_Main_API_Controller extends LBFA_Base_API_Controller
    {
        /**
         * Individual controller instances
         */
        private $cache_controller;
        private $auth_controller;
        private $external_controller;
        private $document_controller;

        private static $instance;

        /**
         * Get singleton instance
         */
        public static function get_instance()
        {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Initialize API controllers
         */
        private function __construct()
        {
            // Initialize individual controllers
            $this->cache_controller = new LBFA_Cache_API_Controller();
            $this->auth_controller = new LBFA_Auth_API_Controller();
            $this->external_controller = new LBFA_External_API_Controller();
            $this->document_controller = new LBFA_Document_API_Controller();

            // Register main API routes
            add_action('rest_api_init', array($this, 'register_routes'));
            add_action('rest_api_init', array($this, 'register_sub_controllers'));
        }

        /**
         * Register routes for sub-controllers
         */
        public function register_sub_controllers()
        {
            $this->cache_controller->register_routes();
            $this->auth_controller->register_routes();
            $this->external_controller->register_routes();
            $this->document_controller->register_routes();
        }

        /**
         * Register main API routes
         */
        public function register_routes()
        {
            // Banner data endpoint
            register_rest_route(self::get_api_namespace(), '/banner', array(
                'methods' => array('GET', 'PUT'),
                'callback' => array($this, 'handle_banner_data'),
                'permission_callback' => array($this, 'check_admin_permissions_with_nonce'),
                'args' => array(
                    'enabled' => array(
                        'required' => false,
                        'type' => 'boolean'
                    ),
                )
            ));

            // WordPress pages endpoint
            register_rest_route(self::get_api_namespace(), '/pages', array(
                'methods' => 'GET',
                'callback' => array($this, 'get_wordpress_pages'),
                'permission_callback' => array($this, 'check_admin_permissions_with_nonce')
            ));
        }

        /**
         * Handle banner data requests
         */
        public function handle_banner_data($request)
        {
            if ($request->get_method() === 'GET') {
                return $this->get_banner_data();
            } elseif ($request->get_method() === 'PUT') {
                return $this->set_banner_data($request);
            } else {
                return $this->create_error_response(
                    /* translators: Error message when an unsupported HTTP method is used */
                    __('Metodo non supportato.', 'legalblink-for-aruba'),
                    /* translators: English error message for unsupported method */
                    __('Method not allowed', 'legalblink-for-aruba')
                );
            }
        }

        /**
         * Get banner data for cookie consent
         */
        public function get_banner_data()
        {
            try {
                $banner_snippet = $this->fetch_banner_snippet();
                LBFA_Option_Helper::setOption('cookie_banner_html_code', $banner_snippet);

                $banner_data = array(
                    'enabled' => (bool)LBFA_Option_Helper::getOption('cookie_banner_enabled', false),
                    'html' => LBFA_Option_Helper::getOption('cookie_banner_html_code', ''),
                );

                LBFA_Logger::info('Banner data fetched and cached successfully', LBFA_Logger::CATEGORY_API, 'get_banner_data');

                return $this->create_api_response(true, $banner_data);
            } catch (Exception $e) {
                return $this->create_error_response(
                    /* translators: Error message for unexpected banner data retrieval errors */
                    __('Errore imprevisto nel recupero dati banner', 'legalblink-for-aruba'),
                    /* translators: English error message for banner data exception */
                    __('Banner data exception', 'legalblink-for-aruba')
                );
            }
        }

        /**
         * Set banner data for cookie consent
         */
        public function set_banner_data($request)
        {
            $enabled = $this->sanitize_and_validate_input($request->get_param('enabled'), array('type' => 'bool'));

            // Update options
            LBFA_Option_Helper::setOption('cookie_banner_enabled', $enabled);

            // Clear cache
            $cache_key = 'banner';
            LBFA_Transient_Helper::delete($cache_key);

            return $this->create_api_response(true, array(
                'enabled' => $enabled,
            ), null,
                /* translators: Success message when banner settings are updated */
                __('Impostazioni banner aggiornate con successo.', 'legalblink-for-aruba'));
        }

        public function fetch_banner_snippet()
        {
            $jwt_token = LBFA_Option_Helper::getOption('jwt_token');

            if (empty($jwt_token)) {
                return $this->create_error_response(
                    /* translators: Error message when authentication credentials are missing */
                    __('Credenziali mancanti.', 'legalblink-for-aruba'),
                    /* translators: English error message for missing credentials */
                    __('Missing credentials', 'legalblink-for-aruba')
                );
            }

            $url = self::get_api_base_url() . '/cookie-solution/embed?language=it';
            $response = wp_remote_get($url, array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $jwt_token,
                ),
                'timeout' => 30
            ));

            if (is_wp_error($response)) {
                // Log request error
                LBFA_Logger::error('Error', LBFA_Logger::CATEGORY_GENERAL, 'fetch_banner_snippet');
                return '';
            }

            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $banner_data = json_decode($body, true);

            LBFA_Logger::debug('Banner fetch response: ' . wp_json_encode($banner_data), LBFA_Logger::CATEGORY_GENERAL, 'fetch_banner_snippet');

            if ($code !== 200 || !isset($banner_data['html'])) {
                // Log failed verification
                LBFA_Logger::warning('Error', LBFA_Logger::CATEGORY_GENERAL, 'fetch_banner_snippet');
                return $this->create_error_response(
                    /* translators: %d is the HTTP response code for banner snippet fetch error */
                    sprintf(__('Errore nel recupero snippet banner: %d', 'legalblink-for-aruba'), $code),
                    /* translators: English error message for banner snippet fetch failure */
                    __('Banner snippet fetch failed', 'legalblink-for-aruba')
                );
            }

            // Log successful verification
            LBFA_Logger::info('Banner snippet fetched successfully', LBFA_Logger::CATEGORY_GENERAL, 'fetch_banner_snippet');

            return $banner_data['html'];
        }

        /**
         * Get WordPress pages
         */
        public function get_wordpress_pages()
        {
            try {
                $pages = get_pages(array(
                    'sort_order' => 'ASC',
                    'sort_column' => 'post_title',
                    'post_type' => 'page',
                    'post_status' => 'publish',
                    'number' => 100, // Limit results for better performance
                    'hierarchical' => 0 // Disable hierarchical sorting for better performance
                ));

                $formatted_pages = array();
                foreach ($pages as $page) {
                    $formatted_pages[] = array(
                        'id' => $page->ID,
                        'title' => $page->post_title,
                        'slug' => $page->post_name,
                        'url' => get_permalink($page->ID),
                        'content_length' => strlen($page->post_content),
                        'last_modified' => $page->post_modified
                    );
                }

                return $this->create_api_response(true, array(
                    'pages' => $formatted_pages,
                    'total' => count($formatted_pages)
                ));

            } catch (Exception $e) {
                return $this->create_error_response(
                    /* translators: Error message for unexpected WordPress pages retrieval errors */
                    __('Errore nel recupero delle pagine WordPress', 'legalblink-for-aruba'),
                    /* translators: English error message for pages retrieval exception */
                    __('Pages retrieval exception', 'legalblink-for-aruba')
                );
            }
        }
    }
}
