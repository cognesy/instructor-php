<?php

namespace Cognesy\Addons\ToolUse\Traits\Tools;

use Cognesy\Addons\ToolUse\Contracts\ToolInterface;
use Cognesy\Addons\ToolUse\Exceptions\InvalidToolException;
use Cognesy\Polyglot\LLM\Data\ToolCalls;

trait HandlesAccess
{
    public function has(string $name): bool {
        return isset($this->tools[$name]);
    }

    public function get(string $name): ToolInterface {
        if (!$this->has($name)) {
            throw new InvalidToolException("Tool '$name' not found.");
        }
        return $this->tools[$name];
    }

    /**
     * @return string[]
     */
    public function missing(?ToolCalls $toolCalls): array {
        $missing = [];
        foreach ($toolCalls?->all() as $toolCall) {
            if (!$this->has($toolCall->name())) {
                $missing[] = $toolCall->name();
            }
        }
        return $missing;
    }

    public function canExecute(ToolCalls $toolCalls) : bool {
        foreach ($toolCalls->all() as $toolCall) {
            if (!$this->has($toolCall->name())) {
                return false;
            }
        }
        return true;
    }

    public function nameList() : array {
        return array_keys($this->tools);
    }

    public function descriptionList() : array {
        $toolsList = [];
        foreach($this->tools as $tool) {
            $toolsList[] = [
                'name' => $tool->name(),
                'description' => $tool->description(),
            ];
        }
        return $toolsList;
    }
}