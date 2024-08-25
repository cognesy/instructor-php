<?php
namespace Cognesy\Instructor\Data\Traits\Request;

use Cognesy\Instructor\Data\ChatTemplate;

trait HandlesPrompts
{
    private string $retryPrompt;
    private string $prompt = '';
    private string $system = '';
    protected array $cachedContext = [];

    public function withPrompt(string $prompt) : self {
        $this->prompt = $prompt;
        return $this;
    }

    public function withSystem(string $system) : self {
        $this->system = $system;
        return $this;
    }

    public function prompt() : string {
        return $this->prompt;
    }

    public function retryPrompt() : string {
        return $this->retryPrompt;
    }

    public function system() : string {
        return $this->system;
    }

    public function cachedContext() : array {
        return $this->cachedContext;
    }

    public function toMessages() : array {
        return ChatTemplate::fromRequest($this)->toMessages();
    }
}