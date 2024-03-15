<?php

use Cognesy\Instructor\Configuration\Configuration;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\InstructorHub\\', __DIR__ . '../src-hub/');

$config = hub(new Configuration());
$app = new Cognesy\InstructorHub\Hub($config);
$app->run($argc, $argv);
