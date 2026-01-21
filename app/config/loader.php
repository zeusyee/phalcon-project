<?php

$loader = new \Phalcon\Autoload\Loader();

// Register namespaces
$loader->setNamespaces([
    'App\Controllers' => APP_PATH . '/controllers/',
    'App\Models'      => APP_PATH . '/models/',
    'App\Services'    => APP_PATH . '/services/',
]);

// Register directories (untuk backward compatibility)
$loader->setDirectories(
    [
        APP_PATH . '/controllers/',
        APP_PATH . '/models/',
        APP_PATH . '/services/',
    ]
);

$loader->register();
