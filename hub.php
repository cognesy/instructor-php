<?php

use Cognesy\Instructor\Container\Container;
use Cognesy\Instructor\Events\EventDispatcher;

use Cognesy\InstructorHub\Configs\CommandConfig;
use Cognesy\InstructorHub\Configs\ServiceConfig;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\InstructorHub\\', __DIR__ . '../src-hub/');

// wire up core components
$events = new EventDispatcher('hub');
$config = Container::fresh($events)
    ->external(
        class: EventDispatcher::class,
        reference: $events
    )
    ->fromConfigProviders([
        new CommandConfig(),
        new ServiceConfig(),
    ]);

// run the app
$app = new Cognesy\InstructorHub\Hub($config);
$app->run($argc, $argv);
