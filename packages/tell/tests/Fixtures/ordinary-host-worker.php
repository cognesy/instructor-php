<?php

declare(strict_types=1);

use Cognesy\Tell\Tell;

$arguments = $_SERVER['argv'] ?? [];
$autoload = $arguments[1] ?? throw new RuntimeException('Missing Composer autoloader path.');
$project = $arguments[2] ?? throw new RuntimeException('Missing Tell project path.');

require $autoload;

$tell = Tell::testing($project, 'deterministic');
$tell->dispose();

echo class_exists('CordisPhp\\Runtime\\Runtime', false)
    ? 'cordis=loaded'
    : 'cordis=not-loaded';
