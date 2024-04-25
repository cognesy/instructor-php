<?php

namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\Enums\Mode;

class Request
{
    public function __construct(
        public string|array $messages,
        public string|object|array $responseModel,
        public string $model = '',
        public int $maxRetries = 0,
        public array $options = [],
        public string $functionName = 'extract_data',
        public string $functionDescription = 'Extract data from provided content',
        public string $retryPrompt = "Recall function correctly, fix following errors",
        public Mode $mode = Mode::Tools,
        public ?CanCallApi $client = null,
    ) {}

    public function messages() : array {
        if (is_string($this->messages)) {
            return [['role' => 'user', 'content' => $this->messages]];
        }
        return $this->messages;
    }
}
