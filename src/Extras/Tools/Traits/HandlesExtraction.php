<?php

namespace Cognesy\Instructor\Extras\Tools\Traits;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Instructor;

trait HandlesExtraction
{
    private Instructor $instructor;

    public function extractArgs(
        string|array $messages,
        string $model = '',
        int $maxRetries = 2,
        array $options = [],
        array $examples = [],
        string $prompt = '',
        string $retryPrompt = '',
        Mode $mode = Mode::Tools,
    ) : array {
        return $this->getInstructor()->respond(
            messages: $messages,
            responseModel: $this->getResponseModel(),
            model: $model,
            maxRetries: $maxRetries,
            options: $options,
            examples: $examples,
            toolName: $this->getName(),
            toolDescription: $this->getDescription(),
            prompt: $prompt,
            retryPrompt: $retryPrompt,
            mode: $mode,
        );
    }

    protected function getInstructor(): Instructor {
        return $this->instructor ?? new Instructor();
    }
}