<?php

if (!defined('ABSPATH')) {
    die;
}

if (!class_exists('LBFA_External_API_Controller')) {
    /**
     * External API Controller
     * Handles communication with external services (languages, branding, upsell)
     */
    class LBFA_External_API_Controller extends LBFA_Base_API_Controller
    {
        /**
         * Register external API routes
         */
        public function register_routes()
        {
            // Get languages
            register_rest_route(self::get_api_namespace(), '/languages', array(
                'methods' => 'GET',
                'callback' => array($this, 'get_languages'),
                'permission_callback' => array($this, 'check_admin_permissions_with_nonce')
            ));

            // Get branding
            register_rest_route(self::get_api_namespace(), '/branding', array(
                'methods' => 'GET',
                'callback' => array($this, 'get_branding'),
                'permission_callback' => array($this, 'check_admin_permissions_with_nonce')
            ));
        }

        /**
         * Get available languages from API
         */
        public function get_languages()
        {
            try {
                // Check cache first
                $cached_languages = LBFA_Transient_Helper::get('languages');
                if ($cached_languages !== false) {
                    return $this->create_api_response(true, $cached_languages);
                }
                $jwt_token = LBFA_Option_Helper::getOption('jwt_token');

                if (empty($jwt_token)) {
                    return $this->create_error_response(
                        /* translators: Error message when authentication credentials are missing */
                        __('Credenziali mancanti.', 'legalblink-for-aruba'),
                        /* translators: English error message for missing credentials */
                        __('Missing credentials', 'legalblink-for-aruba')
                    );
                }

                $url = self::get_api_base_url() . '/languages';
                $response = wp_remote_get($url, array(
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $jwt_token,
                    ),
                    'timeout' => 30
                ));

                if (is_wp_error($response)) {
                    return $this->create_error_response(
                        /* translators: Error message prefix for languages request errors, followed by the actual error */
                        __('Errore nella richiesta lingue: ', 'legalblink-for-aruba') . $response->get_error_message(),
                        /* translators: English error message for languages request failure */
                        __('Languages request failed', 'legalblink-for-aruba')
                    );
                }

                $code = wp_remote_retrieve_response_code($response);
                if ($code !== 200) {
                    return $this->create_error_response(
                        /* translators: %d is the HTTP response code for languages API error */
                        sprintf(__('Errore API lingue: %d', 'legalblink-for-aruba'), $code),
                        /* translators: English error message for languages API error */
                        __('Languages API error', 'legalblink-for-aruba')
                    );
                }

                $body = wp_remote_retrieve_body($response);
                $languages_data = json_decode($body, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    return $this->create_error_response(
                        /* translators: Error message when languages response parsing fails */
                        __('Errore nel parsing della risposta lingue', 'legalblink-for-aruba'),
                        /* translators: English error message for languages parse error */
                        __('Languages parse error', 'legalblink-for-aruba')
                    );
                }

                // Cache for 1 hour
                LBFA_Transient_Helper::set('languages', $languages_data, 3600);

                // Log the operation
                LBFA_Logger::info('Languages fetched and cached successfully', LBFA_Logger::CATEGORY_API, 'get_languages');

                return $this->create_api_response(true, $languages_data);

            } catch (Exception $e) {
                LBFA_Logger::error('Languages retrieval failed: ' . $e->getMessage(), LBFA_Logger::CATEGORY_API, 'get_languages');
                return $this->create_error_response(
                    /* translators: Error message for unexpected languages retrieval errors */
                    __('Errore imprevisto nel recupero lingue', 'legalblink-for-aruba'),
                    /* translators: English error message for languages exception */
                    __('Languages exception', 'legalblink-for-aruba')
                );
            }
        }

        /**
         * Get branding data from API
         */
        public function get_branding()
        {
            try {
                // Check cache first
                $cached_branding = LBFA_Transient_Helper::get('branding_data');
                if ($cached_branding !== false) {
                    return $this->create_api_response(true, $cached_branding);
                }

                $jwt_token = LBFA_Option_Helper::getOption('jwt_token');

                if (empty($jwt_token)) {
                    return $this->create_error_response(
                        /* translators: Error message when authentication credentials are missing */
                        __('Credenziali mancanti.', 'legalblink-for-aruba'),
                        /* translators: English error message for missing credentials */
                        __('Missing credentials', 'legalblink-for-aruba')
                    );
                }

                $url = self::get_api_base_url() . '/config/cobranding';
                $response = wp_remote_get($url, array(
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $jwt_token,
                    ),
                    'timeout' => 30
                ));

                if (is_wp_error($response)) {
                    return $this->create_error_response(
                        /* translators: Error message prefix for branding request errors, followed by the actual error */
                        __('Errore nella richiesta branding: ', 'legalblink-for-aruba') . $response->get_error_message(),
                        /* translators: English error message for branding request failure */
                        __('Branding request failed', 'legalblink-for-aruba')
                    );
                }

                $code = wp_remote_retrieve_response_code($response);
                if ($code !== 200) {
                    return $this->create_error_response(
                        /* translators: %d is the HTTP response code for branding API error */
                        sprintf(__('Errore API branding: %d', 'legalblink-for-aruba'), $code),
                        /* translators: English error message for branding API error */
                        __('Branding API error', 'legalblink-for-aruba')
                    );
                }

                $body = wp_remote_retrieve_body($response);
                $branding_data = json_decode($body, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    return $this->create_error_response(
                        /* translators: Error message when branding response parsing fails */
                        __('Errore nel parsing della risposta branding', 'legalblink-for-aruba'),
                        /* translators: English error message for branding parse error */
                        __('Branding parse error', 'legalblink-for-aruba')
                    );
                }

                // Cache for 1 hour
                LBFA_Transient_Helper::set('branding_data', $branding_data, 3600);

                // Log the operation
                LBFA_Logger::info('Branding data fetched and cached successfully', LBFA_Logger::CATEGORY_API, 'get_branding');

                return $this->create_api_response(true, $branding_data);

            } catch (Exception $e) {
                LBFA_Logger::error('Branding retrieval failed: ' . $e->getMessage(), LBFA_Logger::CATEGORY_API, 'get_branding');
                return $this->create_error_response(
                    /* translators: Error message for unexpected branding retrieval errors */
                    __('Errore imprevisto nel recupero branding', 'legalblink-for-aruba'),
                    /* translators: English error message for branding exception */
                    __('Branding exception', 'legalblink-for-aruba')
                );
            }
        }
    }
}
