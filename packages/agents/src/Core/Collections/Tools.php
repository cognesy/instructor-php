<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Collections;

use Cognesy\Agents\Core\Contracts\ToolInterface;
use Cognesy\Agents\Core\Exceptions\InvalidToolException;

final readonly class Tools
{
    /** @var ToolInterface[] */
    private array $tools;

    /**
     * @param ToolInterface ...$tools
     */
    public function __construct(
        ToolInterface ...$tools,
    ) {
        $toolsArray = [];
        foreach ($tools as $tool) {
            $toolsArray[$tool->name()] = $tool;
        }
        $this->tools = $toolsArray;
    }

    // ACCESSORS ////////////////////////////////////////////////

    /** @return ToolInterface[] */
    public function all(): array {
        return $this->tools;
    }

    public function isEmpty(): bool {
        return count($this->tools) === 0;
    }

    public function count(): int {
        return count($this->tools);
    }

    public function has(string $name): bool {
        return isset($this->tools[$name]);
    }

    public function get(string $name): ToolInterface {
        if (!$this->has($name)) {
            throw new InvalidToolException("Tool '$name' not found.");
        }
        return $this->tools[$name];
    }

    public function names(): array {
        return array_keys($this->tools);
    }

    public function descriptions(): array {
        $toolsList = [];
        foreach ($this->tools as $tool) {
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
        return new self(...$newTools);
    }

    public function withTool(ToolInterface $tool): Tools {
        return $this->withTools($tool);
    }

    public function withToolRemoved(string $name): Tools {
        $newTools = $this->tools;
        unset($newTools[$name]);
        return new self(...$newTools);
    }

    public function merge(Tools $other): Tools {
        return $this->withTools(...$other->all());
    }

    // TRANSFORMERS AND CONVERSIONS //////////////////////////////

    public function toToolSchema(): array {
        $schema = [];
        foreach ($this->tools as $tool) {
            $schema[] = $tool->toToolSchema();
        }
        return $schema;
    }
}
