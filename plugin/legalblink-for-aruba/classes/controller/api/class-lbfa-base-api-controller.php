<?php

if (!defined('ABSPATH')) {
    die;
}

if (!class_exists('LBFA_Base_API_Controller')) {
    /**
     * Base API Controller
     * Contains common functionality shared across API controllers
     */
    abstract class LBFA_Base_API_Controller
    {
        /**
         * Get API namespace for endpoints
         *
         * @return string API namespace
         */
        public static function get_api_namespace()
        {
            return LBFA_Config_Helper::get_api_namespace();
        }

        /**
         * Get API base URL
         *
         * @return string API base URL
         */
        public static function get_api_base_url()
        {
            return LBFA_Config_Helper::get_api_base_url();
        }

        /**
         * Get API bearer token
         *
         * @return string API bearer token
         */
        public static function get_api_bearer_token()
        {
            return LBFA_Config_Helper::get_api_bearer_token();
        }

        /**
         * Get API rate limit
         *
         * @return int API rate limit (calls per minute per user)
         */
        public static function get_api_rate_limit()
        {
            return LBFA_Config_Helper::get_api_rate_limit();
        }

        /**
         * Get API cache time
         *
         * @return int API cache time in seconds
         */
        public static function get_api_cache_time()
        {
            return LBFA_Config_Helper::get_api_cache_time();
        }

        /**
         * Create standardized API response
         *
         * @param bool $success Whether the operation was successful
         * @param array $data Response data
         * @param array|null $errors Array of error messages or null
         * @param string $message Optional message
         * @param int $http_status HTTP status code (200 for success, others for errors)
         * @return WP_REST_Response
         */
        protected function create_api_response($success = true, $data = array(), $errors = null, $message = '', $http_status = 200)
        {
            $response = array(
                'success' => (bool)$success,
                'data' => is_array($data) ? $data : array(),
                'errors' => $errors
            );

            if (!empty($message)) {
                $response['message'] = $message;
            }

            // For backwards compatibility, if data contains direct values, preserve them
            if ($success && !empty($data) && !is_array($data)) {
                $response['data'] = $data;
            }

            return new WP_REST_Response($response, $http_status);
        }

        /**
         * Create standardized error response
         *
         * @param string|array $errors Error message(s)
         * @param string $message Optional error message
         * @param array $data Optional additional data
         * @return WP_REST_Response
         */
        protected function create_error_response($errors, $message = '', $data = array())
        {
            // Normalize errors to array
            if (is_string($errors)) {
                $errors = array($errors);
            }

            return $this->create_api_response(
                false,
                $data,
                $errors,
                $message,
                200 // Always return 200 as per requirements
            );
        }

        /**
         * Verify REST nonce
         */
        protected function verify_rest_nonce($request)
        {
            return wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest');
        }

        /**
         * Check admin permissions with nonce verification
         */
        public function check_admin_permissions_with_nonce($request)
        {
            if (!$this->verify_rest_nonce($request)) {
                return false;
            }

            return current_user_can('manage_options');
        }

        /**
         * Rate limiting check
         */
        protected function check_rate_limit()
        {
            $user_id = get_current_user_id();
            $rate_limit_key = 'rate_limit_' . $user_id;
            $current_count = LBFA_Transient_Helper::get($rate_limit_key, false);

            if ($current_count === false) {
                LBFA_Transient_Helper::set($rate_limit_key, 1, MINUTE_IN_SECONDS);
                return true;
            }

            if ($current_count >= self::get_api_rate_limit()) {
                return new WP_Error(
                    'rate_limit_exceeded',
                    /* translators: Error message when API rate limit is exceeded */
                    __('Limite di chiamate API superato. Riprova tra un minuto.', 'legalblink-for-aruba'),
                    array('status' => 429)
                );
            }

            LBFA_Transient_Helper::set($rate_limit_key, $current_count + 1, MINUTE_IN_SECONDS);
            return true;
        }

        /**
         * Sanitize and validate input recursively
         */
        protected function sanitize_and_validate_input($input, $options = array())
        {
            if (is_array($input)) {
                return array_map(function($item) use ($options) {
                    return $this->sanitize_and_validate_input($item, $options);
                }, $input);
            } else {
                $type = isset($options['type']) ? $options['type'] : 'text';
                switch ($type) {
                    case 'email':
                        return sanitize_email($input);
                    case 'url':
                        return esc_url_raw($input);
                    case 'textarea':
                        return sanitize_textarea_field($input);
                    case 'html':
                        return wp_kses_post($input);
                    case 'int':
                        return (int)$input;
                    case 'bool':
                        return (bool)$input;
                    default:
                        return sanitize_text_field($input);
                }
            }
        }

        /**
         * Abstract method to register routes - must be implemented by child classes
         */
        abstract public function register_routes();
    }
}
