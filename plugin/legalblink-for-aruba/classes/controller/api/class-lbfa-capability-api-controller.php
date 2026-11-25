<?php

if (!defined('ABSPATH')) {
    die;
}

if (!class_exists('LBFA_Capability_API_Controller')) {
    /**
     * Capability API Controller (S#7701 mixed-mode foundation).
     *
     * Proxies the backend `GET /capabilities` payload so the admin UI can drive
     * tab visibility from a single capability-driven source instead of inferring
     * features from legacy GDPR documents or `/users/me`.
     *
     * The plugin never derives capabilities locally: the backend response
     * (`mode`, `features`, `documents`, `resources`, `warnings`) is the only
     * source of truth.
     */
    class LBFA_Capability_API_Controller extends LBFA_Base_API_Controller
    {
        /**
         * Cache key (registered automatically by LBFA_Transient_Helper for clear-all).
         */
        const CACHE_KEY = 'capabilities';

        /**
         * Cache used by get_cached_or_fetch() for the public site. It is kept
         * apart from CACHE_KEY so an answer fetched without an admin session
         * (e.g. mode "none" for a revoked token) never reaches the panel.
         */
        const PUBLIC_CACHE_KEY = 'capabilities_public';

        /**
         * Marker that keeps get_cached_or_fetch() from calling the backend:
         * set while a fetch is in progress (concurrent page views keep the
         * cached banner) and after a failure, so an outage does not add a
         * request to every public page view.
         */
        const FETCH_BACKOFF_KEY = 'capabilities_fetch_backoff';
        const FETCH_IN_PROGRESS_SECONDS = 60;
        const FETCH_BACKOFF_SECONDS = 300;

        /**
         * Short timeout: the fetch runs during a public page view.
         */
        const FETCH_TIMEOUT_SECONDS = 5;

        /**
         * Check whether a feature flag is enabled in the cached capabilities.
         *
         * Returns false when capabilities haven't been resolved yet (e.g. the
         * plugin is rendering the cookie banner before the admin opened the
         * dashboard) — callers should treat that as a safe default to legacy
         * behaviour rather than blocking rendering.
         */
        public static function is_feature_enabled(string $feature): bool
        {
            $capabilities = LBFA_Transient_Helper::get(self::CACHE_KEY);
            if (!is_array($capabilities) || !isset($capabilities['features'][$feature])) {
                return false;
            }
            return (bool) $capabilities['features'][$feature];
        }

        /**
         * Capabilities for callers outside the admin panel, such as the public
         * site (S#7920). Reading the panel's cache alone made the site fall
         * back to the legacy cookie banner once that cache expired (1 hour) or
         * was cleared by a plugin update, until an admin reopened the panel.
         *
         * Reads the panel's cache, then PUBLIC_CACHE_KEY, and on a miss fetches
         * the capabilities and stores them in PUBLIC_CACHE_KEY only. Returns
         * null when they are unknown: no session, a fetch in progress, or a
         * failure in the last FETCH_BACKOFF_SECONDS.
         *
         * @return array|null Normalized capabilities, or null when unknown.
         */
        public static function get_cached_or_fetch()
        {
            foreach (array(self::CACHE_KEY, self::PUBLIC_CACHE_KEY) as $key) {
                $cached = LBFA_Transient_Helper::get($key);
                if (is_array($cached)) {
                    return $cached;
                }
            }

            $jwt_token = LBFA_Option_Helper::getOption('jwt_token');
            if (empty($jwt_token) || LBFA_Transient_Helper::get(self::FETCH_BACKOFF_KEY) !== false) {
                return null;
            }

            LBFA_Transient_Helper::set(self::FETCH_BACKOFF_KEY, 1, self::FETCH_IN_PROGRESS_SECONDS);
            $response = wp_remote_get(self::get_api_base_url() . '/capabilities', array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $jwt_token,
                ),
                'timeout' => self::FETCH_TIMEOUT_SECONDS,
            ));
            $payload = is_wp_error($response) ? null : json_decode(wp_remote_retrieve_body($response), true);

            if (!is_array($payload) || wp_remote_retrieve_response_code($response) !== 200) {
                $reason = is_wp_error($response)
                    ? $response->get_error_message()
                    : 'HTTP ' . wp_remote_retrieve_response_code($response);
                LBFA_Logger::warning('Capabilities fetch for the public site failed: ' . $reason, LBFA_Logger::CATEGORY_API, 'get_cached_or_fetch');
                LBFA_Transient_Helper::set(self::FETCH_BACKOFF_KEY, 1, self::FETCH_BACKOFF_SECONDS);
                return null;
            }

            $normalized = (new self())->normalize_capabilities($payload);
            LBFA_Transient_Helper::set(self::PUBLIC_CACHE_KEY, $normalized, max(60, self::get_api_cache_time()));
            LBFA_Transient_Helper::delete(self::FETCH_BACKOFF_KEY);
            return $normalized;
        }

        public function register_routes()
        {
            register_rest_route(self::get_api_namespace(), '/capabilities', array(
                'methods' => 'GET',
                'callback' => array($this, 'get_capabilities'),
                'permission_callback' => array($this, 'check_admin_permissions_with_nonce'),
            ));
        }

        /**
         * Return the capability payload, hitting the backend on cache miss.
         */
        public function get_capabilities()
        {
            try {
                $cached = LBFA_Transient_Helper::get(self::CACHE_KEY);
                if ($cached !== false) {
                    LBFA_Logger::info('Capabilities retrieved from cache', LBFA_Logger::CATEGORY_API, 'get_capabilities');
                    return $this->create_api_response(true, $cached);
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

                $url = self::get_api_base_url() . '/capabilities';
                $response = wp_remote_get($url, array(
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $jwt_token,
                    ),
                    'timeout' => 30,
                ));

                if (is_wp_error($response)) {
                    LBFA_Logger::error(
                        'Capabilities request error: ' . $response->get_error_message(),
                        LBFA_Logger::CATEGORY_API,
                        'get_capabilities'
                    );
                    return $this->create_error_response(
                        /* translators: Error message prefix for capabilities request errors, followed by the actual error */
                        __('Errore nella richiesta capabilities: ', 'legalblink-for-aruba') . $response->get_error_message(),
                        /* translators: English error message for capabilities request failure */
                        __('Capabilities request failed', 'legalblink-for-aruba')
                    );
                }

                $code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                $payload = json_decode($body, true);

                if ($code !== 200 || !is_array($payload)) {
                    LBFA_Logger::warning('Capabilities request failed with code: ' . $code, LBFA_Logger::CATEGORY_API, 'get_capabilities');
                    return $this->create_error_response(
                        /* translators: %d is the HTTP response code for capabilities request failure */
                        sprintf(__('Richiesta capabilities fallita: %d', 'legalblink-for-aruba'), $code),
                        /* translators: English error message for capabilities request failure */
                        __('Capabilities request failed', 'legalblink-for-aruba')
                    );
                }

                $normalized = $this->normalize_capabilities($payload);

                $expiration = max(60, self::get_api_cache_time());
                LBFA_Transient_Helper::set(self::CACHE_KEY, $normalized, $expiration);

                LBFA_Logger::info('Capabilities retrieved successfully (mode=' . ($normalized['mode'] ?? 'unknown') . ')', LBFA_Logger::CATEGORY_API, 'get_capabilities');

                return $this->create_api_response(true, $normalized);
            } catch (Exception $e) {
                LBFA_Logger::error('Capabilities exception: ' . $e->getMessage(), LBFA_Logger::CATEGORY_API, 'get_capabilities');
                return $this->create_error_response(
                    /* translators: Error message for unexpected capabilities retrieval errors */
                    __('Errore imprevisto nel recupero capabilities', 'legalblink-for-aruba'),
                    /* translators: English error message for capabilities exception */
                    __('Capabilities exception', 'legalblink-for-aruba')
                );
            }
        }

        /**
         * Normalize the backend capabilities payload to the shape the admin UI
         * expects. Defensive against missing optional fields so a partial
         * backend response cannot crash the plugin UI.
         *
         * Reference: back-end service `buildWordPressCapabilities` shape.
         */
        public function normalize_capabilities(array $payload): array
        {
            $mode = isset($payload['mode']) ? (string) $payload['mode'] : 'none';

            $features = isset($payload['features']) && is_array($payload['features']) ? $payload['features'] : array();
            $documents = isset($payload['documents']) && is_array($payload['documents']) ? $payload['documents'] : array();
            $resources = isset($payload['resources']) && is_array($payload['resources']) ? $payload['resources'] : array();
            $warnings = isset($payload['warnings']) && is_array($payload['warnings']) ? $payload['warnings'] : array();

            return array(
                'mode' => $mode,
                'features' => array(
                    'gdpr' => (bool) ($features['gdpr'] ?? false),
                    'accessibility' => (bool) ($features['accessibility'] ?? false),
                    'cookieBannerV2' => (bool) ($features['cookieBannerV2'] ?? false),
                    'accessibilityDeclaration' => (bool) ($features['accessibilityDeclaration'] ?? false),
                    'accessibilityWidget' => (bool) ($features['accessibilityWidget'] ?? false),
                ),
                'documents' => array(
                    'privacyPolicy' => (bool) ($documents['privacyPolicy'] ?? false),
                    'cookiePolicy' => (bool) ($documents['cookiePolicy'] ?? false),
                    'termsOfService' => (bool) ($documents['termsOfService'] ?? false),
                    'accessibilityDeclaration' => (bool) ($documents['accessibilityDeclaration'] ?? false),
                ),
                'resources' => array(
                    'accessibilityWidgetConfigured' => (bool) ($resources['accessibilityWidgetConfigured'] ?? false),
                ),
                'warnings' => array(
                    'accessibilityWidget' => self::normalize_widget_warning($warnings['accessibilityWidget'] ?? null),
                ),
            );
        }

        /**
         * The backend sends the widget warning either as a string or as a list
         * of strings (`[]` when there is nothing to report). Keep the single
         * string contract of the admin UI: first non-empty string, or null.
         * Casting the list to string raised "Array to string conversion" on PHP 8.
         *
         * @param mixed $warning
         * @return string|null
         */
        private static function normalize_widget_warning($warning)
        {
            if (is_array($warning)) {
                foreach ($warning as $item) {
                    if (is_string($item) && $item !== '') {
                        return $item;
                    }
                }
                return null;
            }

            if ($warning === null || $warning === '') {
                return null;
            }

            return (string) $warning;
        }
    }
}
