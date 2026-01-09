<?php

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

if ( ! class_exists( 'LBFA_Shortcode_Manager' ) ) {
    final class LBFA_Shortcode_Manager
    {
        private static $instance;

        /**
         * Get singleton instance
         */
        public static function get_instance()
        {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Constructor
         */
        private function __construct()
        {
            $this->init_shortcodes();
        }

        /**
         * Initialize all shortcodes
         */
        private function init_shortcodes()
        {
            // Initialize all shortcode classes
            $shortcode_classes = array(
                'LBFA_Cookie_Policy_Shortcode' => 'class-alb-cookie-policy-shortcode.php',
                'LBFA_Privacy_Policy_Shortcode' => 'class-alb-privacy-policy-shortcode.php',
                'LBFA_CGV_Policy_Shortcode' => 'class-alb-cgv-policy-shortcode.php',
            );

            foreach ($shortcode_classes as $class_name => $file_name) {
                if (!class_exists($class_name)) {
                    require_once __DIR__ . '/' . $file_name;
                }

                if (class_exists($class_name)) {
                    new $class_name();
                }
            }
        }
    }
}
