<?php

use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\InstructorHub\Configs\HubConfigurator;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\InstructorHub\\', __DIR__ . '../src-hub/');

// wire up core components
$events = new EventDispatcher();
$config = Configuration::fresh($events);
HubConfigurator::with([
    EventDispatcher::class => $events
])->setup($config);

// run the app
$app = new Cognesy\InstructorHub\Hub($config);
$app->run($argc, $argv);
