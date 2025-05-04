<?php
namespace Cognesy\Instructor\Features\Core\Data;

use Cognesy\Polyglot\LLM\Enums\OutputMode;

class StructuredOutputRequestInfo
{
    use Traits\RequestInfo\HandlesMutation;
    use Traits\RequestInfo\HandlesCreation;
    use Traits\RequestInfo\HandlesSerialization;

    public string|array $messages;
    public string|array|object $input;
    public string|array|object $responseModel;
    public string $model;
    public string $system;
    public string $prompt;
    public int $maxRetries;
    public array $options;
    /** @var Example[] */
    public array $examples;
    public string $retryPrompt;
    public string $toolName;
    public string $toolDescription;
    public OutputMode $mode;
    public array $cachedContext = [];

    public function isStreamed() : bool {
        return $this->options['stream'] ?? false;
    }

    public function toMessages() : array {
        return $this->messages;
    }
}
