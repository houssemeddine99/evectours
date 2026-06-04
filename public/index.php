<?php

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

// Force HTTPS when behind a reverse proxy (Railway/PaaS)
$proto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $_SERVER['HTTP_X_FORWARDED_PROTOCOL'] ?? null;
$isHttps = ($proto === 'https')
    || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? null) == 443);

if ($proto !== null && !$isHttps && isset($_SERVER['HTTP_HOST'])) {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
    exit;
}

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