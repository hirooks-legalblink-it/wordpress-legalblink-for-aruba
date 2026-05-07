<?php

if (!defined('ABSPATH')) {
    die;
}

if (!class_exists('LBFA_Accessibility_API_Controller')) {
    /**
     * Accessibility API Controller (S#7701 mixed-mode).
     *
     * Owns all plugin endpoints for the accessibility feature:
     *   - GET  /accessibility/declaration            (read declaration document)
     *   - POST /accessibility/declaration/update-page (apply shortcode to a WP page)
     *
     * Kept separate from `LBFA_Document_API_Controller` on purpose — the GDPR
     * legacy surface must not be widened to host accessibility logic (S#7701
     * "DocumentService non si gonfia" rule).
     */
    class LBFA_Accessibility_API_Controller extends LBFA_Base_API_Controller
    {
        const POLICY_TYPE = 'accessibility_declaration';
        const SHORTCODE = '[LBFA_ACCESSIBILITY_DECLARATION]';

        public function register_routes()
        {
            register_rest_route(self::get_api_namespace(), '/accessibility/declaration', array(
                'methods' => 'GET',
                'callback' => array($this, 'get_declaration'),
                'permission_callback' => array($this, 'check_admin_permissions_with_nonce'),
            ));

            register_rest_route(self::get_api_namespace(), '/accessibility/declaration/update-page', array(
                'methods' => 'POST',
                'callback' => array($this, 'update_declaration_page'),
                'permission_callback' => array($this, 'check_admin_permissions_with_nonce'),
                'args' => array(
                    'page_id' => array(
                        'required' => false,
                        'type' => 'integer',
                        'default' => null,
                    ),
                    'use_html_snippet' => array(
                        'required' => false,
                        'type' => 'boolean',
                        'default' => false,
                    ),
                    'language' => array(
                        'required' => true,
                        'type' => 'string',
                        'validate_callback' => static function ($param) {
                            return is_string($param) && preg_match('/^[a-z]{2}$/', $param);
                        },
                    ),
                ),
            ));
        }

        /**
         * Fetch the canonical accessibility declaration from the backend, with
         * fallback to the legacy template handled server-side. Caches the
         * normalized payload, persists per-language URLs to options so the
         * shortcode can render without an extra round trip.
         */
        public function get_declaration()
        {
            try {
                $cache_key = 'accessibility_declaration';
                $cached = LBFA_Transient_Helper::get($cache_key);
                if ($cached !== false) {
                    LBFA_Logger::info('Accessibility declaration retrieved from cache', LBFA_Logger::CATEGORY_API, 'get_declaration');
                    return $this->create_api_response(true, $this->hydrate_with_page_options($cached));
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

                $url = self::get_api_base_url() . '/accessibility/declaration';
                $response = wp_remote_get($url, array(
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $jwt_token,
                    ),
                    'timeout' => 30,
                ));

                if (is_wp_error($response)) {
                    LBFA_Logger::error(
                        'Accessibility declaration request error: ' . $response->get_error_message(),
                        LBFA_Logger::CATEGORY_API,
                        'get_declaration'
                    );
                    return $this->create_error_response(
                        /* translators: Error message prefix for accessibility declaration request errors */
                        __('Errore nella richiesta dichiarazione accessibilità: ', 'legalblink-for-aruba') . $response->get_error_message(),
                        /* translators: English error message for accessibility declaration request failure */
                        __('Accessibility declaration request failed', 'legalblink-for-aruba')
                    );
                }

                $code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                $payload = json_decode($body, true);

                if ($code !== 200 || !is_array($payload)) {
                    LBFA_Logger::warning('Accessibility declaration request failed with code: ' . $code, LBFA_Logger::CATEGORY_API, 'get_declaration');
                    return $this->create_error_response(
                        /* translators: %d is the HTTP response code for accessibility declaration request failure */
                        sprintf(__('Richiesta dichiarazione accessibilità fallita: %d', 'legalblink-for-aruba'), $code),
                        /* translators: English error message for accessibility declaration request failure */
                        __('Accessibility declaration request failed', 'legalblink-for-aruba')
                    );
                }

                $normalized = $this->normalize_declaration($payload);
                $expiration = max(60, self::get_api_cache_time());
                LBFA_Transient_Helper::set($cache_key, $normalized, $expiration);

                $this->persist_language_urls($normalized);

                LBFA_Logger::info('Accessibility declaration retrieved successfully (available=' . (!empty($normalized['available']) ? 'true' : 'false') . ')', LBFA_Logger::CATEGORY_API, 'get_declaration');

                return $this->create_api_response(true, $this->hydrate_with_page_options($normalized));
            } catch (Exception $e) {
                LBFA_Logger::error('Accessibility declaration exception: ' . $e->getMessage(), LBFA_Logger::CATEGORY_API, 'get_declaration');
                return $this->create_error_response(
                    /* translators: Error message for unexpected accessibility declaration retrieval errors */
                    __('Errore imprevisto nel recupero dichiarazione accessibilità', 'legalblink-for-aruba'),
                    /* translators: English error message for accessibility declaration exception */
                    __('Accessibility declaration exception', 'legalblink-for-aruba')
                );
            }
        }

        /**
         * Apply the [LBFA_ACCESSIBILITY_DECLARATION] shortcode to a chosen WP
         * page (or clear the binding when page_id is null/0). Mirrors the
         * Document controller's update_page_content but never touches GDPR
         * options.
         */
        public function update_declaration_page($request)
        {
            try {
                $page_id = $request->get_param('page_id');
                $use_html_snippet = (bool) $request->get_param('use_html_snippet');
                $language = $request->get_param('language');

                if (!$page_id) {
                    LBFA_Option_Helper::setLanguageOption('page_' . self::POLICY_TYPE . '_id', 0, $language);
                    LBFA_Option_Helper::setLanguageOption('page_' . self::POLICY_TYPE . '_use_html_snippet', false, $language);
                    return $this->create_api_response(true, array());
                }

                if (!get_post((int) $page_id)) {
                    return $this->create_error_response(
                        /* translators: Error message when a WordPress page is not found */
                        __('Pagina non trovata.', 'legalblink-for-aruba'),
                        /* translators: English error message for page not found */
                        __('Page not found', 'legalblink-for-aruba')
                    );
                }

                $shortcode = self::SHORTCODE;
                $shortcode = str_replace(']', ' lang="' . $language . '"]', $shortcode);
                if ($use_html_snippet) {
                    $shortcode = str_replace(']', ' html="true"]', $shortcode);
                }

                $update_result = wp_update_post(array(
                    'ID' => $page_id,
                    'post_content' => $shortcode,
                ));

                LBFA_Option_Helper::setLanguageOption('page_' . self::POLICY_TYPE . '_id', $page_id, $language);
                LBFA_Option_Helper::setLanguageOption('page_' . self::POLICY_TYPE . '_use_html_snippet', $use_html_snippet, $language);

                if (is_wp_error($update_result)) {
                    LBFA_Logger::error('Accessibility declaration page update error: ' . $update_result->get_error_message(), LBFA_Logger::CATEGORY_API, 'update_declaration_page');
                    return $this->create_error_response(
                        /* translators: Error message prefix for page update errors, followed by the actual error */
                        __('Errore nell\'aggiornamento della pagina: ', 'legalblink-for-aruba') . $update_result->get_error_message(),
                        /* translators: English error message for page update failure */
                        __('Page update failed', 'legalblink-for-aruba')
                    );
                }

                LBFA_Logger::info("Page {$page_id} updated with accessibility declaration shortcode", LBFA_Logger::CATEGORY_API, 'update_declaration_page');
                return $this->create_api_response(true, array());
            } catch (Exception $e) {
                LBFA_Logger::error('Accessibility declaration page update exception: ' . $e->getMessage(), LBFA_Logger::CATEGORY_API, 'update_declaration_page');
                return $this->create_error_response(
                    /* translators: Error message for unexpected page update errors */
                    __('Errore imprevisto nell\'aggiornamento pagina', 'legalblink-for-aruba'),
                    /* translators: English error message for page update exception */
                    __('Page update exception', 'legalblink-for-aruba')
                );
            }
        }

        /**
         * Normalize the backend declaration payload to the shape consumed by
         * the admin UI (mirrors the GDPR document shape so PolicySettingsCard
         * and DocumentEmbed can render it with minimal branching).
         *
         * Input shape (back-end service):
         *   { available, source, document: { id, slug, createdAt, updatedAt,
         *     languages: { it: { url: { html, pdf } }, en: ... } } }
         */
        public function normalize_declaration(array $payload): array
        {
            $available = (bool) ($payload['available'] ?? false);
            $source = isset($payload['source']) ? (string) $payload['source'] : null;
            $document = isset($payload['document']) && is_array($payload['document']) ? $payload['document'] : null;

            if (!$available || !$document) {
                return array(
                    'available' => false,
                    'source' => $source,
                    'document' => null,
                );
            }

            $languages_raw = isset($document['languages']) && is_array($document['languages']) ? $document['languages'] : array();
            $languages = array();
            foreach ($languages_raw as $iso => $lang_data) {
                if (!is_string($iso) || !preg_match('/^[a-z]{2}$/', $iso)) {
                    continue;
                }
                $url = isset($lang_data['url']) && is_array($lang_data['url']) ? $lang_data['url'] : array();
                $languages[$iso] = array(
                    'url' => array(
                        'html' => isset($url['html']) ? (string) $url['html'] : '',
                        'pdf' => isset($url['pdf']) ? (string) $url['pdf'] : '',
                    ),
                );
            }

            return array(
                'available' => true,
                'source' => $source,
                'document' => array(
                    'id' => isset($document['id']) ? (string) $document['id'] : '',
                    'slug' => isset($document['slug']) ? (string) $document['slug'] : '',
                    'createdAt' => isset($document['createdAt']) ? (string) $document['createdAt'] : '',
                    'updatedAt' => isset($document['updatedAt']) ? (string) $document['updatedAt'] : '',
                    'languages' => $languages,
                ),
            );
        }

        /**
         * Persist per-language HTML URLs into options so LBFA_Base_Shortcode
         * can render the declaration without an extra HTTP round trip.
         */
        private function persist_language_urls(array $normalized): void
        {
            if (empty($normalized['available']) || !isset($normalized['document']['languages'])) {
                return;
            }

            foreach ($normalized['document']['languages'] as $iso => $lang) {
                $html_url = $lang['url']['html'] ?? '';
                if ($html_url === '') {
                    continue;
                }
                LBFA_Option_Helper::setLanguageOption('documents_' . self::POLICY_TYPE . '_html_url', $html_url, $iso);
            }
        }

        /**
         * Inject pageId / useHtmlSnippet from option storage into each language
         * payload, mirroring the shape Document_API_Controller emits for GDPR.
         */
        private function hydrate_with_page_options(array $normalized): array
        {
            if (empty($normalized['available']) || empty($normalized['document']['languages'])) {
                return $normalized;
            }

            foreach ($normalized['document']['languages'] as $iso => &$lang) {
                $page_id = LBFA_Option_Helper::getLanguageOption('page_' . self::POLICY_TYPE . '_id', $iso, '0');
                $use_html_snippet = LBFA_Option_Helper::getLanguageOption('page_' . self::POLICY_TYPE . '_use_html_snippet', $iso, false);
                $lang['pageId'] = ($page_id === '0' || $page_id === 0) ? null : (string) $page_id;
                $lang['useHtmlSnippet'] = (bool) $use_html_snippet;
            }
            unset($lang);

            return $normalized;
        }
    }
}
