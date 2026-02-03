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

// Odoo - Delete Product
$router->add(
    '/odoo/delete-product/{id:[0-9]+}',
    [
        'controller' => 'odoo',
        'action'     => 'deleteProduct',
        'id'         => 1,
    ]
)->via(['POST', 'DELETE']); // Allow POST for easier HTML form use

// Odoo - Delete Customer
$router->add(
    '/odoo/delete-customer/{id:[0-9]+}',
    [
        'controller' => 'odoo',
        'action'     => 'deleteCustomer',
        'id'         => 1,
    ]
)->via(['POST', 'DELETE']);

// Odoo - Sales Orders
$router->add(
    '/odoo/sales-orders',
    [
        'controller' => 'odoo',
        'action'     => 'salesOrders',
    ]
);

// Odoo - Delete Sales Order
$router->add(
    '/odoo/delete-sales-order/{id:[0-9]+}',
    [
        'controller' => 'odoo',
        'action'     => 'deleteSalesOrder',
        'id'         => 1,
    ]
)->via(['POST', 'DELETE']);

// Odoo - Purchase Orders
$router->add(
    '/odoo/purchase-orders',
    [
        'controller' => 'odoo',
        'action'     => 'purchaseOrders',
    ]
);

// Odoo - Delete Purchase Order
$router->add(
    '/odoo/delete-purchase-order/{id:[0-9]+}',
    [
        'controller' => 'odoo',
        'action'     => 'deletePurchaseOrder',
        'id'         => 1,
    ]
)->via(['POST', 'DELETE']);

// Odoo - Invoices
$router->add(
    '/odoo/invoices',
    [
        'controller' => 'odoo',
        'action'     => 'invoices',
    ]
);

// Odoo - Delete Invoice
$router->add(
    '/odoo/delete-invoice/{id:[0-9]+}',
    [
        'controller' => 'odoo',
        'action'     => 'deleteInvoice',
        'id'         => 1,
    ]
)->via(['POST', 'DELETE']);

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

// Odoo - Process Payment
$router->add(
    '/odoo/process-payment',
    [
        'controller' => 'odoo',
        'action'     => 'processPayment',
    ]
)->via(['POST']);

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
