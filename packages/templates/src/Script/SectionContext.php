<?php declare(strict_types=1);

namespace Cognesy\Template\Script;

use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

/**
 * Context object that allows fluent section operations while maintaining immutability
 */
class SectionContext
{
    public function __construct(
        private Script $script,
        private string $sectionName,
    ) {}

    public function appendMessage(array|Message $message): Script {
        return $this->script->appendMessageToSection($this->sectionName, $message);
    }

    public function appendMessages(Messages $messages): Script {
        $script = $this->script;
        foreach ($messages->each() as $message) {
            $script = $script->appendMessageToSection($this->sectionName, $message);
        }
        return $script;
    }

    public function prependMessage(array|Message $message): Script {
        return $this->script->prependMessageToSection($this->sectionName, $message);
    }

    public function section(string $name): SectionContext {
        return new SectionContext($this->script, $name);
    }

    public function script(): Script {
        return $this->script;
    }
}