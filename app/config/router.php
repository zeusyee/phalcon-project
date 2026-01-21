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

// =====================
// ODOO INTEGRATION ROUTES
// =====================

// Odoo - Dashboard
$router->add(
    '/odoo',
    [
        'controller' => 'odoo',
        'action'     => 'index',
    ]
);

// Odoo - Customers
$router->add(
    '/odoo/customers',
    [
        'controller' => 'odoo',
        'action'     => 'customers',
    ]
);

// Odoo - Test Connection
$router->add(
    '/odoo/test',
    [
        'controller' => 'odoo',
        'action'     => 'test',
    ]
);

// Odoo - Products
$router->add(
    '/odoo/products',
    [
        'controller' => 'odoo',
        'action'     => 'products',
    ]
);

// Odoo - Sales Orders
$router->add(
    '/odoo/sales-orders',
    [
        'controller' => 'odoo',
        'action'     => 'salesOrders',
    ]
);

// Odoo - Purchase Orders
$router->add(
    '/odoo/purchase-orders',
    [
        'controller' => 'odoo',
        'action'     => 'purchaseOrders',
    ]
);

// Odoo - Invoices
$router->add(
    '/odoo/invoices',
    [
        'controller' => 'odoo',
        'action'     => 'invoices',
    ]
);

// Odoo - Inventory
$router->add(
    '/odoo/inventory',
    [
        'controller' => 'odoo',
        'action'     => 'inventory',
    ]
);

// Odoo - Create Customer (Form & POST)
$router->add(
    '/odoo/create-customer',
    [
        'controller' => 'odoo',
        'action'     => 'createCustomer',
    ]
);

// Odoo - Update Customer
$router->add(
    '/odoo/update-customer/{id:[0-9]+}',
    [
        'controller' => 'odoo',
        'action'     => 'updateCustomer',
        'id'         => 1,
    ]
);

// Odoo - Get Customer by ID
$router->add(
    '/odoo/customer/{id:[0-9]+}',
    [
        'controller' => 'odoo',
        'action'     => 'getCustomer',
        'id'         => 1,
    ]
);

// Odoo - Sync WiFi Passwords
$router->add(
    '/odoo/sync-wifi',
    [
        'controller' => 'odoo',
        'action'     => 'sync-wifi',
    ]
)->via(['GET', 'POST']);

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
