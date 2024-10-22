<?php

namespace Cognesy\Instructor\Extras\Evals\Metrics\Generic;

use Cognesy\Instructor\Extras\Evals\Units\TokensUnit;

class TokenCount extends IntMetric
{
    public function __construct(
        string $name,
        int $value,
    ) {
        parent::__construct($name, $value, new TokensUnit());
    }
}