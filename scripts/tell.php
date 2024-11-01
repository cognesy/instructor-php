<?php
namespace Cognesy\Tell;

require __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Console\Application;

$application = new Application('Instructor Tell', '1.0.0');
$application->add(new TellCommand());
$application->run();
