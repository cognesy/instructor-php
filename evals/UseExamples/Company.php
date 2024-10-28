<?php

namespace Cognesy\Evals\UseExamples;

use Cognesy\Instructor\Features\Schema\Attributes\Description;

class Company {
    #[Description("The name of the company")]
    public string $name;
    #[Description("The year the company was founded")]
    public int $year;
}
