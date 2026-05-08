<?php
/**
 * Minimal stand-ins for WordPress core classes used by plugin controllers
 * (WP_REST_Response, WP_Error, WP_REST_Request).
 *
 * Brain\Monkey doesn't ship these, so we shim the surface that the plugin
 * code actually invokes (status + data accessor + error message + request
 * params). Loaded by both tests/bootstrap.php and tests/bootstrap-real-helpers.php.
 */

declare(strict_types=1);

if (!class_exists('WP_REST_Response', false)) {
    class WP_REST_Response
    {
        public $data;
        public int $status;

        public function __construct($data = null, int $status = 200)
        {
            $this->data = $data;
            $this->status = $status;
        }

        public function get_data()
        {
            return $this->data;
        }

        public function get_status(): int
        {
            return $this->status;
        }
    }
}

if (!class_exists('WP_Error', false)) {
    class WP_Error
    {
        public string $code;
        public string $message;
        public array $data;

        public function __construct(string $code = '', string $message = '', array $data = [])
        {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }

        public function get_error_message(): string
        {
            return $this->message;
        }

        public function get_error_code(): string
        {
            return $this->code;
        }

        public function get_error_data()
        {
            return $this->data;
        }
    }
}

if (!class_exists('WP_REST_Request', false)) {
    class WP_REST_Request
    {
        private array $params;
        private string $method;
        private array $headers;

        public function __construct(array $params = [], string $method = 'POST', array $headers = [])
        {
            $this->params = $params;
            $this->method = $method;
            $this->headers = $headers;
        }

        public function get_param(string $key)
        {
            return $this->params[$key] ?? null;
        }

        public function get_params(): array
        {
            return $this->params;
        }

        public function get_method(): string
        {
            return $this->method;
        }

        public function get_header(string $key): string
        {
            return $this->headers[$key] ?? '';
        }
    }
}
