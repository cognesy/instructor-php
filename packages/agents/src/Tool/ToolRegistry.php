<?php declare(strict_types=1);

namespace Cognesy\Agents\Tool;

use Cognesy\Agents\Exceptions\InvalidToolException;
use Cognesy\Agents\Tool\Contracts\CanManageTools;
use Cognesy\Agents\Tool\Contracts\ToolInterface;

final class ToolRegistry implements CanManageTools
{
    /** @var array<string, callable(): ToolInterface> */
    private array $factories = [];

    /** @var array<string, ToolInterface> */
    private array $instances = [];

    #[\Override]
    public function register(ToolInterface $tool): void {
        $name = $tool->descriptor()->name();
        $this->instances[$name] = $tool;
    }

    #[\Override]
    public function registerFactory(string $name, callable $factory): void {
        $this->factories[$name] = $factory;
    }

    #[\Override]
    public function has(string $name): bool
    {
        return isset($this->instances[$name]) || isset($this->factories[$name]);
    }

    #[\Override]
    public function get(string $name): ToolInterface
    {
        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        if (isset($this->factories[$name])) {
            $tool = ($this->factories[$name])();
            $this->instances[$name] = $tool;
            return $tool;
        }

        throw new InvalidToolException("Tool '{$name}' not found in registry.");
    }

    /** @return array<string, ToolInterface> */
    public function all(): array {
        $all = [];
        foreach ($this->names() as $name) {
            $all[$name] = $this->get($name);
        }

        return $all;
    }

    #[\Override]
    public function names(): array
    {
        return array_keys($this->factories + $this->instances);
    }

    #[\Override]
    public function count(): int
    {
        return count($this->names());
    }
}
