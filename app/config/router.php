<?php
/**
 * ============================================
 * Router Configuration
 * ============================================
 * 
 * Mengatur routing URL ke Controller/Action
 */

use Phalcon\Mvc\Router;

$router = new Router(false);

// Hapus trailing slash
$router->removeExtraSlashes(true);

// =====================
// ROUTE DEFINITIONS
// =====================

// Homepage
$router->add(
    '/',
    [
        'controller' => 'index',
        'action'     => 'index',
    ]
);

// WiFi - Form (GET)
$router->add(
    '/wifi',
    [
        'controller' => 'wifi',
        'action'     => 'index',
    ]
)->via(['GET']);

// WiFi - Generate Password (POST)
$router->add(
    '/wifi/generate',
    [
        'controller' => 'wifi',
        'action'     => 'generate',
    ]
)->via(['POST']);

// WiFi - History
$router->add(
    '/wifi/history',
    [
        'controller' => 'wifi',
        'action'     => 'history',
    ]
);

// Default route (fallback)
$router->add(
    '/:controller/:action/:params',
    [
        'controller' => 1,
        'action'     => 2,
        'params'     => 3,
    ]
);

$router->add(
    '/:controller',
    [
        'controller' => 1,
        'action'     => 'index',
    ]
);

// Set 404 handler
$router->notFound([
    'controller' => 'index',
    'action'     => 'index',
]);

return $router;
