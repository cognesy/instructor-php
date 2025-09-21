<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse;

use Cognesy\Addons\ToolUse\Contracts\ToolInterface;
use Cognesy\Addons\ToolUse\Exceptions\InvalidToolException;
use Cognesy\Polyglot\Inference\Data\ToolCalls;

final readonly class Tools
{
    /** @var ToolInterface[] */
    private array $tools;

    /**
     * @param ToolInterface[] $tools
     */
    public function __construct(
        array $tools = [],
    ) {
        $toolsArray = [];
        foreach ($tools as $tool) {
            $toolsArray[$tool->name()] = $tool;
        }
        $this->tools = $toolsArray;
    }

    public function toToolSchema(): array {
        $schema = [];
        foreach ($this->tools as $tool) {
            $schema[] = $tool->toToolSchema();
        }
        return $schema;
    }

    // ACCESSORS ////////////////////////////////////////////////

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

    // MUTATORS ////////////////////////////////////////////////

    public function withTools(ToolInterface ...$tools): Tools {
        $newTools = $this->tools;
        foreach ($tools as $tool) {
            $newTools[$tool->name()] = $tool;
        }
        return new self($newTools);
    }

    public function withTool(ToolInterface $tool): Tools {
        return $this->withTools($tool);
    }

    public function withToolRemoved(string $name): Tools {
        $newTools = $this->tools;
        unset($newTools[$name]);
        return new self($newTools);
    }

    /** @return ToolInterface[] */
    public function all(): array
    {
        return $this->tools;
    }
}
