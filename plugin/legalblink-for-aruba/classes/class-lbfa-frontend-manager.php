<?php

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

if ( ! class_exists( 'LBFA_Frontend_Manager' ) ) {
    final class LBFA_Frontend_Manager
    {
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
         * Initialize frontend features
         */
        private function __construct()
        {
            add_action('wp_footer', [$this, 'render_cookie_banner']);
            add_action('wp_footer', [$this, 'render_accessibility_widget']);
        }

        /**
         * Render cookie consent banner
         */
        public function render_cookie_banner()
        {
            if (!$this->should_show_banner()) {
                return;
            }

            $cache_duration = LBFA_Option_Helper::getOption('cache_duration', 30);
            $cache_duration_days = $cache_duration * 3600 * 24;

            $banner_snippet = LBFA_Transient_Helper::get('cookie_banner_snippet');
            if ($banner_snippet === false) {
                $banner_snippet = $this->fetch_banner_snippet();
                if (!empty($banner_snippet)) {
                    LBFA_Transient_Helper::set('cookie_banner_snippet', $banner_snippet, $cache_duration_days);
                }
            }

            $allow_list = [
                'script' => [
                    'type' => [],
                    'src' => [],
                    'id' => [],
                    'async' => [],
                    'defer' => [],
                ],
            ];

            if (!empty($banner_snippet)) {
                echo wp_kses($banner_snippet, $allow_list);
            }
        }

        public function fetch_banner_snippet()
        {
            $jwt_token = LBFA_Option_Helper::getOption('jwt_token');

            if (empty($jwt_token)) {
                return '';
            }

            $url = LBFA_Base_API_Controller::get_api_base_url() . '/cookie-solution/embed?language=it';
            $response = wp_remote_get($url, array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $jwt_token,
                ),
                'timeout' => 30
            ));

            if (is_wp_error($response)) {
                LBFA_Logger::error('Error', LBFA_Logger::CATEGORY_GENERAL, 'fetch_banner_snippet');
                return '';
            }

            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $banner_data = json_decode($body, true);

            LBFA_Logger::debug('Banner fetch response: ' . wp_json_encode($banner_data), LBFA_Logger::CATEGORY_GENERAL, 'fetch_banner_snippet');

            if ($code !== 200 || !isset($banner_data['html'])) {
                LBFA_Logger::warning('Error', LBFA_Logger::CATEGORY_GENERAL, 'fetch_banner_snippet');
                return '';
            }

            LBFA_Logger::info('Banner snippet fetched successfully', LBFA_Logger::CATEGORY_GENERAL, 'fetch_banner_snippet');

            return $banner_data['html'];
        }

        /**
         * Check if cookie banner should be shown
         */
        private function should_show_banner()
        {
            return (bool)LBFA_Option_Helper::getOption('cookie_banner_enabled',false);
        }

        /**
         * Render the accessibility widget snippet on the public site.
         *
         * S#7701 Phase 4: read-only injection — the snippet itself comes from
         * the backend (`/accessibility/widget`) and is cached separately from
         * the cookie banner. The plugin only decides whether to inject it,
         * driven by the local toggle option `accessibility_widget_enabled`.
         * No injection happens when the backend reports warnings (missing
         * configuration, domain mismatch, expired) or when the snippet HTML
         * is empty.
         */
        public function render_accessibility_widget()
        {
            if (!$this->should_show_accessibility_widget()) {
                return;
            }

            $cache_duration = LBFA_Option_Helper::getOption('cache_duration', 30);
            $cache_duration_seconds = max(60, $cache_duration * 3600 * 24);

            $payload = LBFA_Transient_Helper::get('accessibility_widget_snippet');
            if ($payload === false) {
                $payload = $this->fetch_accessibility_widget_payload();
                if (is_array($payload)) {
                    LBFA_Transient_Helper::set('accessibility_widget_snippet', $payload, $cache_duration_seconds);
                }
            }

            if (!is_array($payload) || empty($payload['available']) || empty($payload['configured'])) {
                return;
            }

            if (!empty($payload['warnings'])) {
                return;
            }

            $html = isset($payload['html']) ? (string) $payload['html'] : '';
            if ($html === '') {
                return;
            }

            $allow_list = array(
                'script' => array(
                    'type' => array(),
                    'src' => array(),
                    'id' => array(),
                    'async' => array(),
                    'defer' => array(),
                ),
            );

            echo wp_kses($html, $allow_list);
        }

        /**
         * Decide whether the accessibility widget should be injected. Combines
         * the local toggle option with the requirement of a valid jwt session
         * (no point trying to fetch when unauthenticated).
         */
        private function should_show_accessibility_widget()
        {
            if (!(bool) LBFA_Option_Helper::getOption('accessibility_widget_enabled', false)) {
                return false;
            }
            $jwt = LBFA_Option_Helper::getOption('jwt_token');
            return !empty($jwt);
        }

        /**
         * Fetch the widget payload from the backend. Mirrors the shape that
         * LBFA_Accessibility_API_Controller::normalize_widget produces but
         * without the localEnabled tag (frontend doesn't need it).
         */
        public function fetch_accessibility_widget_payload()
        {
            $jwt_token = LBFA_Option_Helper::getOption('jwt_token');
            if (empty($jwt_token)) {
                return null;
            }

            $url = LBFA_Base_API_Controller::get_api_base_url() . '/accessibility/widget';
            $response = wp_remote_get($url, array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $jwt_token,
                ),
                'timeout' => 30,
            ));

            if (is_wp_error($response)) {
                LBFA_Logger::error('Error', LBFA_Logger::CATEGORY_GENERAL, 'fetch_accessibility_widget_payload');
                return null;
            }

            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if ($code !== 200 || !is_array($data)) {
                LBFA_Logger::warning('Accessibility widget fetch failed code=' . $code, LBFA_Logger::CATEGORY_GENERAL, 'fetch_accessibility_widget_payload');
                return null;
            }

            return array(
                'available' => (bool) ($data['available'] ?? false),
                'configured' => (bool) ($data['configured'] ?? false),
                'domain' => isset($data['domain']) ? (string) $data['domain'] : '',
                'html' => isset($data['html']) ? (string) $data['html'] : '',
                'warnings' => isset($data['warnings']) && is_array($data['warnings']) ? $data['warnings'] : array(),
            );
        }
    }
}
