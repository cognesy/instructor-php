<?php

namespace Cognesy\Instructor\Extras\Agent\Traits\Toolset;

use Cognesy\Instructor\Extras\Agent\Tool;

trait HandlesAccess
{
    public function addTool(Tool $tool) : void {
        $this->tools[$tool->getName()] = $tool;
    }

    public function listTools() : array {
        return $this->tools;
    }

    public function hasTool(string $name) : bool {
        return isset($this->tools[$name]);
    }

    public function getTool(string $name) : Tool {
        if (!$this->hasTool($name)) {
            throw new \Exception('Tool not found');
        }
        return $this->tools[$name];
    }
}