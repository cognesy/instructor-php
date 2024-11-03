<?php
namespace Cognesy\Tell;

require __DIR__ . '/bootstrap.php';

use Symfony\Component\Console\Application;

$application = new Application('Instructor Tell', '1.0.0');
$application->add(new TellCommand());
$application->setDefaultCommand('tell', true);
$application->run();
