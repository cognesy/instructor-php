<?php

namespace Cognesy\Evals\LLMModes;

use Exception;

class EvalResponse
{
    public function __construct(
        public string $id = '',
        public string $answer = '',
        public bool $isCorrect = false,
        public float $timeElapsed = 0.0,
        public ?Exception $exception = null,
    ) {}
}