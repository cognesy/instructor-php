<?php declare(strict_types=1);

$loader = require 'vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Optional record/replay HTTP switch (default: pass = no change).
// Controlled by INSTRUCTOR_EXAMPLES_HTTP / INSTRUCTOR_EXAMPLES_RECORDINGS_DIR.
require_once __DIR__ . '/_support/HttpRecordingBoot.php';
\Examples\Support\HttpRecordingBoot::bootFromEnv();
