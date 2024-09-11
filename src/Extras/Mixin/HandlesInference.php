<?php
namespace Cognesy\Instructor\Extras\Mixin;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Instructor;

trait HandlesInference {
    public function infer(
        string|array $messages = '',
        string|array|object $input = '',

        string $model = '',
        int $maxRetries = 2,
        array $options = [],
        array $examples = [],
        string $prompt = '',
        Mode $mode = Mode::Tools,
        Instructor $instructor = null,
    ) : mixed {
        $instructor = $instructor ?? $this->getInstructor() ?? new Instructor;

        return $instructor->respond(
            messages: $messages,
            input: $input,
            responseModel: $this->getResponseModel(),
            prompt: $prompt,
            examples: $examples,
            model: $model,
            maxRetries: $maxRetries,
            options: $options,
            mode: $mode,
        );
    }

    abstract protected function getInstructor() : Instructor;

    abstract protected function getResponseModel() : string|array|object;
}
