<?php
/**
 * ============================================
 * Services Configuration
 * ============================================
 * 
 * Mendefinisikan semua service yang digunakan aplikasi
 */

use Phalcon\Db\Adapter\Pdo\Mysql;
use Phalcon\Mvc\View;
use Phalcon\Mvc\Url as UrlResolver;

// =====================
// DATABASE SERVICE
// =====================
$di->set(
    'db',
    function () {
        return new Mysql([
            'host'     => getenv('DB_HOST') ?: 'mysql',
            'username' => getenv('DB_USER') ?: 'phalcon_user',
            'password' => getenv('DB_PASSWORD') ?: 'phalcon_password',
            'dbname'   => getenv('DB_NAME') ?: 'phalcon_db',
            'charset'  => 'utf8mb4',
        ]);
    }
);

// =====================
// VIEW SERVICE
// =====================
$di->set(
    'view',
    function () {
        $view = new View();
        $view->setViewsDir(APP_PATH . '/views/');
        return $view;
    }
);

// =====================
// URL SERVICE
// =====================
$di->set(
    'url',
    function () {
        $url = new UrlResolver();
        $url->setBaseUri('/');
        return $url;
    }
);

// =====================
// ROUTER SERVICE
// =====================
$di->set(
    'router',
    function () {
        return require APP_PATH . '/config/router.php';
    }
);
