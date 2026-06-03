<?php

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

// Trust Railway / PaaS reverse proxy automatically
$trustedProxies = $_SERVER['TRUSTED_PROXIES'] ?? $_ENV['TRUSTED_PROXIES'] ?? null;
if ($trustedProxies) {
    Request::setTrustedProxies(
        explode(',', $trustedProxies),
        Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT
    );
} elseif (isset($_ENV['RAILWAY_ENVIRONMENT']) || isset($_SERVER['RAILWAY_ENVIRONMENT'])) {
    Request::setTrustedProxies(['REMOTE_ADDR'],
        Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT
    );
}

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};