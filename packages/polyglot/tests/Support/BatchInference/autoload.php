<?php

declare(strict_types=1);

$autoloaders = [
    dirname(__DIR__, 3).'/vendor/autoload.php',
    dirname(__DIR__, 5).'/vendor/autoload.php',
];

foreach ($autoloaders as $autoload) {
    if (is_file($autoload)) {
        require_once $autoload;
        return;
    }
}

throw new RuntimeException('Could not locate the Polyglot or monorepo Composer autoloader.');
