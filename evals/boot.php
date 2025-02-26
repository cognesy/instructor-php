<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Evals\\', __DIR__ . '../evals/');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
