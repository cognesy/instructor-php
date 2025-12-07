<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Contracts;

interface CanPersistStatus
{
    /** @param array<string, mixed> $statusData */
    public function save(array $statusData): void;

    /** @return array<string, mixed> */
    public function load(): array;

    public function exists(): bool;

    public function clear(): void;

    public function backup(): string;

    public function getLastModified(): ?\DateTimeImmutable;

    public function getFilePath(): string;
}
