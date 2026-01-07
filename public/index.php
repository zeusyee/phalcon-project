<?php

use Phalcon\Di\FactoryDefault;
use Phalcon\Mvc\Application;

error_reporting(E_ALL);

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');

try {
    // Register an autoloader
    require APP_PATH . '/config/loader.php';

    // Create a DI
    $di = new FactoryDefault();

    // Setup the database service
    require APP_PATH . '/config/services.php';

    // Handle the request
    $application = new Application($di);

    $response = $application->handle(
        $_SERVER["REQUEST_URI"]
    );

    $response->send();

} catch (\Exception $e) {
    echo $e->getMessage() . '<br>';
    echo '<pre>' . $e->getTraceAsString() . '</pre>';
}
