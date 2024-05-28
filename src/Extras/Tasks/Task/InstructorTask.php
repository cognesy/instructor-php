<?php

namespace Cognesy\Instructor\Extras\Tasks\Task;

use Cognesy\Instructor\Extras\Tasks\Signature\Contracts\Signature;
use Cognesy\Instructor\Instructor;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated(reason: 'Needs revision')]
class InstructorTask extends ExecutableTask
{
    private Instructor $instructor;
    private string|object|array $responseModel;

    public function __construct(
        string|Signature $signature,
        string|object|array $responseModel,
        Instructor $instructor,
    ) {
        parent::__construct($signature);
        $this->instructor = $instructor;
        $this->responseModel = $responseModel;
    }

    public function forward(string|array $input): mixed {
        $messages = match(true) {
            empty($input) => throw new \Exception('Empty input'),
            is_string($input) => [['role' => 'user', 'content' => $input]],
            default => throw new \Exception('Invalid input type'),
        };
        return $this->instructor->respond(
            $messages,
            $this->responseModel,
        );
    }
}
