<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability;

use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use InvalidArgumentException;

final class AgentCapabilityRegistry implements CanManageAgentCapabilities
{
    /** @var array<string, callable(): CanProvideAgentCapability> */
    private array $factories = [];

    /** @var array<string, CanProvideAgentCapability> */
    private array $instances = [];

    public function register(string $name, CanProvideAgentCapability $capability): void {
        $this->instances[$name] = $capability;
    }

    /**
     * @param callable(): CanProvideAgentCapability $factory
     */
    public function registerFactory(string $name, callable $factory): void {
        $this->factories[$name] = $factory;
    }

    public function has(string $name): bool {
        return isset($this->instances[$name]) || isset($this->factories[$name]);
    }

    public function get(string $name): CanProvideAgentCapability {
        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        if (isset($this->factories[$name])) {
            $capability = ($this->factories[$name])();
            if (!$capability instanceof CanProvideAgentCapability) {
                throw new InvalidArgumentException(
                    "Capability factory for '{$name}' must return CanProvideAgentCapability."
                );
            }
            $this->instances[$name] = $capability;
            return $capability;
        }

        throw new InvalidArgumentException("Capability '{$name}' not found.");
    }

    /** @return array<string, CanProvideAgentCapability> */
    public function all(): array {
        $all = [];
        foreach ($this->names() as $name) {
            $all[$name] = $this->get($name);
        }

        return $all;
    }

    /**
     * @return array<int, string>
     */
    public function names(): array {
        return array_keys($this->factories + $this->instances);
    }

    public function count(): int {
        return count($this->names());
    }
}
