<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\Workspace\Memory;

use Cognesy\Tell\Core\Contract\Workspace\CanUseTellBranchConfigurationStore;
use Cognesy\Tell\Core\Workspace\Branch\BranchConfigurationPolicy;
use Cognesy\Tell\Core\Workspace\WorkspaceException;
use InvalidArgumentException;
use Override;

final class InMemoryBranchConfigurationStore implements CanUseTellBranchConfigurationStore
{
    /** @var array<string, array{version: int, values: array<string, mixed>}> */
    private array $configuration = [];

    #[Override]
    public function read(string $branch): array {
        return $this->configuration[$branch] ?? ['version' => 0, 'values' => []];
    }

    #[Override]
    public function runtimeValues(string $branch): array {
        return $this->read($branch)['values'];
    }

    #[Override]
    public function executionDefaults(): array {
        return [];
    }

    #[Override]
    public function keys(): array {
        return BranchConfigurationPolicy::keys();
    }

    #[Override]
    public function effective(string $branch): array {
        $configuration = $this->read($branch);
        $values = array_replace(BranchConfigurationPolicy::defaults(), $configuration['values']);
        $provenance = array_fill_keys(array_keys(BranchConfigurationPolicy::defaults()), 'bundled');
        foreach (array_keys($configuration['values']) as $key) {
            $provenance[$key] = 'branch';
        }

        return ['version' => $configuration['version'], 'values' => $values, 'provenance' => $provenance];
    }

    #[Override]
    public function set(string $branch, string $key, mixed $value, int $expectedVersion): array {
        $this->assertVersion($branch, $expectedVersion);
        BranchConfigurationPolicy::assertValue($key, $value);
        $current = $this->read($branch);
        $current['values'][$key] = $value;
        ksort($current['values'], SORT_STRING);
        $current['version']++;

        return $this->configuration[$branch] = $current;
    }

    #[Override]
    public function delete(string $branch, string $key, int $expectedVersion): array {
        $this->assertVersion($branch, $expectedVersion);
        BranchConfigurationPolicy::assertKey($key);
        $current = $this->read($branch);
        unset($current['values'][$key]);
        $current['version']++;

        return $this->configuration[$branch] = $current;
    }

    #[Override]
    public function inherit(string $source, string $destination): void {
        $sourceConfiguration = $this->read($source);
        if ($sourceConfiguration['version'] === 0) {
            return;
        }
        $this->configuration[$destination] = ['version' => 1, 'values' => $sourceConfiguration['values']];
    }

    private function assertVersion(string $branch, int $expectedVersion): void {
        if ($expectedVersion < 0) {
            throw new InvalidArgumentException('Expected version must be non-negative.');
        }
        if ($this->read($branch)['version'] !== $expectedVersion) {
            throw new WorkspaceException('Tell branch config version conflict. Re-read and retry.');
        }
    }
}
