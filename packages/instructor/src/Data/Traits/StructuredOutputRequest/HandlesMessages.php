<?php
namespace Cognesy\Instructor\Data\Traits\StructuredOutputRequest;

use Cognesy\Instructor\Data\CachedContext;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Utils\Messages\Message;
use Cognesy\Utils\Messages\Messages;

trait HandlesMessages
{
    // PUBLIC /////////////////////////////////////////////////////////////////

    public function cachedContext() : CachedContext {
        return $this->cachedContext;
    }

    public function examples() : array {
        return $this->examples;
    }

    public function messages() : array {
        return $this->messages->toArray();
    }

    public function mode() : OutputMode {
        return $this->config->outputMode();
    }

    public function model() : string {
        return $this->model;
    }

    public function options() : array {
        return $this->options;
    }

    public function option(string $name) : mixed {
        return $this->options[$name] ?? null;
    }

    public function prompt() : string {
        return $this->prompt;
    }

    public function retryPrompt() : string {
        return $this->config->retryPrompt();
    }

    public function setOption(string $key, mixed $value) : self {
        return $this->options[$key] = $value;
    }

    public function system() : string {
        return $this->system;
    }

    public function toMessages() : array {
        return $this->chatTemplate->toMessages($this);
    }

    public function withPrompt(string $prompt) : self {
        $this->prompt = $prompt;
        return $this;
    }

    public function withSystem(string $system) : self {
        $this->system = $system;
        return $this;
    }

    // INTERNAL ///////////////////////////////////////////////////////////////

    protected function withExamples(array $examples) : self {
        $this->examples = $examples;
        return $this;
    }

    protected function withMessages(array $messages) : self {
        $this->messages = $this->normalizeMessages($messages);
        return $this;
    }

    protected function normalizeMessages(string|array|Message|Messages $messages): Messages {
        return Messages::fromAny($messages);
    }
}