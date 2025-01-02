<?php

namespace Cognesy\Instructor\Extras\Chat;

use Cognesy\Instructor\Utils\Messages\Message;
use Cognesy\Instructor\Utils\Messages\Messages;
use Cognesy\Instructor\Utils\Messages\Script;

class ScriptPipeline {
    private array $sections;
    private array $processors;
    private Script $script;

    public function __construct(array $sections = [], array $processors = []) {
        $this->sections = $sections;
        $this->processors = $processors;
        $this->script = new Script();

        // Initialize all sections
        foreach ($sections as $section) {
            $this->script->section($section);
        }
    }

    public function appendMessage(Message $message, string $section): self {
        $this->script->section($section)->appendMessage($message);
        return $this->process();
    }

    public function appendMessages(Messages $messages, string $section): self {
        $this->script->section($section)->appendMessages($messages);
        return $this->process();
    }

    public function process(): self {
        foreach ($this->processors as $processor) {
            if ($processor->shouldProcess($this->script)) {
                $this->script = $processor->process($this->script);
            }
        }
        return $this;
    }

    public function script(): Script {
        return $this->script;
    }

    public function messages(array $sectionOrder = []): Messages {
        return $this->script
            ->select($sectionOrder ?: $this->sections)
            ->toMessages();
    }
}