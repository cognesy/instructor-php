<?php

namespace Cognesy\Instructor\Evaluation\Data;

use Cognesy\Instructor\Data\RequestInfo;

class Evaluation
{
    public array $actual = [];
    public array $expected = [];
    public RequestInfo $request;
    public EvaluationResult $result;
}
