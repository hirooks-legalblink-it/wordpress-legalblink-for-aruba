<?php
/**
 * LegalBlink for Aruba - Configuration
 *
 * Copy this file to config.php and fill in your actual values.
 * The config.php file will not be tracked by git.
 */

if (!defined('ABSPATH')) {
    die;
}

return array(
    /**
     * API Configuration
     */
    'api' => array(
        /**
         * API namespace for REST endpoints
         * Default: 'lbfa/v1'
         */
        'namespace' => 'lbfa/v1',

        /**
         * Base URL for LegalBlink API calls
         * Production: https://app.legalblink.it/api/integrations/wordpress
         * Staging: https://staging.app.legalblink.it/api/integrations/wordpress
         */
        'base_url' => 'https://app.legalblink.it/api/integrations/wordpress',

        /**
         * LegalBlink API Bearer Token
         * IMPORTANT: Keep this secret! Never commit this file with real credentials.
         * Get your token from: https://app.legalblink.it/settings/integrations
         */
        'bearer_token' => 'your-api-token-here',

        /**
         * Rate limiting for API calls (calls per minute per user)
         * Default: 60
         */
        'rate_limit' => 60,

        /**
         * Cache time for API responses (in seconds)
         * Default: 3600 (1 hour)
         */
        'cache_time' => 3600,
    ),
);

