<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use Cognesy\Messages\Messages;

class CachedContext
{
    private Messages $messages;
    private array $tools;
    private string|array $toolChoice;
    private ResponseFormat $responseFormat;

    public function __construct(
        string|array $messages = [],
        array $tools = [],
        string|array $toolChoice = [],
        array|ResponseFormat|null $responseFormat = null,
    ) {
        $this->messages = Messages::fromAny($messages);
        $this->tools = $tools;
        $this->toolChoice = $toolChoice;
        $this->responseFormat = match(true) {
            $responseFormat instanceof ResponseFormat => $responseFormat,
            is_array($responseFormat) => ResponseFormat::fromData($responseFormat),
            default => new ResponseFormat(),
        };
    }

    public function messages() : Messages {
        return $this->messages;
    }

    public function tools() : array {
        return $this->tools;
    }

    public function toolChoice() : string|array {
        return $this->toolChoice;
    }

    public function responseFormat() : ResponseFormat {
        return $this->responseFormat;
    }

    public function isEmpty() : bool {
        return $this->messages->isEmpty()
            && empty($this->tools)
            && empty($this->toolChoice)
            && $this->responseFormat->isEmpty();
    }
}
