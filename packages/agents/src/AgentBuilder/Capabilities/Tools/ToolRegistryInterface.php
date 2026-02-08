<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Tools;

use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\Core\Contracts\ToolInterface;

interface ToolRegistryInterface
{
    public function register(ToolInterface $tool): void;

    /**
     * @param callable(): ToolInterface $factory
     * @param array<string, mixed>|null $metadata
     * @param array<string, mixed>|null $fullSpec
     */
    public function registerFactory(
        string $name,
        callable $factory,
        ?array $metadata = null,
        ?array $fullSpec = null,
    ): void;

    public function has(string $name): bool;

    public function resolve(string $name): ToolInterface;

    /** @return array<int, array<string, mixed>> */
    public function listMetadata(): array;

    /** @return array<int, array<string, mixed>> */
    public function listFullSpecs(): array;

    /** @return array<int, array<string, mixed>> */
    public function search(string $query): array;

    /** @param array<int, string>|null $names */
    public function buildTools(?array $names = null, ?ToolPolicy $policy = null): Tools;

    /** @return array<int, string> */
    public function names(): array;
}
