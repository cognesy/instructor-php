<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\Workspace\Filesystem;

use Cognesy\Tell\Core\Contract\Workspace\CanUseTellBranchConfigurationStore;
use Cognesy\Tell\Core\Workspace\Branch\BranchName;
use Cognesy\Tell\Core\Workspace\Branch\BranchConfigurationPolicy;
use Cognesy\Tell\Core\Configuration\TellPolicyDefaults;
use Cognesy\Tell\Capability\Workspace\Filesystem\PrivateFilesystem;
use Cognesy\Tell\Core\Workspace\WorkspaceException;
use Cognesy\Tell\Capability\Workspace\Filesystem\WorkspaceState;
use InvalidArgumentException;
use JsonException;
use Override;

/** Secret-free, versioned branch-local runtime intent. */
final readonly class FilesystemBranchConfigurationStore implements CanUseTellBranchConfigurationStore
{
    private const int SCHEMA_VERSION = 1;
    private PrivateFilesystem $files;

    public function __construct(private WorkspaceState $workspace, ?PrivateFilesystem $files = null) {
        $this->files = $files ?? PrivateFilesystem::forWorkspace();
    }

    /** @return array{version: int, values: array<string, mixed>} */
    #[Override]
    public function read(string $branch): array {
        $path = $this->path($branch);
        if (!$this->files->exists($path)) {
            return ['version' => 0, 'values' => []];
        }
        $bytes = $this->files->read($path, 'branch config');
        try {
            $data = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new WorkspaceException('Tell branch config is invalid JSON.', previous: $error);
        }
        if (
            !is_array($data)
            || array_is_list($data)
            || array_keys($data) !== ['schema', 'version', 'values']
            || $data['schema'] !== self::SCHEMA_VERSION
            || !is_int($data['version'])
            || $data['version'] < 1
            || !is_array($data['values'])
            || array_is_list($data['values'])
        ) {
            throw new WorkspaceException('Tell branch config has an unsupported shape.');
        }
        foreach ($data['values'] as $key => $value) {
            BranchConfigurationPolicy::assertValue($key, $value);
        }

        return ['version' => $data['version'], 'values' => $data['values']];
    }

    /** @return array<string, int|list<string>|string> */
    #[Override]
    public function runtimeValues(string $branch): array {
        $values = $this->read($branch)['values'];

        return $values;
    }

    #[Override]
    public function executionDefaults(): array {
        return TellPolicyDefaults::fromFile($this->workspace->paths->config . '/defaults.json');
    }

    /** @return list<string> */
    #[Override]
    public function keys(): array {
        return BranchConfigurationPolicy::keys();
    }

    /**
     * @return array{version: int, values: array<string, mixed>, provenance: array<string, 'branch'|'bundled'>}
     */
    #[Override]
    public function effective(string $branch): array {
        $config = $this->read($branch);
        $values = BranchConfigurationPolicy::defaults();
        $provenance = array_fill_keys(array_keys($values), 'bundled');
        foreach ($config['values'] as $key => $value) {
            $values[$key] = $value;
            $provenance[$key] = 'branch';
        }

        return [
            'version' => $config['version'],
            'values' => $values,
            'provenance' => $provenance,
        ];
    }

    /** @return array{version: int, values: array<string, mixed>} */
    #[Override]
    public function set(string $branch, string $key, mixed $value, int $expectedVersion): array {
        $result = $this->mutate($branch, $expectedVersion, function (array $values) use ($key, $value): array {
            BranchConfigurationPolicy::assertValue($key, $value);
            $values[$key] = $value;
            ksort($values, SORT_STRING);

            return $values;
        });

        return ['version' => $result['version'], 'values' => $result['values']];
    }

    /** @return array{version: int, values: array<string, mixed>} */
    #[Override]
    public function delete(string $branch, string $key, int $expectedVersion): array {
        $result = $this->mutate($branch, $expectedVersion, function (array $values) use ($key): array {
            BranchConfigurationPolicy::assertKey($key);
            unset($values[$key]);

            return $values;
        });

        return ['version' => $result['version'], 'values' => $result['values']];
    }

    #[Override]
    public function inherit(string $source, string $destination): void {
        $config = $this->read($source);
        if ($config['version'] === 0) {
            return;
        }
        $this->mutate($destination, 0, static fn (array $values): array => $config['values']);
    }

    /** @return array{version: int, values: array<string, mixed>} */
    /** @param callable(array<string, mixed>): array<string, mixed> $operation */
    private function mutate(string $branch, int $expectedVersion, callable $operation): array {
        if ($expectedVersion < 0) {
            throw new InvalidArgumentException('--if-version must be a non-negative integer.');
        }

        return $this->files->withExclusiveLock($this->lockPath($branch), 'branch config lock', function () use ($branch, $expectedVersion, $operation): array {
            $current = $this->read($branch);
            if ($current['version'] !== $expectedVersion) {
                throw new WorkspaceException('Tell branch config version conflict. Re-read and retry.');
            }

            $values = $operation($current['values']);

            return $this->write($branch, $values, $current['version']);
        });
    }

    /** @param array<string, mixed> $values @return array{version: int, values: array<string, mixed>} */
    private function write(string $branch, array $values, int $previousVersion): array {
        $path = $this->path($branch);
        $version = $previousVersion + 1;
        try {
            $bytes = json_encode([
                'schema' => self::SCHEMA_VERSION,
                'version' => $version,
                'values' => $values,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
        } catch (JsonException $error) {
            throw new WorkspaceException('Tell branch config could not be encoded.', previous: $error);
        }
        $this->files->writeAtomically($path, $bytes, 'branch config', true);

        return ['version' => $version, 'values' => $values];
    }

    private function path(string $branch): string {
        $name = $branch === 'main' ? 'main' : BranchName::fromStored($branch)->toString();

        return $this->workspace->paths->config . '/' . $name . '.json';
    }

    private function lockPath(string $branch): string {
        return $this->path($branch) . '.lock';
    }

}
