<?php

if (!defined('ABSPATH')) {
    die;
}

if (!class_exists('LBFA_Document_API_Controller')) {
    /**
     * Document API Controller
     * Handles document-related API requests to external service
     */
    class LBFA_Document_API_Controller extends LBFA_Base_API_Controller
    {
        /**
         * Register document routes
         */
        public function register_routes()
        {
            // Get all documents
            register_rest_route(self::get_api_namespace(), '/documents', array(
                'methods' => 'GET',
                'callback' => array($this, 'get_documents'),
                'permission_callback' => array($this, 'check_admin_permissions_with_nonce')
            ));

            // Get WordPress pages
            register_rest_route(self::get_api_namespace(), '/pages', array(
                'methods' => 'GET',
                'callback' => array($this, 'get_wordpress_pages'),
                'permission_callback' => array($this, 'check_admin_permissions_with_nonce')
            ));

            // Update page content with policy
            register_rest_route(self::get_api_namespace(), '/documents/update-page', array(
                'methods' => 'POST',
                'callback' => array($this, 'update_page_content'),
                'permission_callback' => array($this, 'check_admin_permissions_with_nonce'),
                'args' => array(
                    'policy_type' => array(
                        'required' => true,
                        'type' => 'string',
                        'enum' => array('privacy_policy', 'cookie_policy', 'terms_of_service'),
                    ),
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
                        'validate_callback' => function ($param, $request, $key) {
                            return preg_match('/^[a-z]{2}$/', $param);
                        }
                    ),
                )
            ));
        }

        /**
         * Get all documents from external service
         */
        public function get_documents()
        {
            try {
                $cached_documents = LBFA_Transient_Helper::getCachedApiResponse('documents');
                if ($cached_documents !== false) {
                    LBFA_Logger::info('Documents retrieved from cache', LBFA_Logger::CATEGORY_API, 'get_documents');
                    foreach ($cached_documents as $policy_type => &$document) {
                        foreach ($document['languages'] as $iso_code => &$language) {
                            $page_id = LBFA_Option_Helper::getLanguageOption("page_{$policy_type}_id", $iso_code, '0');
                            $use_html_snippet = LBFA_Option_Helper::getLanguageOption("page_{$policy_type}_use_html_snippet", $iso_code, false);
                            $language['pageId'] = $page_id === '0' ? null : $page_id;
                            $language['useHtmlSnippet'] = (bool)$use_html_snippet;
                        }
                        unset($language);
                    }
                    unset($document);
                    $documents_response = [
                        'count' => count($cached_documents),
                        'data' => $cached_documents
                    ];
                    return $this->create_api_response(true, $documents_response);
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

                $url = self::get_api_base_url() . '/documents';
                $response = wp_remote_get($url, array(
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $jwt_token,
                    ),
                    'timeout' => 30
                ));

                if (is_wp_error($response)) {
                    LBFA_Logger::error('Documents request error: ' . $response->get_error_message(), LBFA_Logger::CATEGORY_API, 'get_documents');
                    return $this->create_error_response(
                        /* translators: Error message prefix for documents request errors, followed by the actual error */
                        __('Errore nella richiesta documenti: ', 'legalblink-for-aruba') . $response->get_error_message(),
                        /* translators: English error message for documents request failure */
                        __('Documents request failed', 'legalblink-for-aruba')
                    );
                }

                $code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                $documents_data = json_decode($body, true);

                if (!isset($documents_data['data']) || $code !== 200) {
                    LBFA_Logger::warning('Documents request failed with code: ' . $code, LBFA_Logger::CATEGORY_API, 'get_documents');
                    return $this->create_error_response(
                        /* translators: %d is the HTTP response code for documents request failure */
                        sprintf(__('Richiesta documenti fallita: %d', 'legalblink-for-aruba'), $code),
                        /* translators: English error message for documents request failure */
                        __('Documents request failed', 'legalblink-for-aruba')
                    );
                }

                $parsed_documents = $this->parse_documents($documents_data['data']);
                LBFA_Transient_Helper::cacheApiResponse('documents', $parsed_documents);

                // We store urls in options for quick access
                foreach ($parsed_documents as $policy_type => &$document) {
                    foreach ($document['languages'] as $iso_code => &$language) {
                        LBFA_Option_Helper::setLanguageOption("documents_{$policy_type}_html_url", $language['url']['html'], $iso_code);
                        $page_id = LBFA_Option_Helper::getLanguageOption("page_{$policy_type}_id", $iso_code, '0');
                        $use_html_snippet = LBFA_Option_Helper::getLanguageOption("page_{$policy_type}_use_html_snippet", $iso_code, false);
                        $language['pageId'] = $page_id === '0' ? null : $page_id;
                        $language['useHtmlSnippet'] = (bool)$use_html_snippet;
                    }
                    unset($language);
                }
                unset($document);

                $documents_response = [
                    'count' => count($parsed_documents),
                    'data' => $parsed_documents
                ];

                LBFA_Logger::info('Documents retrieved successfully: ' . wp_json_encode($documents_response), LBFA_Logger::CATEGORY_API, 'get_documents');
                return $this->create_api_response(true, $documents_response);
            } catch (Exception $e) {
                LBFA_Logger::error('Documents retrieval exception: ' . $e->getMessage(), LBFA_Logger::CATEGORY_API, 'get_documents');
                return $this->create_error_response(
                    /* translators: Error message for unexpected documents retrieval errors */
                    __('Errore imprevisto nel recupero documenti', 'legalblink-for-aruba'),
                    /* translators: English error message for documents exception */
                    __('Documents exception', 'legalblink-for-aruba')
                );
            }
        }

        /**
         * Get WordPress pages
         */
        public function get_wordpress_pages()
        {
            try {
                $cached_pages = LBFA_Transient_Helper::get('wordpress_pages');
                if ($cached_pages !== false) {
                    LBFA_Logger::info('WordPress pages retrieved from cache', LBFA_Logger::CATEGORY_API, 'get_wordpress_pages');
                    return $this->create_api_response(true, array('pages' => $cached_pages));
                }

                // If not cached, fetch from database
                $pages = get_pages(array(
                    'post_status' => 'publish',
                    'number' => 100, // Limit to 100 pages
                    'sort_column' => 'post_title',
                    'sort_order' => 'ASC'
                ));

                $pages_data = array();
                foreach ($pages as $page) {
                    $pages_data[] = array(
                        'id' => $page->ID,
                        'title' => $page->post_title,
                        'slug' => $page->post_name,
                        'url' => get_permalink($page->ID),
                        'modified' => $page->post_modified
                    );
                }

                LBFA_Transient_Helper::set('wordpress_pages', $pages_data, 1800);

                LBFA_Logger::info('WordPress pages retrieved: ' . count($pages_data), LBFA_Logger::CATEGORY_API, 'get_wordpress_pages');
                return $this->create_api_response(true, array('pages' => $pages_data));

            } catch (Exception $e) {
                LBFA_Logger::error('WordPress pages retrieval exception: ' . $e->getMessage(), LBFA_Logger::CATEGORY_API, 'get_wordpress_pages');
                return $this->create_error_response(
                    /* translators: Error message for unexpected WordPress pages retrieval errors */
                    __('Errore nel recupero delle pagine WordPress', 'legalblink-for-aruba'),
                    /* translators: English error message for WordPress pages exception */
                    __('WordPress pages exception', 'legalblink-for-aruba')
                );
            }
        }


        public function parse_documents($documents)
        {
            $allowed_slugs = [
                'privacy_policy' => 'privacy-policy',
                'cookie_policy' => 'cookie-policy',
                'terms_of_service' => 'termini',
            ];

            $parsed_documents = array();

            foreach ($documents as $document) {
                $active = $document['active'] ?? false;
                if (!$active) {
                    continue; // Skip inactive documents
                }

                $slug = $document['template']['slug'] ?? '';
                if (empty($slug)) {
                    continue; // Skip if slug is missing
                }

                $files = $document['files'] ?? [];
                if (empty($files)) {
                    continue; // Skip if no files are available
                }

                $matched_key = null;
                foreach ($allowed_slugs as $key => $value) {
                    if (stripos($slug, $value) !== false) {
                        $matched_key = $key;
                        break;
                    }
                }

                if (is_null($matched_key)) {
                    continue; // Skip if slug is not in allowed list
                }

                if (isset($parsed_documents[$matched_key])) {
                    continue; // Skip if this document type is already added
                }

                $parsed_documents[$matched_key] = array(
                    'id' => $document['id'],
                    'slug' => $slug,
                    'createdAt' => $document['createdAt'] ?? '',
                    'updatedAt' => $document['updatedAt'] ?? '',
                    'languages' => []
                );

                foreach ($files as $file) {
                    $language = $file['language']['code'] ?? 'it';
                    $parsed_documents[$matched_key]['languages'][$language] = array(
                        'url' => array(
                            'html' => $file['html']['url'] ?? '',
                            'pdf' => $file['pdf']['url'] ?? ''
                        )
                    );
                }
            }

            return $parsed_documents;
        }


        /**
         * Update page content with policy
         */
        public function update_page_content($request)
        {
            try {
                $policy_type = $request->get_param('policy_type');
                $page_id = $request->get_param('page_id');
                $use_html_snippet = (bool)$request->get_param('use_html_snippet');
                $language = $request->get_param('language');

                if (!$page_id) {
                    LBFA_Option_Helper::setLanguageOption("page_{$policy_type}_id", 0, $language);
                    LBFA_Option_Helper::setLanguageOption("page_{$policy_type}_use_html_snippet", false, $language);
                    return $this->create_api_response(true, array());
                }

                // Validate page exists
                if (!get_post((int)$page_id)) {
                    return $this->create_error_response(
                        /* translators: Error message when a WordPress page is not found */
                        __('Pagina non trovata.', 'legalblink-for-aruba'),
                        /* translators: English error message for page not found */
                        __('Page not found', 'legalblink-for-aruba')
                    );
                }

                // Get shortcode for policy type
                $shortcode = $this->get_shortcode_for_policy($policy_type);
                if (!$shortcode) {
                    return $this->create_error_response(
                        /* translators: Error message when an invalid policy type is provided */
                        __('Tipo di policy non valido.', 'legalblink-for-aruba'),
                        /* translators: English error message for invalid policy type */
                        __('Invalid policy type', 'legalblink-for-aruba')
                    );
                }

                // Add language parameter to shortcode
                $shortcode = str_replace(']', ' lang="' . $language . '"]', $shortcode);

                // Add html parameter to shortcode if use_html_snippet is active
                if ($use_html_snippet) {
                    $shortcode = str_replace(']', ' html="true"]', $shortcode);
                }

                // Update page content
                $update_result = wp_update_post(array(
                    'ID' => $page_id,
                    'post_content' => $shortcode
                ));

                LBFA_Option_Helper::setLanguageOption("page_{$policy_type}_id", $page_id, $language);
                LBFA_Option_Helper::setLanguageOption("page_{$policy_type}_use_html_snippet", $use_html_snippet, $language);

                if (is_wp_error($update_result)) {
                    LBFA_Logger::error('Page update error: ' . $update_result->get_error_message(), LBFA_Logger::CATEGORY_API, 'update_page_content');
                    return $this->create_error_response(
                        /* translators: Error message prefix for page update errors, followed by the actual error */
                        __('Errore nell\'aggiornamento della pagina: ', 'legalblink-for-aruba') . $update_result->get_error_message(),
                        /* translators: English error message for page update failure */
                        __('Page update failed', 'legalblink-for-aruba')
                    );
                }

                // Log successful update
                LBFA_Logger::info("Page {$page_id} updated with {$policy_type} policy shortcode", LBFA_Logger::CATEGORY_API, 'update_page_content');

                return $this->create_api_response(true, array());

            } catch (Exception $e) {
                LBFA_Logger::error('Page update exception: ' . $e->getMessage(), LBFA_Logger::CATEGORY_API, 'update_page_content');
                return $this->create_error_response(
                    /* translators: Error message for unexpected page update errors */
                    __('Errore imprevisto nell\'aggiornamento pagina', 'legalblink-for-aruba'),
                    /* translators: English error message for page update exception */
                    __('Page update exception', 'legalblink-for-aruba')
                );
            }
        }

        /**
         * Get shortcode for policy type
         */
        private function get_shortcode_for_policy($policy_type)
        {
            switch ($policy_type) {
                case 'privacy_policy':
                    return '[LBFA_PRIVACY_POLICY]';
                case 'cookie_policy':
                    return '[LBFA_COOKIE_POLICY]';
                case 'terms_of_service':
                    return '[LBFA_CGV_POLICY]';
                default:
                    return false;
            }
        }

    }
}
