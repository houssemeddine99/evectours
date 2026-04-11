#!/usr/bin/env php
<?php

use App\Kernel;

require __DIR__ . '/../vendor/autoload.php';

echo "🔍 Checking Twig templates for invalid route names...\n\n";

$kernel = new Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

// Get router service (public alias)
$router = $container->get('router');

$templateDir = __DIR__ . '/../templates';
if (!is_dir($templateDir)) {
    echo "❌ Templates directory not found: $templateDir\n";
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($templateDir, RecursiveDirectoryIterator::SKIP_DOTS)
);

$errors = [];
foreach ($iterator as $file) {
    if ($file->getExtension() !== 'twig') {
        continue;
    }
    
    $content = file_get_contents($file->getPathname());
    
    // Check path() calls
    preg_match_all('/path\([\'\"]([^\'\"]+)[\'\"]/', $content, $pathMatches);
    foreach ($pathMatches[1] as $routeName) {
        if (!$router->getRouteCollection()->get($routeName)) {
            $errors[] = sprintf(
                "❌ Route '%s' used in path() in %s does not exist",
                $routeName,
                $file->getFilename()
            );
        }
    }
    
    // Check url() calls
    preg_match_all('/url\([\'\"]([^\'\"]+)[\'\"]/', $content, $urlMatches);
    foreach ($urlMatches[1] as $routeName) {
        if (!$router->getRouteCollection()->get($routeName)) {
            $errors[] = sprintf(
                "❌ Route '%s' used in url() in %s does not exist",
                $routeName,
                $file->getFilename()
            );
        }
    }
}

if (empty($errors)) {
    echo "✅ All route names in Twig templates are valid.\n";
    exit(0);
} else {
    echo implode("\n", $errors) . "\n";
    exit(1);
}