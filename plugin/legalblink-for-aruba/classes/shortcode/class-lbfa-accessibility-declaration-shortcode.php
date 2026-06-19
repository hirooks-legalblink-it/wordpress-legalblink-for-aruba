<?php

if (!defined('ABSPATH')) {
    die;
}

if (!class_exists('LBFA_Accessibility_Declaration_Shortcode')) {
    /**
     * Renders the accessibility declaration document inside a WordPress page
     * via the [LBFA_ACCESSIBILITY_DECLARATION] shortcode (S#7701 Phase 3).
     *
     * Reuses LBFA_Base_Shortcode entirely — language detection, html-vs-iframe
     * mode and option-driven URL lookup are inherited. The only declaration
     * here is the policy_type/tag wiring so the base shortcode reads from the
     * `documents_accessibility_declaration_html_url_{lang}` options written
     * by LBFA_Accessibility_API_Controller.
     */
    class LBFA_Accessibility_Declaration_Shortcode extends LBFA_Base_Shortcode
    {
        protected $tag = 'LBFA_ACCESSIBILITY_DECLARATION';
        protected $policy_type = 'accessibility_declaration';

        protected function generate_output($attrs, $content)
        {
            return $this->generate_common_output(
                $attrs,
                $content,
                $this->policy_type,
                /* translators: Title for the accessibility declaration document */
                __('Dichiarazione di accessibilità', 'legalblink-for-aruba'),
                /* translators: Message displayed when accessibility declaration URL is not configured in plugin settings */
                __('Per visualizzare la dichiarazione di accessibilità, completa la configurazione nel pannello di amministrazione del plugin.', 'legalblink-for-aruba'),
                /* translators: Fallback message for browsers that don't support iframes */
                __('Il tuo browser non supporta gli iframe. Puoi visualizzare la dichiarazione di accessibilità qui:', 'legalblink-for-aruba')
            );
        }
    }
}
