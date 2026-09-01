<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Contract\Workspace;

interface CanUseTellBranchConfigurationStore
{
    /** @return array{version: int, values: array<string, mixed>} */
    public function read(string $branch): array;

    /** @return array<string, int|list<string>|string> */
    public function runtimeValues(string $branch): array;

    /** @return array<string, int> */
    public function executionDefaults(): array;

    /** @return list<string> */
    public function keys(): array;

    /** @return array{version: int, values: array<string, mixed>, provenance: array<string, 'branch'|'bundled'>} */
    public function effective(string $branch): array;

    /** @return array{version: int, values: array<string, mixed>} */
    public function set(string $branch, string $key, mixed $value, int $expectedVersion): array;

    /** @return array{version: int, values: array<string, mixed>} */
    public function delete(string $branch, string $key, int $expectedVersion): array;

    public function inherit(string $source, string $destination): void;
}
