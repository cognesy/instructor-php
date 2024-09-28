<?php
namespace Cognesy\Instructor\Data;

use Cognesy\Instructor\Enums\Mode;

class RequestInfo
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
    public Mode $mode;
    public array $cachedContext = [];
    public string $connection = '';

    public function isStream() : bool {
        return $this->options['stream'] ?? false;
    }
}
