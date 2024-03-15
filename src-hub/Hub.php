<?php

namespace Cognesy\InstructorHub;

use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\InstructorHub\Core\CliApp;

class Hub extends CliApp
{
    public string $name = "Instructor for PHP";
    public string $description = " (^) Get structured outputs from LLMs";

    public function __construct(Configuration $config)
    {
        parent::__construct($config);
    }
}