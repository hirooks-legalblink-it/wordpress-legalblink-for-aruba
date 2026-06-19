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
                    'accessibilityWidget' => isset($warnings['accessibilityWidget']) && $warnings['accessibilityWidget'] !== ''
                        ? (string) $warnings['accessibilityWidget']
                        : null,
                ),
            );
        }
    }
}
