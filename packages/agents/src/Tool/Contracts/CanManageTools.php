<?php declare(strict_types=1);

namespace Cognesy\Agents\Tool\Contracts;

interface CanManageTools
{
    public function register(ToolInterface $tool): void;

    /**
     * @param callable(): ToolInterface $factory
     */
    public function registerFactory(string $name, callable $factory): void;

    public function has(string $name): bool;

    public function get(string $name): ToolInterface;

    /** @return array<string, ToolInterface> */
    public function all(): array;

    /** @return array<int, string> */
    public function names(): array;

    public function count(): int;
}
