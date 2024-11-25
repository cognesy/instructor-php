<?php

namespace Cognesy\Instructor\Extras\ToolUse\Traits\Tools;

use Cognesy\Instructor\Extras\ToolUse\Tools;
use Cognesy\Instructor\Extras\ToolUse\Tools\FunctionTool;

trait HandlesFunctions
{
    /**
     * @param callable[] $functions
     */
    public static function fromFunctions(array $functions): Tools {
        $tools = new self();
        foreach ($functions as $function) {
            $tools->addFunction($function);
        }
        return $tools;
    }

    public function addFunction(callable $function, string $name = '', string $description = ''): self {
        $tool = FunctionTool::fromCallable($function);
        $name = $name ?: $tool->name();
        $description = $description ?: $tool->description();
        $tool->withName($name)->withDescription($description);
        $this->tools[$name] = $tool;
        return $this;
    }
}