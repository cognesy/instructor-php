<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use InvalidArgumentException;

readonly final class ToolDefinitions
{
    /** @var ToolDefinition[] */
    private array $tools;

    public function __construct(ToolDefinition ...$tools) {
        $this->tools = array_values($tools);
    }

    public static function empty() : self {
        return new self();
    }

    public static function fromArray(array $tools) : self {
        $list = array_map(
            fn (mixed $tool) => match (true) {
                $tool instanceof ToolDefinition => $tool,
                is_array($tool) => ToolDefinition::fromArray($tool),
                default => throw new InvalidArgumentException('ToolDefinitions accepts only ToolDefinition instances or tool definition arrays.'),
            },
            $tools,
        );

        return new self(...$list);
    }

    /** @return ToolDefinition[] */
    public function all() : array {
        return $this->tools;
    }

    public function isEmpty() : bool {
        return $this->tools === [];
    }

    public function count() : int {
        return count($this->tools);
    }

    public function toArray() : array {
        return array_values(array_map(
            fn (ToolDefinition $tool) => $tool->toArray(),
            $this->tools,
        ));
    }
}
