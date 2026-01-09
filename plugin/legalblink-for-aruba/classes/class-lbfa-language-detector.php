<?php

if (!defined('ABSPATH')) {
    exit;
}

if ( ! class_exists( 'LBFA_Language_Detector' ) ) {
    class LBFA_Language_Detector
    {

        /**
         * Supported languages by the plugin
         */
        const SUPPORTED_LANGUAGES = ['it', 'en', 'de', 'fr', 'es'];

        /**
         * Default fallback language
         */
        const DEFAULT_LANGUAGE = 'it';

        /**
         * Detect current language with support for multilingual plugins
         *
         * @param string|null $manual_lang Manually specified language
         * @return string Language code (2 characters)
         */
        public static function detect_language($manual_lang = null)
        {
            // Priority 1: Manual language parameter
            if (!empty($manual_lang)) {
                $manual_lang = sanitize_text_field($manual_lang);
                if (self::is_supported_language($manual_lang)) {
                    return strtolower($manual_lang);
                }
            }

            // Priority 2: WPML plugin
            $wpml_lang = self::detect_wpml_language();
            if ($wpml_lang) {
                return $wpml_lang;
            }

            // Priority 3: Polylang plugin
            $polylang_lang = self::detect_polylang_language();
            if ($polylang_lang) {
                return $polylang_lang;
            }

            // Priority 4: qTranslate X plugin
            $qtranslate_lang = self::detect_qtranslate_language();
            if ($qtranslate_lang) {
                return $qtranslate_lang;
            }

            // Priority 5: TranslatePress plugin
            $translatepress_lang = self::detect_translatepress_language();
            if ($translatepress_lang) {
                return $translatepress_lang;
            }

            // Priority 6: WordPress get_locale()
            $wp_lang = self::detect_wordpress_language();
            if ($wp_lang) {
                return $wp_lang;
            }

            // Fallback: Default language
            return self::DEFAULT_LANGUAGE;
        }

        /**
         * Detect WPML current language
         *
         * @return string|false Language code or false if not detected
         */
        private static function detect_wpml_language()
        {
            // Check if WPML is active
            if (!function_exists('wpml_get_current_language')) {
                return false;
            }

            $current_lang = wpml_get_current_language();
            if ($current_lang && $current_lang !== 'all') {
                $lang_code = substr($current_lang, 0, 2);
                if (self::is_supported_language($lang_code)) {
                    return strtolower($lang_code);
                }
            }

            // Alternative WPML detection methods
            if (defined('ICL_LANGUAGE_CODE')) {
                $lang_code = substr(ICL_LANGUAGE_CODE, 0, 2);
                if (self::is_supported_language($lang_code)) {
                    return strtolower($lang_code);
                }
            }

            // Check WPML global variable
            global $sitepress;
            if (isset($sitepress) && method_exists($sitepress, 'get_current_language')) {
                $current_lang = $sitepress->get_current_language();
                if ($current_lang && $current_lang !== 'all') {
                    $lang_code = substr($current_lang, 0, 2);
                    if (self::is_supported_language($lang_code)) {
                        return strtolower($lang_code);
                    }
                }
            }

            return false;
        }

        /**
         * Detect Polylang current language
         *
         * @return string|false Language code or false if not detected
         */
        private static function detect_polylang_language()
        {
            // Check if Polylang is active
            if (!function_exists('pll_current_language')) {
                return false;
            }

            $current_lang = pll_current_language();
            if ($current_lang) {
                $lang_code = substr($current_lang, 0, 2);
                if (self::is_supported_language($lang_code)) {
                    return strtolower($lang_code);
                }
            }

            // Alternative Polylang detection
            if (function_exists('pll_the_languages')) {
                global $polylang;
                if (isset($polylang) && isset($polylang->curlang)) {
                    $current_lang = $polylang->curlang->slug;
                    if ($current_lang) {
                        $lang_code = substr($current_lang, 0, 2);
                        if (self::is_supported_language($lang_code)) {
                            return strtolower($lang_code);
                        }
                    }
                }
            }

            return false;
        }

        /**
         * Detect qTranslate X current language
         *
         * @return string|false Language code or false if not detected
         */
        private static function detect_qtranslate_language()
        {
            // Check if qTranslate X is active
            if (!function_exists('qtranxf_getLanguage')) {
                return false;
            }

            $current_lang = qtranxf_getLanguage();
            if ($current_lang) {
                $lang_code = substr($current_lang, 0, 2);
                if (self::is_supported_language($lang_code)) {
                    return strtolower($lang_code);
                }
            }

            // Check global variable
            global $q_config;
            if (isset($q_config['language'])) {
                $lang_code = substr($q_config['language'], 0, 2);
                if (self::is_supported_language($lang_code)) {
                    return strtolower($lang_code);
                }
            }

            return false;
        }

        /**
         * Detect TranslatePress current language
         *
         * @return string|false Language code or false if not detected
         */
        private static function detect_translatepress_language()
        {
            // Check if TranslatePress is active
            if (!class_exists('TRP_Translate_Press')) {
                return false;
            }

            // Get TranslatePress language from URL or settings
            $trp = TRP_Translate_Press::get_trp_instance();
            if (isset($trp) && method_exists($trp, 'get_component')) {
                $url_converter = $trp->get_component('url_converter');
                if ($url_converter && method_exists($url_converter, 'get_lang_from_url_string')) {
                    $current_url = sanitize_url(wp_unslash(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''));
                    $current_lang = $url_converter->get_lang_from_url_string($current_url);
                    if ($current_lang) {
                        $lang_code = substr($current_lang, 0, 2);
                        if (self::is_supported_language($lang_code)) {
                            return strtolower($lang_code);
                        }
                    }
                }
            }

            return false;
        }

        /**
         * Detect language from WordPress get_locale()
         *
         * @return string|false Language code or false if not detected
         */
        private static function detect_wordpress_language()
        {
            if (!function_exists('get_locale')) {
                return false;
            }

            $locale = get_locale();
            if ($locale) {
                $lang_code = substr($locale, 0, 2);
                if (self::is_supported_language($lang_code)) {
                    return strtolower($lang_code);
                }
            }

            return false;
        }

        /**
         * Check if language is supported by the plugin
         *
         * @param string $lang_code Language code to check
         * @return bool True if supported, false otherwise
         */
        private static function is_supported_language($lang_code)
        {
            return in_array(strtolower($lang_code), self::SUPPORTED_LANGUAGES, true);
        }

        /**
         * Get list of supported languages
         *
         * @return array Array of supported language codes
         */
        public static function get_supported_languages()
        {
            return self::SUPPORTED_LANGUAGES;
        }

        /**
         * Get language name from code
         *
         * @param string $lang_code Language code
         * @return string Language name
         */
        public static function get_language_name($lang_code)
        {
            $language_names = [
                'it' => 'Italiano',
                'en' => 'English',
                'de' => 'Deutsch',
                'fr' => 'Français',
                'es' => 'Español'
            ];

            return $language_names[strtolower($lang_code)] ?? strtoupper($lang_code);
        }

        /**
         * Get translated page ID for WPML
         *
         * @param int $page_id Original page ID
         * @param string $lang_code Target language code
         * @return int Translated page ID or original if not found
         */
        public static function get_translated_page_id($page_id, $lang_code = null)
        {
            if (!$page_id) {
                return 0;
            }

            // Use current language if not specified
            if (empty($lang_code)) {
                $lang_code = self::detect_language();
            }

            // WPML integration
            if (function_exists('wpml_object_id_filter')) {
                $translated_id = wpml_object_id_filter($page_id, 'page', true, $lang_code);
                if ($translated_id && $translated_id != $page_id) {
                    return $translated_id;
                }
            }

            // Alternative WPML method
            if (function_exists('icl_object_id')) {
                $translated_id = icl_object_id($page_id, 'page', true, $lang_code);
                if ($translated_id && $translated_id != $page_id) {
                    return $translated_id;
                }
            }

            // Polylang integration
            if (function_exists('pll_get_post')) {
                $translated_id = pll_get_post($page_id, $lang_code);
                if ($translated_id && $translated_id != $page_id) {
                    return $translated_id;
                }
            }

            // Return original if no translation found
            return $page_id;
        }

        /**
         * Get current page language for WPML
         *
         * @param int $page_id Page ID to check
         * @return string Language code
         */
        public static function get_page_language($page_id)
        {
            if (!$page_id) {
                return self::detect_language();
            }

            // WPML integration
            if (function_exists('wpml_get_language_information')) {
                $lang_info = wpml_get_language_information(null, $page_id);
                if (isset($lang_info['language_code'])) {
                    $lang_code = substr($lang_info['language_code'], 0, 2);
                    if (self::is_supported_language($lang_code)) {
                        return strtolower($lang_code);
                    }
                }
            }

            // Alternative WPML method
            global $sitepress;
            if (isset($sitepress) && method_exists($sitepress, 'get_language_for_element')) {
                $element_lang = $sitepress->get_language_for_element($page_id, 'post_page');
                if ($element_lang) {
                    $lang_code = substr($element_lang, 0, 2);
                    if (self::is_supported_language($lang_code)) {
                        return strtolower($lang_code);
                    }
                }
            }

            // Polylang integration
            if (function_exists('pll_get_post_language')) {
                $post_lang = pll_get_post_language($page_id);
                if ($post_lang) {
                    $lang_code = substr($post_lang, 0, 2);
                    if (self::is_supported_language($lang_code)) {
                        return strtolower($lang_code);
                    }
                }
            }

            // Fallback to current detected language
            return self::detect_language();
        }

        /**
         * Check if we should show content for specific language context
         *
         * @param string $content_lang Language of the stored content
         * @param string $current_lang Current page/context language
         * @return bool Whether to show the content
         */
        public static function should_show_content($content_lang, $current_lang = null)
        {
            if (empty($current_lang)) {
                $current_lang = self::detect_language();
            }

            // Exact match
            if ($content_lang === $current_lang) {
                return true;
            }

            // If no content for current language, check fallback rules
            // This can be configured based on WPML fallback settings
            $fallback_chain = self::get_language_fallback_chain($current_lang);

            return in_array($content_lang, $fallback_chain);
        }

        /**
         * Get language fallback chain
         *
         * @param string $lang_code Primary language
         * @return array Fallback language codes in order of preference
         */
        public static function get_language_fallback_chain($lang_code)
        {
            $fallbacks = [$lang_code];

            // Add English as common fallback
            if ($lang_code !== 'en') {
                $fallbacks[] = 'en';
            }

            // Add default language as final fallback
            if ($lang_code !== self::DEFAULT_LANGUAGE && !in_array(self::DEFAULT_LANGUAGE, $fallbacks)) {
                $fallbacks[] = self::DEFAULT_LANGUAGE;
            }

            return $fallbacks;
        }

        /**
         * Debug function to show detection process
         *
         * @param string|null $manual_lang Manual language override
         * @return array Debug information about language detection
         */
        public static function debug_detection($manual_lang = null)
        {
            $debug_info = [
                'manual_lang' => $manual_lang,
                'wpml_detected' => function_exists('wpml_get_current_language'),
                'polylang_detected' => function_exists('pll_current_language'),
                'qtranslate_detected' => function_exists('qtranxf_getLanguage'),
                'translatepress_detected' => class_exists('TRP_Translate_Press'),
                'wpml_current' => function_exists('wpml_get_current_language') ? wpml_get_current_language() : 'N/A',
                'polylang_current' => function_exists('pll_current_language') ? pll_current_language() : 'N/A',
                'wp_locale' => function_exists('get_locale') ? get_locale() : 'N/A',
                'detected_language' => self::detect_language($manual_lang),
                'supported_languages' => self::SUPPORTED_LANGUAGES
            ];

            return $debug_info;
        }
    }
}
