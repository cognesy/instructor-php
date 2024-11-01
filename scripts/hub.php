<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/bootstrap.php';

// run the app
$app = new Cognesy\InstructorHub\Hub();
$app->run($argc, $argv);
