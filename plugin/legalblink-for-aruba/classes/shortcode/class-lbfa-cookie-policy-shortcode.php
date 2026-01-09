<?php

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

if ( ! class_exists( 'LBFA_Cookie_Policy_Shortcode' ) ) {
    class LBFA_Cookie_Policy_Shortcode extends LBFA_Base_Shortcode
    {
        protected $tag = 'LBFA_COOKIE_POLICY';
        protected $policy_type = 'cookie_policy';

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
                /* translators: Title for the Cookie Policy document */
                __('Cookie Policy', 'legalblink-for-aruba'),
                /* translators: Message displayed when Cookie Policy URL is not configured in plugin settings */
                __('Per visualizzare la Cookie Policy, configura l\'URL nelle impostazioni del plugin.', 'legalblink-for-aruba'),
                /* translators: Fallback message for browsers that don't support iframes */
                __('Il tuo browser non supporta gli iframe. Puoi visualizzare la cookie policy qui:', 'legalblink-for-aruba')
            );
        }
    }
}
