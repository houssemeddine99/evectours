<?php

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

// C'est ici qu'on définit les Trusted Proxies pour Render
// On vérifie si on est en environnement de prod pour ne pas affecter le local inutilement
if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? 'dev') === 'prod') {
    Request::setTrustedProxies(
        ['0.0.0.0/0'], 
        Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT
    );
}

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};