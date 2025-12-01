<?php

declare(strict_types=1);

use Hyperf\Contract\ApplicationInterface;
use Hyperf\Di\ClassLoader;
use Hyperf\Di\Container;
use Hyperf\Di\Definition\DefinitionSourceFactory;
use Hyperf\Utils\ApplicationContext;

ini_set('display_errors', 'on');
ini_set('display_startup_errors', 'on');

error_reporting(E_ALL);
date_default_timezone_set('America/Sao_Paulo');

! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

require BASE_PATH . '/vendor/autoload.php';

// Self-called anonymous function that creates its own scope and keeps the global namespace clean.
(function () {
    ClassLoader::init();
    /** @var Container $container */
    $container = require BASE_PATH . '/config/container.php';
    ApplicationContext::setContainer($container);

    $application = $container->get(ApplicationInterface::class);
    $application->run();
})();