<?php
namespace Cognesy\Instructor\Features\Core\Data\Traits\Request;

use Cognesy\Instructor\Features\Core\Data\ChatTemplate;
use Cognesy\LLM\LLM\Enums\Mode;

trait HandlesMessages
{
    private string|array $messages;
    private string $model;
    private string $prompt = '';
    private string $retryPrompt;
    private string $system = '';
    private string|array|object $input = '';
    private array $examples;
    private array $cachedContext = [];
    private array $options = [];
    private Mode $mode;

    // PUBLIC /////////////////////////////////////////////////////////////////

    public function cachedContext() : array {
        return $this->cachedContext;
    }

    public function examples() : array {
        return $this->examples;
    }

    public function input(): string|array|object {
        return $this->input;
    }

    public function messages() : array {
        if (is_string($this->messages)) {
            return [['role' => 'user', 'content' => $this->messages]];
        }
        return $this->messages;
    }

    public function mode() : Mode {
        return $this->mode;
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
        return $this->retryPrompt;
    }

    public function setOption(string $key, mixed $value) : self {
        return $this->options[$key] = $value;
    }

    public function system() : string {
        return $this->system;
    }

    public function toMessages() : array {
        return ChatTemplate::fromRequest($this)->toMessages();
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
        $this->messages = $messages;
        return $this;
    }

    protected function normalizeMessages(string|array $messages): array {
        if (!is_array($messages)) {
            return [['role' => 'user', 'content' => $messages]];
        }
        return $messages;
    }
}