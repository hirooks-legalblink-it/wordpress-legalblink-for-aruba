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
         * Render cookie consent banner.
         *
         * S#7701 Phase 5: dispatches between the legacy `/cookie-solution/embed`
         * and the new `/cookie-solution/embed-v2` based on the
         * `features.cookieBannerV2` capability. Uses a separate cache key for
         * v2 so an account that flips between the two never gets stale HTML.
         */
        public function render_cookie_banner()
        {
            if (!$this->should_show_banner()) {
                return;
            }

            $cache_duration = LBFA_Option_Helper::getOption('cache_duration', 30);
            $cache_duration_days = $cache_duration * 3600 * 24;

            $use_v2 = self::should_use_banner_v2();
            $cache_key = $use_v2 ? 'cookie_banner_snippet_v2' : 'cookie_banner_snippet';

            $banner_snippet = LBFA_Transient_Helper::get($cache_key);
            if ($banner_snippet === false) {
                $banner_snippet = $this->fetch_banner_snippet($use_v2);
                if (!empty($banner_snippet)) {
                    LBFA_Transient_Helper::set($cache_key, $banner_snippet, $cache_duration_days);
                }
            }

            if (!empty($banner_snippet)) {
                echo wp_kses($banner_snippet, self::get_script_allowed_html());
            }
        }

        /**
         * Whitelist for `<script>` snippets we inject via wp_kses.
         *
         * The v2 cookie banner snippet from the backend embeds `data-*`
         * attributes (license id, blocking mode, consent mode, TCF) that
         * the CMP loader reads at runtime. wp_kses strips any attribute
         * not explicitly listed here, so the data-* keys MUST be enumerated
         * — there is no wildcard for `data-*` in wp_kses.
         *
         * Reference: see backend `buildCookieBannerV2Snippet` for the full
         * attribute set, and `ACCESSIBILITY_WIDGET_SNIPPET` for the widget
         * snippet (which uses only `src` + `defer`).
         */
        public static function get_script_allowed_html()
        {
            return array(
                'script' => array(
                    'type' => array(),
                    'src' => array(),
                    'id' => array(),
                    'async' => array(),
                    'defer' => array(),
                    'crossorigin' => array(),
                    'integrity' => array(),
                    'referrerpolicy' => array(),
                    'nonce' => array(),
                    'data-license-id' => array(),
                    'data-blocking-mode' => array(),
                    'data-consent-mode' => array(),
                    'data-tcf-enabled' => array(),
                ),
            );
        }

        /**
         * Decide whether to use the v2 cookie banner endpoint based on the
         * cached capabilities. Falls back to false (legacy) when capabilities
         * have not been resolved yet, so existing flows keep working before
         * the admin opens the dashboard.
         */
        public static function should_use_banner_v2(): bool
        {
            return class_exists('LBFA_Capability_API_Controller')
                && LBFA_Capability_API_Controller::is_feature_enabled('cookieBannerV2');
        }

        public function fetch_banner_snippet($use_v2 = null)
        {
            $jwt_token = LBFA_Option_Helper::getOption('jwt_token');

            if (empty($jwt_token)) {
                return '';
            }

            if ($use_v2 === null) {
                $use_v2 = self::should_use_banner_v2();
            }

            $endpoint = $use_v2 ? '/cookie-solution/embed-v2' : '/cookie-solution/embed?language=it';
            $url = LBFA_Base_API_Controller::get_api_base_url() . $endpoint;
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

            LBFA_Logger::debug('Banner fetch response (v2=' . ($use_v2 ? '1' : '0') . '): ' . wp_json_encode($banner_data), LBFA_Logger::CATEGORY_GENERAL, 'fetch_banner_snippet');

            if ($code !== 200 || !isset($banner_data['html'])) {
                LBFA_Logger::warning('Error', LBFA_Logger::CATEGORY_GENERAL, 'fetch_banner_snippet');
                return '';
            }

            LBFA_Logger::info('Banner snippet fetched successfully (v2=' . ($use_v2 ? '1' : '0') . ')', LBFA_Logger::CATEGORY_GENERAL, 'fetch_banner_snippet');

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

            echo wp_kses($html, self::get_script_allowed_html());
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
