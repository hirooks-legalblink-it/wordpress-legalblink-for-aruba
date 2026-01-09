<?php

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

if ( ! class_exists( 'LBFA_CGV_Policy_Shortcode' ) ) {
    class LBFA_CGV_Policy_Shortcode extends LBFA_Base_Shortcode
    {
        protected $tag = 'LBFA_CGV_POLICY';
        protected $policy_type = 'terms_of_service';

        /**
         * Generate the shortcode output
         * @param array $attrs shortcode attributes
         * @param string|null $content shortcode content
         * @return string shortcode output
         */
        protected function generate_output($attrs, $content)
        {
            return $this->generate_common_output(
                $attrs,
                $content,
                $this->policy_type,
                /* translators: Title for the Terms of Service document (CGV - Condizioni Generali di Vendita) */
                __('Condizioni Generali di Vendita', 'legalblink-for-aruba'),
                /* translators: Message displayed when Terms of Service URL is not configured in plugin settings */
                __('Per visualizzare le CGV, configura l\'URL nelle impostazioni del plugin.', 'legalblink-for-aruba'),
                /* translators: Fallback message for browsers that don't support iframes when displaying Terms of Service */
                __('Il tuo browser non supporta gli iframe. Puoi visualizzare le CGV qui:', 'legalblink-for-aruba')
            );
        }
    }
}
