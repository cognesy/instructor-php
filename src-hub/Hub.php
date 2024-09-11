<?php

namespace Cognesy\InstructorHub;

use Cognesy\Instructor\Container\Container;
use Cognesy\InstructorHub\Core\CliApp;

class Hub extends CliApp
{
    public string $name = "Hub // Instructor for PHP";
    public string $description = " (^) Get typed structured outputs from LLMs";

    public function __construct(Container $config)
    {
        parent::__construct($config);
    }
}