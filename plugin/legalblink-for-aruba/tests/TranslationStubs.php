<?php
/**
 * Stub helpers for LBFA_Language_Detector tests.
 *
 * Defines the third-party translation plugin globals that the detector
 * inspects (WPML, Polylang, qTranslate, TranslatePress). Each stub function
 * reads its return value from $GLOBALS['__lbfa_test_translation'] so tests
 * can drive each scenario by setting a key before invoking the detector.
 *
 * NOTE: function_exists() will always be true once this file is loaded;
 * to simulate "plugin absent", set the corresponding $GLOBALS entry to
 * `null` (or unset it). The detector treats empty/null returns the same
 * as a missing plugin and falls through to the next priority.
 *
 * Loaded only by tests/bootstrap-real-helpers.php (UnitReal testsuite).
 */

declare(strict_types=1);

if (!isset($GLOBALS['__lbfa_test_translation'])) {
    $GLOBALS['__lbfa_test_translation'] = [];
}

/**
 * Reset every translation stub state. Call from tests' set_up().
 */
function lbfa_test_reset_translation_stubs(): void
{
    $GLOBALS['__lbfa_test_translation'] = [];
    $GLOBALS['sitepress'] = null;
    $GLOBALS['polylang'] = null;
    $GLOBALS['q_config'] = null;
}

/* ---------- WPML ---------- */

if (!function_exists('wpml_get_current_language')) {
    function wpml_get_current_language()
    {
        return $GLOBALS['__lbfa_test_translation']['wpml_current_language'] ?? null;
    }
}

if (!function_exists('icl_object_id')) {
    function icl_object_id($element_id, $element_type = 'post', $return_original_if_missing = false, $language_code = null)
    {
        $map = $GLOBALS['__lbfa_test_translation']['icl_object_id_map'] ?? [];
        $key = $element_id . '|' . ($language_code ?? '');
        if (isset($map[$key])) {
            return $map[$key];
        }
        return $return_original_if_missing ? $element_id : null;
    }
}

if (!function_exists('wpml_get_language_information')) {
    function wpml_get_language_information($empty = null, $post_id = null)
    {
        $map = $GLOBALS['__lbfa_test_translation']['wpml_language_info_map'] ?? [];
        return $map[$post_id] ?? null;
    }
}

/* ---------- Polylang ---------- */

if (!function_exists('pll_current_language')) {
    function pll_current_language($value = 'slug')
    {
        return $GLOBALS['__lbfa_test_translation']['pll_current_language'] ?? null;
    }
}

if (!function_exists('pll_get_post')) {
    function pll_get_post($post_id, $lang_code = null)
    {
        $map = $GLOBALS['__lbfa_test_translation']['pll_get_post_map'] ?? [];
        $key = $post_id . '|' . ($lang_code ?? '');
        return $map[$key] ?? false;
    }
}

if (!function_exists('pll_get_post_language')) {
    function pll_get_post_language($post_id, $field = 'slug')
    {
        $map = $GLOBALS['__lbfa_test_translation']['pll_get_post_language_map'] ?? [];
        return $map[$post_id] ?? false;
    }
}

/* ---------- qTranslate-XT / qTranslate-X ---------- */

if (!function_exists('qtranxf_getLanguage')) {
    function qtranxf_getLanguage()
    {
        return $GLOBALS['__lbfa_test_translation']['qtranxf_language'] ?? '';
    }
}

/* ---------- TranslatePress ---------- */

if (!class_exists('TRP_Translate_Press')) {
    /**
     * Stand-in for the real TranslatePress class. The detector calls
     * TRP_Translate_Press::get_trp_instance() then asks the instance for the
     * `url_converter` component, which exposes get_lang_from_url_string().
     * Each return value is driven by $GLOBALS['__lbfa_test_translation'].
     */
    class TRP_Translate_Press
    {
        public static function get_trp_instance(): self
        {
            return new self();
        }

        public function get_component(string $name)
        {
            if ($name === 'url_converter') {
                return new class {
                    public function get_lang_from_url_string(string $url): ?string
                    {
                        return $GLOBALS['__lbfa_test_translation']['trp_url_lang'] ?? null;
                    }
                };
            }
            return null;
        }
    }
}
