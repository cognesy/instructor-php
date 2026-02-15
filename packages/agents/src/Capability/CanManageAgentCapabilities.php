<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability;

use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;

interface CanManageAgentCapabilities
{
    public function register(string $name, CanProvideAgentCapability $capability): void;

    /**
     * @param callable(): CanProvideAgentCapability $factory
     */
    public function registerFactory(string $name, callable $factory): void;

    public function has(string $name): bool;

    public function get(string $name): CanProvideAgentCapability;

    /** @return array<string, CanProvideAgentCapability> */
    public function all(): array;

    /** @return array<int, string> */
    public function names(): array;

    public function count(): int;
}
