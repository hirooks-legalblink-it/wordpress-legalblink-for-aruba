<?php

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

if ( ! class_exists( 'LBFA_Base_Shortcode' ) ) {
    abstract class LBFA_Base_Shortcode
    {
        protected $tag = '';
        protected $default_attrs = array(
            'lang' => null,
            'height' => '600px',
            'width' => '100%',
            'style' => '',
            'html' => 'false',
        );
        protected $policy_type = '';

        /**
         * Constructor
         */
        public function __construct()
        {
            add_shortcode($this->tag, array($this, 'handle'));
        }

        /**
         * Called to handle the shortcode
         * @param array $attrs shortcode attributes
         * @param string|null $content shortcode content
         * @return string shortcode output
         */
        public function handle($attrs, $content = null)
        {
            $attrs = shortcode_atts($this->default_attrs, $attrs);
            return $this->generate_output($attrs, $content);
        }

        /**
         * Override this function in your concrete class to generate the shortcode output
         * @param array $attrs shortcode attributes
         * @param string|null $content shortcode content
         * @return string shortcode output
         */
        protected abstract function generate_output($attrs, $content);

        /**
         * Common method to get language from attributes or locale
         * @param array $attrs shortcode attributes
         * @return string language code
         */
        protected function get_language($attrs)
        {
            if (!empty($attrs['lang'])) {
                return sanitize_text_field($attrs['lang']);
            }

            return LBFA_Language_Detector::detect_language();
        }

        /**
         * Get document URL from cache or options
         * @param string $policy_type
         * @param string $language
         * @return string|null
         */
        protected function get_document_url($policy_type, $language)
        {
            return LBFA_Option_Helper::getLanguageOption("documents_{$policy_type}_html_url", $language);
        }

        /**
         * Generate error notice HTML
         * @param string $title
         * @param string $message
         * @param string $policy_type
         * @return string
         */
        protected function generate_error_notice($title, $message, $policy_type)
        {
            $css_class = "lbp-{$policy_type}-policy-notice";
            return sprintf(
                '<div class="%s" style="padding: 20px; border: 1px solid #ddd; background: #f9f9f9; text-align: center; border-radius: 4px; margin: 10px 0;">
                    <p style="margin: 0 0 10px 0;"><strong>%s</strong></p>
                    <p style="margin: 0;">%s</p>
                </div>',
                esc_attr($css_class),
                esc_html($title),
                esc_html($message)
            );
        }

        /**
         * Generate iframe HTML
         * @param string $url
         * @param array $attrs
         * @param string $title
         * @param string $fallback_text
         * @param string $policy_type
         * @return string
         */
        protected function generate_iframe($url, $attrs, $title, $fallback_text, $policy_type)
        {
            $height = esc_attr($attrs['height']);
            $width = esc_attr($attrs['width']);
            $style = esc_attr($attrs['style']);

            $iframe_style = "border:none; height:{$height}; width:{$width};";
            if ($style) {
                $iframe_style .= $style;
            }

            $css_class = "lbp-{$policy_type}-policy-container";

            return sprintf(
                '<div class="%s">
                    <iframe src="%s" style="%s" title="%s" frameborder="0" loading="lazy">
                        <p>%s <a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>
                    </iframe>
                </div>',
                esc_attr($css_class),
                esc_url($url),
                $iframe_style,
                esc_attr($title),
                esc_html($fallback_text),
                esc_url($url),
                /* translators: Link text to view the document */
                esc_html(__('Visualizza documento', 'legalblink-for-aruba'))
            );
        }

        /**
         * Generate HTML content directly
         * @param string $html_content
         * @param string $policy_type
         * @return string
         */
        protected function generate_html_content($html_content, $policy_type)
        {
            $css_class = "lbp-{$policy_type}-policy-html-container";

            return sprintf(
                '<div class="%s">%s</div>',
                esc_attr($css_class),
                /*wp_kses_post(*/$html_content/*)*/
            );
        }

        /**
         * Get HTML content from API
         * @param string $policy_type
         * @param string $language
         * @return string|null
         */
        protected function get_html_content($policy_type, $language)
        {
            // Try cache first
            $cached_content = LBFA_Transient_Helper::getLanguage("documents_{$policy_type}_html_content", $language);
            if ($cached_content !== false) {
                return $cached_content;
            }

            $url = $this->get_document_url($policy_type, $language);
            if (empty($url)) {
                return null;
            }

            // Fetch content
            $response = wp_remote_get($url, array('timeout' => 30));
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                return null;
            }

            $content = wp_remote_retrieve_body($response);

            // Strip <style> blocks entirely (tag + content) to avoid layout conflicts with the WordPress theme
            $content = preg_replace('/<style\b[^>]*>.*?<\/style>/si', '', $content);
            $sanitized = wp_kses($content, wp_kses_allowed_html('post'));

            if (!empty($sanitized)) {
                // Cache the content
                $cache_duration = LBFA_Option_Helper::getOption('cache_duration', 30);
                $cache_duration_days = $cache_duration * 3600 * 24;

                LBFA_Transient_Helper::setLanguage("documents_{$policy_type}_html_content", $language, $sanitized, $cache_duration_days);
                return $sanitized;
            }

            return null;
        }

        /**
         * Common output generation logic
         * @param array $attrs
         * @param string $content
         * @param string $policy_type
         * @param string $title
         * @param string $not_configured_message
         * @param string $fallback_text
         * @return string
         */
        protected function generate_common_output($attrs, $content, $policy_type, $title, $not_configured_message, $fallback_text)
        {
            $language = $this->get_language($attrs);
            $use_html = filter_var($attrs['html'], FILTER_VALIDATE_BOOLEAN);

            // Get document URL
            $document_url = $this->get_document_url($policy_type, $language);

            if (empty($document_url)) {
                return $this->generate_error_notice(
                    /* translators: %s is the document type (e.g., Privacy Policy, Cookie Policy) */
                    sprintf(__('%s non configurato', 'legalblink-for-aruba'), $title),
                    $not_configured_message,
                    $policy_type
                );
            }

            // If HTML mode is enabled, try to get HTML content
            if ($use_html) {
                $html_content = $this->get_html_content($policy_type, $language);
                if (!empty($html_content)) {
                    return $this->generate_html_content($html_content, $policy_type);
                }
            }

            // Generate iframe
            return $this->generate_iframe(
                $document_url,
                $attrs,
                $title,
                $fallback_text,
                $policy_type
            );
        }
    }
}
