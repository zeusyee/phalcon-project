<?php

$loader = new \Phalcon\Autoload\Loader();

// Register directories
$loader->setDirectories(
    [
        APP_PATH . '/controllers/',
        APP_PATH . '/models/',
    ]
);

$loader->register();
