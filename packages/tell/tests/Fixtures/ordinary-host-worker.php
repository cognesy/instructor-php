<?php

declare(strict_types=1);

use Cognesy\Tell\Tell;

require $argv[1];

$tell = Tell::testing($argv[2], 'deterministic');
$tell->dispose();

echo class_exists('CordisPhp\\Runtime\\Runtime', false)
    ? 'cordis=loaded'
    : 'cordis=not-loaded';
