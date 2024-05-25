<?php

namespace Cognesy\Instructor\Extras\Task;

use BackedEnum;
use Cognesy\Instructor\Data\Example;
use Cognesy\Instructor\Extras\Signature\Signature;
use Cognesy\Instructor\Instructor;

class PredictTask extends ExecutableTask
{
    private Instructor $instructor;
    public string $prompt;
    public $responseModel;

    public function __construct(
        string|Signature $signature,
        Instructor $instructor,
    ) {
        parent::__construct($signature);
        $this->instructor = $instructor;
    }

    public function forward(mixed ...$args): mixed {
        $input = match(true) {
            count($args) === 0 => throw new \Exception('Empty input'),
            count($args) === 1 => reset($args),
            default => $args,
        };
        $response = $this->instructor->respond(
            messages: $this->toMessages($input),
            responseModel: $this->signature->getOutputs(),
        );
        return $response->toArray();
    }

    private function toMessages(string|array $input) : array {
        $content = match(true) {
            is_string($input) => $input,
            $input instanceof Example => $input->input(),
            $input instanceof BackedEnum => $input->value,
            // ...
            default => json_encode($input),
        };
        return [
            ['role' => 'user', 'content' => $this->prompt()],
            ['role' => 'assistant', 'content' => 'Provide data for processing'],
            ['role' => 'user', 'content' => $content]
        ];
    }

    public function prompt() : string {
        if (empty($this->prompt)) {
            $this->prompt = $this->signature->toString();
        }
        return $this->prompt;
    }

    public function setPrompt(string $prompt) : void {
        $this->prompt = $prompt;
    }
}
