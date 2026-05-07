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
         * Staging:    https://staging.app.legalblink.it/api/integrations/wordpress
         * Local dev:  http://host.docker.internal:3001/integrations/wordpress
         *             (or http://localhost:3001/integrations/wordpress when the
         *             plugin is not running inside a Docker container)
         *             Pair with `npm run seed:wordpress-integration` and the
         *             local-safe Docker stack on the platform repo.
         */
        'base_url' => 'https://app.legalblink.it/api/integrations/wordpress',

        /**
         * LegalBlink API Bearer Token
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

