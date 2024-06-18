<?php

namespace Cognesy\Instructor\Evaluation\Data;

use Cognesy\Instructor\Data\RequestInfo;
use Cognesy\Instructor\Evaluation\Contracts\Metric;

class Evaluation
{
    public array $actual = [];
    public array $expected = [];
    public RequestInfo $request;
    public Metric $metric;
    public Feedback $feedback;
}
